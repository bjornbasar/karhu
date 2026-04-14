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
}
