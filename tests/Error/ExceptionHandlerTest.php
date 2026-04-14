<?php

declare(strict_types=1);

namespace Karhu\Tests\Error;

use Karhu\Error\ExceptionHandler;
use Karhu\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExceptionHandlerTest extends TestCase
{
    #[Test]
    public function json_response_in_dev_mode(): void
    {
        $handler = new ExceptionHandler(devMode: true);
        $request = new Request(server: ['HTTP_ACCEPT' => 'application/json']);

        $res = $handler->handle(new \RuntimeException('boom'), $request);

        $this->assertSame(500, $res->status());
        $this->assertStringContainsString('application/problem+json', $res->header('content-type'));

        $body = json_decode($res->body(), true);
        $this->assertSame('Internal Server Error', $body['title']);
        $this->assertSame(500, $body['status']);
        $this->assertSame('boom', $body['detail']);
        $this->assertArrayHasKey('trace', $body);
    }

    #[Test]
    public function json_response_in_prod_mode_hides_details(): void
    {
        $handler = new ExceptionHandler(devMode: false);
        $request = new Request(server: ['HTTP_ACCEPT' => 'application/json']);

        $res = $handler->handle(new \RuntimeException('secret'), $request);

        $body = json_decode($res->body(), true);
        $this->assertSame('Internal Server Error', $body['title']);
        $this->assertArrayNotHasKey('detail', $body);
        $this->assertArrayNotHasKey('trace', $body);
    }

    #[Test]
    public function html_response_in_dev_mode_shows_trace(): void
    {
        $handler = new ExceptionHandler(devMode: true);
        $request = new Request(server: ['HTTP_ACCEPT' => 'text/html']);

        $res = $handler->handle(new \RuntimeException('html-boom'), $request);

        $this->assertSame(500, $res->status());
        $this->assertStringContainsString('text/html', $res->header('content-type'));
        $this->assertStringContainsString('html-boom', $res->body());
        $this->assertStringContainsString('RuntimeException', $res->body());
    }

    #[Test]
    public function html_response_in_prod_mode_is_generic(): void
    {
        $handler = new ExceptionHandler(devMode: false);
        $request = new Request(server: ['HTTP_ACCEPT' => 'text/html']);

        $res = $handler->handle(new \RuntimeException('secret'), $request);

        $this->assertSame(500, $res->status());
        $this->assertStringNotContainsString('secret', $res->body());
        $this->assertStringContainsString('Internal Server Error', $res->body());
    }

    #[Test]
    public function invalid_argument_maps_to_400(): void
    {
        $handler = new ExceptionHandler(devMode: false);
        $request = new Request(server: ['HTTP_ACCEPT' => 'application/json']);

        $res = $handler->handle(new \InvalidArgumentException('bad input'), $request);
        $this->assertSame(400, $res->status());
    }

    #[Test]
    public function defaults_to_html_when_accept_includes_html(): void
    {
        $handler = new ExceptionHandler(devMode: true);
        // Browser-style Accept header includes both
        $request = new Request(server: ['HTTP_ACCEPT' => 'text/html, application/json']);

        $res = $handler->handle(new \RuntimeException('test'), $request);
        $this->assertStringContainsString('text/html', $res->header('content-type'));
    }

    #[Test]
    public function handles_without_request(): void
    {
        $handler = new ExceptionHandler(devMode: false);
        $res = $handler->handle(new \RuntimeException('no request'));

        $this->assertSame(500, $res->status());
        $this->assertStringContainsString('text/html', $res->header('content-type'));
    }
}
