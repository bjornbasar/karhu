<?php

declare(strict_types=1);

namespace Karhu\Tests\Http;

use Karhu\Attributes\Route;
use Karhu\Http\Router;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/* Stub controllers for attribute scanning */

final class StubHomeController
{
    #[Route('/', name: 'home')]
    public function index(): void {}
}

final class StubUserController
{
    #[Route('/users', methods: ['GET'], name: 'users.index')]
    public function index(): void {}

    #[Route('/users/{id}', methods: ['GET'], name: 'users.show')]
    public function show(): void {}

    #[Route('/users', methods: ['POST'], name: 'users.create')]
    public function create(): void {}

    #[Route('/users/{id}', methods: ['PUT'], name: 'users.update')]
    public function update(): void {}

    #[Route('/users/{id}', methods: ['DELETE'])]
    public function delete(): void {}
}

final class RouterTest extends TestCase
{
    #[Test]
    public function static_route_matches(): void
    {
        $r = new Router();
        $r->addRoute('/hello', ['GET'], 'Handler::hello');
        $result = $r->match('GET', '/hello');
        $this->assertTrue($result->found);
        $this->assertSame('Handler::hello', $result->handler);
    }

    #[Test]
    public function param_route_extracts_values(): void
    {
        $r = new Router();
        $r->addRoute('/users/{id}', ['GET'], 'Handler::show');
        $result = $r->match('GET', '/users/42');
        $this->assertTrue($result->found);
        $this->assertSame(['id' => '42'], $result->params);
    }

    #[Test]
    public function multiple_params(): void
    {
        $r = new Router();
        $r->addRoute('/users/{userId}/posts/{postId}', ['GET'], 'Handler::post');
        $result = $r->match('GET', '/users/5/posts/99');
        $this->assertTrue($result->found);
        $this->assertSame(['userId' => '5', 'postId' => '99'], $result->params);
    }

    #[Test]
    public function not_found_for_unregistered_path(): void
    {
        $r = new Router();
        $r->addRoute('/hello', ['GET'], 'Handler::hello');
        $result = $r->match('GET', '/goodbye');
        $this->assertFalse($result->found);
        $this->assertFalse($result->isMethodNotAllowed());
    }

    #[Test]
    public function method_not_allowed_returns_allowed_methods(): void
    {
        $r = new Router();
        $r->addRoute('/items', ['GET'], 'Handler::list');
        $r->addRoute('/items', ['POST'], 'Handler::create');

        $result = $r->match('DELETE', '/items');
        $this->assertFalse($result->found);
        $this->assertTrue($result->isMethodNotAllowed());
        $this->assertContains('GET', $result->allowedMethods);
        $this->assertContains('POST', $result->allowedMethods);
    }

    #[Test]
    public function head_auto_matches_get_routes(): void
    {
        $r = new Router();
        $r->addRoute('/page', ['GET'], 'Handler::page');
        $result = $r->match('HEAD', '/page');
        $this->assertTrue($result->found);
        $this->assertSame('HEAD', $result->method);
    }

    #[Test]
    public function options_returns_405_with_all_methods(): void
    {
        $r = new Router();
        $r->addRoute('/items', ['GET'], 'Handler::list');
        $r->addRoute('/items', ['POST'], 'Handler::create');

        $result = $r->match('OPTIONS', '/items');
        $this->assertTrue($result->isMethodNotAllowed());
        $this->assertContains('GET', $result->allowedMethods);
        $this->assertContains('HEAD', $result->allowedMethods);
        $this->assertContains('POST', $result->allowedMethods);
        $this->assertContains('OPTIONS', $result->allowedMethods);
    }

    #[Test]
    public function route_groups_apply_prefix(): void
    {
        $r = new Router();
        $r->group('/api/v1', function (Router $r) {
            $r->addRoute('/users', ['GET'], 'Api::users');
            $r->addRoute('/posts', ['GET'], 'Api::posts');
        });

        $this->assertTrue($r->match('GET', '/api/v1/users')->found);
        $this->assertTrue($r->match('GET', '/api/v1/posts')->found);
        $this->assertFalse($r->match('GET', '/users')->found);
    }

