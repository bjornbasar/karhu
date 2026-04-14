<?php

declare(strict_types=1);

namespace Karhu\Tests;

use Karhu\App;
use Karhu\Attributes\Route;
use Karhu\Container\Container;
use Karhu\Http\Request;
use Karhu\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/* --- Stub controllers --- */

final class StubAppController
{
    #[Route('/', name: 'home')]
    public function home(Request $request): Response
    {
        return (new Response())->withBody('hello karhu');
    }

    #[Route('/users/{id}', methods: ['GET'], name: 'users.show')]
    public function show(Request $request): Response
    {
        return (new Response())->json(['id' => $request->routeParams()['id']]);
    }

    #[Route('/string', methods: ['GET'])]
    public function returnString(): string
    {
        return 'raw string';
    }

    #[Route('/array', methods: ['GET'])]
    public function returnArray(): array
    {
        return ['key' => 'value'];
    }
}

/* --- Tests --- */

final class AppTest extends TestCase
{
    private function createApp(): App
    {
        $app = new App();
        $app->router()->scanControllers([StubAppController::class]);
        return $app;
    }

    #[Test]
    public function handles_matched_route(): void
    {
        $app = $this->createApp();
        $res = $app->handle(new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']));

        $this->assertSame(200, $res->status());
        $this->assertSame('hello karhu', $res->body());
    }

    #[Test]
    public function handles_route_with_params(): void
    {
        $app = $this->createApp();
        $res = $app->handle(new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/users/42']));

        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('"id":"42"', $res->body());
    }

    #[Test]
    public function returns_404_for_unknown_path(): void
    {
        $app = $this->createApp();
        $res = $app->handle(new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/unknown']));

        $this->assertSame(404, $res->status());
    }

    #[Test]
    public function returns_405_for_wrong_method(): void
    {
        $app = $this->createApp();
        $res = $app->handle(new Request(server: ['REQUEST_METHOD' => 'DELETE', 'REQUEST_URI' => '/']));

        $this->assertSame(405, $res->status());
        $this->assertNotEmpty($res->header('allow'));
    }

    #[Test]
    public function middleware_runs_before_dispatch(): void
    {
        $app = $this->createApp();
        $app->pipe(function (Request $req, callable $next): Response {
            /** @var Response $res */
            $res = $next($req);
            return $res->withHeader('X-Framework', 'karhu');
        });

        $res = $app->handle(new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']));
        $this->assertSame('karhu', $res->header('x-framework'));
    }

    #[Test]
    public function string_return_wrapped_in_response(): void
    {
        $app = $this->createApp();
        $res = $app->handle(new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/string']));
        $this->assertSame('raw string', $res->body());
    }

    #[Test]
    public function array_return_json_encoded(): void
    {
        $app = $this->createApp();
        $res = $app->handle(new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/array']));
        $this->assertSame('application/json', $res->header('content-type'));
        $this->assertStringContainsString('"key":"value"', $res->body());
    }

    #[Test]
    public function base_path_applies(): void
    {
        $app = $this->createApp();
        $app->setBasePath('/sub');

        $res = $app->handle(new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/sub/']));
        $this->assertSame(200, $res->status());
        $this->assertSame('hello karhu', $res->body());
    }

    #[Test]
    public function container_is_accessible(): void
    {
        $app = new App();
        $this->assertInstanceOf(Container::class, $app->container());
    }

    #[Test]
    public function pipe_is_fluent(): void
    {
        $app = new App();
        $result = $app->pipe(fn (Request $r, callable $n) => $n($r));
        $this->assertSame($app, $result);
    }
}
