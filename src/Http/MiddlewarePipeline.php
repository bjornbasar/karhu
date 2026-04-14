<?php

declare(strict_types=1);

namespace Karhu\Http;

/**
 * PSR-15-shape middleware pipeline — self-contained, no external deps.
 *
 * Each middleware is a callable that receives (Request, callable $next)
 * and returns a Response. The pipeline runs them in FIFO order;
 * $next passes control to the remaining stack.
 */
final class MiddlewarePipeline
{
    /** @var list<callable(Request, callable): Response> */
    private array $middleware = [];

    /**
     * Add a middleware to the end of the pipeline.
     *
     * @param callable(Request, callable): Response $middleware
     */
    public function pipe(callable $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * Run the pipeline, terminating with the given handler.
     *
     * @param Request                     $request The incoming request
     * @param callable(Request): Response $handler The final handler (controller dispatch)
     */
    public function handle(Request $request, callable $handler): Response
    {
        $runner = $this->buildRunner($handler);
        return $runner($request);
    }

    /**
     * Build a nested callable that chains all middleware with the final handler.
     *
     * @param callable(Request): Response $handler
     * @return callable(Request): Response
     */
    private function buildRunner(callable $handler): callable
    {
        $stack = $handler;

        // Wrap from last to first so the first middleware runs first
        foreach (array_reverse($this->middleware) as $mw) {
            $next = $stack;
            $stack = static fn (Request $request): Response => $mw($request, $next);
        }

        return $stack;
    }
}