    #[Test]
    public function nested_groups(): void
    {
        $r = new Router();
        $r->group('/api', function (Router $r) {
            $r->group('/v2', function (Router $r) {
                $r->addRoute('/items', ['GET'], 'Api::items');
            });
        });

        $this->assertTrue($r->match('GET', '/api/v2/items')->found);
    }

    #[Test]
    public function base_path_is_stripped_before_matching(): void
    {
        $r = new Router();
        $r->setBasePath('/app');
        $r->addRoute('/dashboard', ['GET'], 'Handler::dash');

        $this->assertTrue($r->match('GET', '/app/dashboard')->found);
        $this->assertFalse($r->match('GET', '/dashboard')->found);
    }

    #[Test]
    public function named_routes_and_url_for(): void
    {
        $r = new Router();
        $r->addRoute('/users/{id}', ['GET'], 'Handler::show', 'user.show');

        $this->assertSame('/users/42', $r->urlFor('user.show', ['id' => '42']));
    }

    #[Test]
    public function url_for_with_base_path(): void
    {
        $r = new Router();
        $r->setBasePath('/app');
        $r->addRoute('/users/{id}', ['GET'], 'Handler::show', 'user.show');

        $this->assertSame('/app/users/42', $r->urlFor('user.show', ['id' => '42']));
    }

    #[Test]
    public function url_for_unknown_name_throws(): void
    {
        $r = new Router();
        $this->expectException(\InvalidArgumentException::class);
        $r->urlFor('nonexistent');
    }

    #[Test]
    public function url_for_missing_param_throws(): void
    {
        $r = new Router();
        $r->addRoute('/users/{id}', ['GET'], 'Handler::show', 'user.show');

        $this->expectException(\InvalidArgumentException::class);
        $r->urlFor('user.show');
    }

    #[Test]
    public function scan_controllers_discovers_routes(): void
    {
        $r = new Router();
        $r->scanControllers([
            StubHomeController::class,
            StubUserController::class,
        ]);

        $result = $r->match('GET', '/');
        $this->assertTrue($result->found);
        $this->assertStringContainsString('StubHomeController::index', $result->handler);

        $result = $r->match('GET', '/users');
        $this->assertTrue($result->found);
        $this->assertStringContainsString('StubUserController::index', $result->handler);

        $result = $r->match('GET', '/users/7');
        $this->assertTrue($result->found);
        $this->assertSame(['id' => '7'], $result->params);

        $result = $r->match('POST', '/users');
        $this->assertTrue($result->found);

        $result = $r->match('PUT', '/users/7');
        $this->assertTrue($result->found);

        $result = $r->match('DELETE', '/users/7');
        $this->assertTrue($result->found);
    }

    #[Test]
    public function scanned_named_routes_work_with_url_for(): void
    {
        $r = new Router();
        $r->scanControllers([StubUserController::class]);

        $this->assertSame('/users', $r->urlFor('users.index'));
        $this->assertSame('/users/42', $r->urlFor('users.show', ['id' => '42']));
    }

    #[Test]
    public function cache_round_trip(): void
    {
        $original = new Router();
        $original->addRoute('/cached/{id}', ['GET'], 'Handler::cached', 'cached.show');

        $cache = $original->dumpCache();

        $restored = new Router();
        $restored->loadCache($cache);

        $result = $restored->match('GET', '/cached/99');
        $this->assertTrue($result->found);
        $this->assertSame(['id' => '99'], $result->params);
        $this->assertSame('/cached/99', $restored->urlFor('cached.show', ['id' => '99']));
    }

    #[Test]
    public function trailing_slash_normalised(): void
    {
        $r = new Router();
        $r->addRoute('/hello', ['GET'], 'Handler::hello');

        $this->assertTrue($r->match('GET', '/hello/')->found);
        $this->assertTrue($r->match('GET', '/hello')->found);
    }

    #[Test]
    public function root_route_matches(): void
    {
        $r = new Router();
        $r->addRoute('/', ['GET'], 'Handler::home');
        $this->assertTrue($r->match('GET', '/')->found);
    }
}
