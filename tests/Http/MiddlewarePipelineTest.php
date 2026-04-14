<?php

declare(strict_types=1);

namespace Karhu\Tests\Http;

use Karhu\Http\MiddlewarePipeline;
use Karhu\Http\Request;
use Karhu\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MiddlewarePipelineTest extends TestCase
{
    #[Test]
    public function empty_pipeline_calls_handler_directly(): void
    {
        $pipe = new MiddlewarePipeline();
        $res = $pipe->handle(new Request(), fn () => (new Response())->withBody('handler'));
        $this->assertSame('handler', $res->body());
    }

    #[Test]
    public function middleware_wraps_handler(): void
    {
        $pipe = new MiddlewarePipeline();
        $pipe->pipe(function (Request $req, callable $next): Response {
            /** @var Response $res */
            $res = $next($req);
            return $res->withHeader('X-Middleware', 'applied');
        });

        $res = $pipe->handle(new Request(), fn () => (new Response())->withBody('ok'));
        $this->assertSame('ok', $res->body());
        $this->assertSame('applied', $res->header('x-middleware'));
    }

    #[Test]
    public function middleware_runs_in_fifo_order(): void
    {
        $order = [];

        $pipe = new MiddlewarePipeline();

        $pipe->pipe(function (Request $req, callable $next) use (&$order): Response {
            $order[] = 'A-before';
            $res = $next($req);
            $order[] = 'A-after';
            return $res;
        });

        $pipe->pipe(function (Request $req, callable $next) use (&$order): Response {
            $order[] = 'B-before';
            $res = $next($req);
            $order[] = 'B-after';
            return $res;
        });

        $pipe->handle(new Request(), function () use (&$order) {
            $order[] = 'handler';
            return new Response();
        });

        $this->assertSame(['A-before', 'B-before', 'handler', 'B-after', 'A-after'], $order);
    }

    #[Test]
    public function middleware_can_short_circuit(): void
    {
        $pipe = new MiddlewarePipeline();

        $pipe->pipe(fn (Request $req, callable $next): Response => (new Response(403))->withBody('blocked'));

        $pipe->pipe(fn (Request $req, callable $next): Response => (new Response())->withBody('should not reach'));

        $res = $pipe->handle(new Request(), fn () => (new Response())->withBody('should not reach either'));
        $this->assertSame(403, $res->status());
        $this->assertSame('blocked', $res->body());
    }

    #[Test]
    public function middleware_can_modify_request(): void
    {
        $pipe = new MiddlewarePipeline();

        $pipe->pipe(function (Request $req, callable $next): Response {
            $modified = $req->withRouteParams(['injected' => 'yes']);
            return $next($modified);
        });

        $captured = null;
        $pipe->handle(new Request(), function (Request $req) use (&$captured) {
            $captured = $req->routeParams();
            return new Response();
        });

        $this->assertSame(['injected' => 'yes'], $captured);
    }

    #[Test]
    public function pipe_is_fluent(): void
    {
        $pipe = new MiddlewarePipeline();
        $result = $pipe->pipe(fn (Request $r, callable $n) => $n($r));
        $this->assertSame($pipe, $result);
    }
}
