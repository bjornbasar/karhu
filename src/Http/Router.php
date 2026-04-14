<?php

declare(strict_types=1);

namespace Karhu\Http;

use Karhu\Attributes\Route;
use ReflectionClass;
use ReflectionMethod;

/**
 * Attribute-scanned router with route groups, named routes, and
 * production-ready cache support.
 *
 * Converts route paths (e.g. /users/{id}) to regex patterns and
 * matches sequentially. For production, dump the compiled table
 * via dumpCache() and load it with loadCache() to skip reflection.
 */
final class Router
{
    /**
     * @var list<array{
     *   pattern: string,
     *   methods: list<string>,
     *   handler: string,
     *   name: string|null,
     *   paramNames: list<string>
     * }>
     */
    private array $routes = [];

    /** @var array<string, int> Named route index → position in $routes */
    private array $namedIndex = [];

    private string $basePath = '';

    /** @var list<string> Active group prefixes (stack for nested groups) */
    private array $groupPrefixes = [];

    /** Set a base path prefix for sub-directory deployments. */
    public function setBasePath(string $basePath): void
    {
        $this->basePath = '/' . trim($basePath, '/');
        if ($this->basePath === '/') {
            $this->basePath = '';
        }
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    /**
     * Define a route group with a shared prefix.
     *
     * @param string   $prefix   Path prefix, e.g. '/api/v1'
     * @param callable $callback Receives the Router instance
     */
    public function group(string $prefix, callable $callback): void
    {
        $this->groupPrefixes[] = '/' . trim($prefix, '/');
        $callback($this);
        array_pop($this->groupPrefixes);
    }

    /**
     * Register a route explicitly (used by scanControllers, or directly).
     *
     * @param string       $path    URI pattern, e.g. '/users/{id}'
     * @param list<string> $methods HTTP methods
     * @param string       $handler Controller class::method, e.g. 'App\Controllers\UserController::show'
     * @param string|null  $name    Optional route name
     */
    public function addRoute(string $path, array $methods, string $handler, ?string $name = null): void
    {
        $prefix = implode('', $this->groupPrefixes);
        $fullPath = $prefix . '/' . ltrim($path, '/');
        $fullPath = '/' . trim($fullPath, '/');
        if ($fullPath !== '/') {
            $fullPath = rtrim($fullPath, '/');
        }

        // Extract parameter names from {param} placeholders
        $paramNames = [];
        $pattern = preg_replace_callback(
            '#\{(\w+)\}#',
            static function (array $matches) use (&$paramNames): string {
                $paramNames[] = $matches[1];
                return '([^/]+)';
            },
            $fullPath,
        );

        $methods = array_map('strtoupper', $methods);
        $index = count($this->routes);

        $this->routes[] = [
            'pattern' => '#^' . ($pattern ?? $fullPath) . '$#',
            'methods' => $methods,
            'handler' => $handler,
            'name' => $name,
            'paramNames' => $paramNames,
        ];

        if ($name !== null) {
            $this->namedIndex[$name] = $index;
        }
    }

    /**
     * Scan controller classes for #[Route] attributes and register them.
     *
     * @param list<class-string> $controllers
     */
    public function scanControllers(array $controllers): void
    {
        foreach ($controllers as $class) {
            $ref = new ReflectionClass($class);

            foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $attrs = $method->getAttributes(Route::class);

                foreach ($attrs as $attr) {
                    /** @var Route $route */
                    $route = $attr->newInstance();
                    $handler = $class . '::' . $method->getName();
                    $this->addRoute($route->path, $route->methods, $handler, $route->name);
                }
            }
        }
    }

