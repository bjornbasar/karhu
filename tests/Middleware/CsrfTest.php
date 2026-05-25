<?php

declare(strict_types=1);

namespace Karhu\Tests\Middleware;

use Karhu\Http\Request;
use Karhu\Http\Response;
use Karhu\Middleware\Csrf;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        // Start a real session so the middleware's session-backed token storage
        // works the same way it does in production. Without this, getStoredToken()
        // falls back to $_COOKIE and regenerate() can't persist updates.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        @session_start();
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
    }

    #[Test]
    public function get_requests_bypass_csrf(): void
    {
        $mw = new Csrf();
        $req = new Request(server: ['REQUEST_METHOD' => 'GET']);
        $res = $mw($req, fn() => (new Response())->withBody('ok'));
        $this->assertSame(200, $res->status());
    }

    #[Test]
    public function head_requests_bypass_csrf(): void
    {
        $mw = new Csrf();
        $req = new Request(server: ['REQUEST_METHOD' => 'HEAD']);
        $res = $mw($req, fn() => new Response());
        $this->assertSame(200, $res->status());
    }

    #[Test]
    public function options_requests_bypass_csrf(): void
    {
        $mw = new Csrf();
        $req = new Request(server: ['REQUEST_METHOD' => 'OPTIONS']);
        $res = $mw($req, fn() => new Response());
        $this->assertSame(200, $res->status());
    }

    #[Test]
    public function post_without_token_returns_403(): void
    {
        $_SESSION = [];
        $mw = new Csrf();
        $req = new Request(server: ['REQUEST_METHOD' => 'POST']);
        $res = $mw($req, fn() => new Response());
        $this->assertSame(403, $res->status());
    }

    #[Test]
    public function token_generates_hex_string(): void
    {
        $token = Csrf::token();
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    #[Test]
    public function field_returns_hidden_input(): void
    {
        $field = Csrf::field();
        $this->assertStringContainsString('type="hidden"', $field);
        $this->assertStringContainsString('name="_csrf_token"', $field);
    }

    #[Test]
    public function post_with_json_deny_returns_problem_json(): void
    {
        $_SESSION = [];
        $mw = new Csrf();
        $req = new Request(server: [
            'REQUEST_METHOD' => 'POST',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $res = $mw($req, fn() => new Response());
        $this->assertSame(403, $res->status());
        $body = json_decode($res->body(), true);
        $this->assertSame('CSRF token mismatch', $body['title']);
    }

    #[Test]
    public function token_is_unchanged_after_successful_post(): void
    {
        $_SESSION['_csrf_token'] = str_repeat('a', 64);
        $original = Csrf::token();

        $mw = new Csrf();
        $req = new Request(
            server: ['REQUEST_METHOD' => 'POST'],
            post: ['_csrf_token' => $original],
        );
        $res = $mw($req, fn() => (new Response())->withBody('ok'));

        $this->assertSame(200, $res->status());
        // Token must not rotate per-POST — multi-tab workflows would break.
        $this->assertSame($original, Csrf::token());
    }

    #[Test]
    public function explicit_regenerate_rotates_the_token(): void
    {
        $_SESSION['_csrf_token'] = str_repeat('b', 64);
        $original = Csrf::token();

        $next = Csrf::regenerate();

        $this->assertNotSame($original, $next);
        $this->assertSame($next, Csrf::token());
    }
}
