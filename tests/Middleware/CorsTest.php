<?php

declare(strict_types=1);

namespace Karhu\Tests\Middleware;

use Karhu\Http\Request;
use Karhu\Http\Response;
use Karhu\Middleware\Cors;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CorsTest extends TestCase
{
    #[Test]
    public function no_origin_passes_through(): void
    {
        $cors = new Cors();
        $req = new Request(server: ['REQUEST_METHOD' => 'GET']);
        $res = $cors($req, fn() => (new Response())->withBody('ok'));
        $this->assertSame('ok', $res->body());
        $this->assertSame('', $res->header('access-control-allow-origin'));
    }

    #[Test]
    public function allowed_origin_adds_headers(): void
    {
        $cors = new Cors(['origins' => ['https://example.com']]);
        $req = new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'HTTP_ORIGIN' => 'https://example.com',
        ]);
        $res = $cors($req, fn() => new Response());
        $this->assertSame('https://example.com', $res->header('access-control-allow-origin'));
        $this->assertSame('Origin', $res->header('vary'));
    }

    #[Test]
    public function disallowed_origin_passes_without_cors_headers(): void
    {
        $cors = new Cors(['origins' => ['https://allowed.com']]);
        $req = new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'HTTP_ORIGIN' => 'https://evil.com',
        ]);
        $res = $cors($req, fn() => new Response());
        $this->assertSame('', $res->header('access-control-allow-origin'));
    }

    #[Test]
    public function preflight_returns_204(): void
    {
        $cors = new Cors(['origins' => ['*']]);
        $req = new Request(server: [
            'REQUEST_METHOD' => 'OPTIONS',
            'HTTP_ORIGIN' => 'https://any.com',
        ]);
        $res = $cors($req, fn() => new Response(500));
        $this->assertSame(204, $res->status());
        $this->assertSame('https://any.com', $res->header('access-control-allow-origin'));
        $this->assertNotEmpty($res->header('access-control-allow-methods'));
        $this->assertNotEmpty($res->header('access-control-allow-headers'));
    }

    #[Test]
    public function credentials_flag(): void
    {
        $cors = new Cors(['origins' => ['*'], 'credentials' => true]);
        $req = new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'HTTP_ORIGIN' => 'https://app.com',
        ]);
        $res = $cors($req, fn() => new Response());
        $this->assertSame('true', $res->header('access-control-allow-credentials'));
    }

    #[Test]
    public function wildcard_origin_allows_any(): void
    {
        $cors = new Cors(); // default: empty origins = allow all
        $req = new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'HTTP_ORIGIN' => 'https://anything.com',
        ]);
        $res = $cors($req, fn() => new Response());
        $this->assertSame('https://anything.com', $res->header('access-control-allow-origin'));
    }
}