    /**
     * Match a request method + path against registered routes.
     *
     * Handles HEAD automatically for GET routes; returns 405 with
     * the Allow header when the path matches but the method doesn't.
     */
    public function match(string $method, string $path): RouteResult
    {
        $method = strtoupper($method);

        // Strip base path prefix before matching; reject paths that don't
        // start with the configured base path.
        if ($this->basePath !== '') {
            if (!str_starts_with($path, $this->basePath)) {
                return RouteResult::notFound();
            }
            $path = substr($path, strlen($this->basePath)) ?: '/';
        }

        // Normalise: strip trailing slash (except root)
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        $allowedMethods = [];

        foreach ($this->routes as $route) {
            if (!preg_match($route['pattern'], $path, $matches)) {
                continue;
            }

            // Path matched — check method
            $routeMethods = $route['methods'];

            // HEAD is implicitly allowed for any GET route
            $effectiveMethods = $routeMethods;
            if (in_array('GET', $routeMethods, true) && !in_array('HEAD', $routeMethods, true)) {
                $effectiveMethods[] = 'HEAD';
            }

            if (in_array($method, $effectiveMethods, true)) {
                // Extract named parameters
                $params = [];
                foreach ($route['paramNames'] as $i => $name) {
                    $params[$name] = $matches[$i + 1] ?? '';
                }

                return RouteResult::found($route['handler'], $method, $params);
            }

            // Track allowed methods for 405 response
            $allowedMethods = array_merge($allowedMethods, $effectiveMethods);
        }

        // OPTIONS auto-response: return all known methods for matched paths
        if ($method === 'OPTIONS' && $allowedMethods !== []) {
            $allowedMethods[] = 'OPTIONS';
            $allowedMethods = array_values(array_unique($allowedMethods));
            sort($allowedMethods);
            return RouteResult::methodNotAllowed($allowedMethods);
        }

        if ($allowedMethods !== []) {
            $allowedMethods = array_values(array_unique($allowedMethods));
            sort($allowedMethods);
            return RouteResult::methodNotAllowed($allowedMethods);
        }

        return RouteResult::notFound();
    }

    /**
     * Generate a URL for a named route.
     *
     * @param string               $name   Route name
     * @param array<string, string> $params Parameter substitutions
     * @return string Full path including basePath
     *
     * @throws \InvalidArgumentException If route name is not found
     */
    public function urlFor(string $name, array $params = []): string
    {
        if (!isset($this->namedIndex[$name])) {
            throw new \InvalidArgumentException("Route '{$name}' not found.");
        }

        $route = $this->routes[$this->namedIndex[$name]];

        // Reverse the pattern to get the original path template
        // Replace each capture group with the corresponding param value
        $path = $route['pattern'];

        // Strip regex delimiters and anchors: #^....$# → path with ([^/]+)
        $path = (string) preg_replace('#^\#\^|\$\#$#', '', $path);

        foreach ($route['paramNames'] as $paramName) {
            $value = $params[$paramName] ?? throw new \InvalidArgumentException(
                "Missing parameter '{$paramName}' for route '{$name}'."
            );
            $path = (string) preg_replace('#\([^)]+\)#', $value, $path, 1);
        }

        return $this->basePath . ($path !== '' ? $path : '/');
    }

    /**
     * Dump the compiled route table as a PHP array (for route:cache).
     *
     * @return array{routes: list<array{pattern: string, methods: list<string>, handler: string, name: string|null, paramNames: list<string>}>, namedIndex: array<string, int>}
     */
    public function dumpCache(): array
    {
        return [
            'routes' => $this->routes,
            'namedIndex' => $this->namedIndex,
        ];
    }

    /**
     * Load a previously cached route table, skipping reflection.
     *
     * @param array{routes: list<array{pattern: string, methods: list<string>, handler: string, name: string|null, paramNames: list<string>}>, namedIndex: array<string, int>} $cache
     */
    public function loadCache(array $cache): void
    {
        $this->routes = $cache['routes'];
        $this->namedIndex = $cache['namedIndex'];
    }

    /** @return list<array{pattern: string, methods: list<string>, handler: string, name: string|null, paramNames: list<string>}> */
    public function routes(): array
    {
        return $this->routes;
    }
}
