<?php

declare(strict_types=1);

namespace Karhu;

use Karhu\Container\Container;
use Karhu\Http\MiddlewarePipeline;
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
            return (new Response(405))
                ->withHeader('Allow', implode(', ', $result->allowedMethods))
                ->withBody('Method Not Allowed');
        }

        if (!$result->found) {
            return (new Response(404))->withBody('Not Found');
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
        $response = $controller->{$method}($request);

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
}
