<?php

declare(strict_types=1);

namespace Karhu;

use Karhu\Container\Container;
use Karhu\Http\DefaultErrorHandler;
use Karhu\Http\ErrorHandler;
use Karhu\Http\MiddlewarePipeline;
use Karhu\Http\NotFoundException;
use Karhu\Http\Request;
use Karhu\Http\Response;
use Karhu\Http\Router;
use Karhu\Http\RouteResult;

/**
 * Karhu application — front controller.
 *
 * Boots the router, resolves controllers via the container, runs the
 * middleware pipeline, and emits the response. Descendant of chukwu's
 * Core_Chukwu and Peopsquik's Core_Peopsquik::display().
 */
final class App
{
    private Router $router;
    private Container $container;
    private MiddlewarePipeline $pipeline;

    public function __construct(?Container $container = null)
    {
        $this->container = $container ?? new Container();
        $this->router = new Router();
        $this->pipeline = new MiddlewarePipeline();

        // Register core services in the container
        $this->container->set(self::class, $this);
        $this->container->set(Router::class, $this->router);
        $this->container->set(Container::class, $this->container);
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function container(): Container
    {
        return $this->container;
    }

    /**
     * Add middleware to the application pipeline.
     *
     * @param callable(Request, callable): Response $middleware
     */
    public function pipe(callable $middleware): self
    {
        $this->pipeline->pipe($middleware);
        return $this;
    }

    /**
     * Set the base path for sub-directory deployments.
     */
    public function setBasePath(string $basePath): self
    {
        $this->router->setBasePath($basePath);
        return $this;
    }

    /**
     * Handle a request through the middleware pipeline → router → controller.
     */
    public function handle(Request $request): Response
    {
        return $this->pipeline->handle($request, fn(Request $req) => $this->dispatch($req));
    }

    /**
     * Handle + emit. The standard entry point from public/index.php.
     */
    public function run(?Request $request = null): void
    {
        $request ??= Request::fromGlobals();
        $this->handle($request)->emit();
    }

    /**
     * Dispatch a request to the matched controller method.
     */
    private function dispatch(Request $request): Response
    {
        $result = $this->router->match($request->method(), $request->path());

        if ($result->isMethodNotAllowed()) {
            // 405 preserves the classic hard fallback for now — the
            // ErrorHandler seam is 404-first; 405 stays inline until a
            // future release wants to unify. Body + Allow header shape
            // matches pre-v0.1.4 verbatim.
            return (new Response(405))
                ->withHeader('Allow', implode(', ', $result->allowedMethods))
                ->withBody('Method Not Allowed');
        }

        if (!$result->found) {
            // v0.1.4 — unmatched-route path resolves the container-bound
            // ErrorHandler (or DefaultErrorHandler fallback) so consumers
            // can render a branded 404. See resolveErrorHandler() for the
            // defensive try/catch that guarantees a 404 even when the
            // bound handler itself explodes.
            return $this->handleNotFound($request, null);
        }

        // Inject route params into the request
        $request = $request->withRouteParams($result->params);
        $this->container->set(Request::class, $request);

        return $this->callHandler($result, $request);
    }

    /**
     * Invoke the controller method, passing route params as arguments.
     */
    private function callHandler(RouteResult $result, Request $request): Response
    {
        [$class, $method] = explode('::', $result->handler);

        $controller = $this->container->get($class);

        // v0.1.4 — a matched controller throwing NotFoundException routes
        // through the SAME handler as the unmatched-route path. Idiomatic
        // "resource-by-id not found" becomes `throw new NotFoundException()`
        // in the controller — the response body/branding lives in one
        // place, not duplicated across every controller. This catch
        // deliberately runs INSIDE dispatch (not in ExceptionHandler)
        // because ExceptionHandler is instantiated pre-App at
        // set_exception_handler() time and has no container access.
        try {
            $response = $controller->{$method}($request);
        } catch (NotFoundException $e) {
            return $this->handleNotFound($request, $e);
        }

        if ($response instanceof Response) {
            return $response;
        }

        // If the handler returns a string, wrap it in a response
        if (is_string($response)) {
            return (new Response())->withBody($response);
        }

        // If the handler returns an array, JSON-encode it
        if (is_array($response)) {
            return (new Response())->json($response);
        }

        return new Response();
    }

    /**
     * Build a 404 Response via the bound ErrorHandler (or DefaultErrorHandler
     * fallback). Shared by BOTH the unmatched-route path and the "matched
     * controller threw NotFoundException" path.
     *
     * Defensive try/catch: if a bound handler itself throws (missing Twig
     * template, DB failure in a nav context lookup, any other Throwable)
     * we fall back to DefaultErrorHandler so a 404 is ALWAYS served. This
     * preserves the pre-v0.1.4 guarantee that "unmatched route always
     * returns 'Not Found'" — the branded upgrade must not regress that.
     */
    private function handleNotFound(Request $request, ?\Throwable $error): Response
    {
        $handler = $this->resolveErrorHandler();
        try {
            return $handler->handle($request, $error, ['status' => 404]);
        } catch (\Throwable) {
            // Any bound-handler failure → bland default. Swallow the
            // internal Throwable so a broken 404 template doesn't cascade
            // into a 500 for the user; the outer set_exception_handler
            // will still log the underlying issue if it propagates from
            // the DefaultErrorHandler (extremely unlikely — it's ~4 LOC).
            return (new DefaultErrorHandler())->handle($request, $error, ['status' => 404]);
        }
    }

    /**
     * Container-bind aware lookup with default fallback. Returns the bound
     * ErrorHandler if one exists (typical: an app binds its own branded
     * implementation in bootstrap); otherwise a shared DefaultErrorHandler.
     */
    private function resolveErrorHandler(): ErrorHandler
    {
        // has() correctly returns false for interfaces with no explicit
        // binding — class_exists() returns false for interfaces, so the
        // Container.php:108 autoload-fallback doesn't trip.
        if ($this->container->has(ErrorHandler::class)) {
            $bound = $this->container->get(ErrorHandler::class);
            if ($bound instanceof ErrorHandler) {
                return $bound;
            }
        }
        return new DefaultErrorHandler();
    }
}
