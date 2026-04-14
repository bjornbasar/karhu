<?php

declare(strict_types=1);

namespace Karhu\Tests\Http;

use Karhu\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    #[Test]
    public function method_defaults_to_get(): void
    {
        $req = new Request();
        $this->assertSame('GET', $req->method());
    }

    #[Test]
    public function method_reads_from_server(): void
    {
        $req = new Request(server: ['REQUEST_METHOD' => 'post']);
        $this->assertSame('POST', $req->method());
    }

    #[Test]
    public function path_strips_query_string(): void
    {
        $req = new Request(server: ['REQUEST_URI' => '/users?page=2']);
        $this->assertSame('/users', $req->path());
    }

    #[Test]
    public function path_defaults_to_root(): void
    {
        $req = new Request();
        $this->assertSame('/', $req->path());
    }

    #[Test]
    public function query_returns_get_params(): void
    {
        $req = new Request(get: ['page' => '3', 'q' => 'hello']);
        $this->assertSame('3', $req->query('page'));
        $this->assertSame('hello', $req->query('q'));
        $this->assertSame('default', $req->query('missing', 'default'));
    }

    #[Test]
    public function post_returns_post_params(): void
    {
        $req = new Request(post: ['name' => 'Bjorn']);
        $this->assertSame('Bjorn', $req->post('name'));
        $this->assertSame('', $req->post('missing'));
    }

    #[Test]
    public function headers_extracted_from_server(): void
    {
        $req = new Request(server: [
            'HTTP_HOST' => 'example.com',
            'HTTP_ACCEPT' => 'text/html',
            'CONTENT_TYPE' => 'application/json',
            'CONTENT_LENGTH' => '42',
        ]);

        $this->assertSame('example.com', $req->header('Host'));
        $this->assertSame('text/html', $req->header('Accept'));
        $this->assertSame('application/json', $req->header('Content-Type'));
        $this->assertSame('42', $req->header('Content-Length'));
        $this->assertSame('fallback', $req->header('X-Missing', 'fallback'));
    }

    #[Test]
    public function headers_can_be_provided_directly(): void
    {
        $req = new Request(
            server: ['HTTP_HOST' => 'should-be-ignored'],
            headers: ['host' => 'direct.com'],
        );
        $this->assertSame('direct.com', $req->header('host'));
    }

    #[Test]
    public function body_returns_raw_for_non_json(): void
    {
        $req = new Request(
            server: ['CONTENT_TYPE' => 'text/plain'],
            body: 'raw text',
        );
        $this->assertSame('raw text', $req->body());
    }

    #[Test]
    public function body_auto_decodes_json(): void
    {
        $json = json_encode(['name' => 'karhu', 'version' => 1]);

        $req = new Request(
            server: ['CONTENT_TYPE' => 'application/json'],
            body: $json ?: '',
        );

        $body = $req->body();
        $this->assertIsArray($body);
        $this->assertSame('karhu', $body['name']);
        $this->assertSame(1, $body['version']);
    }

    #[Test]
    public function body_handles_json_plus_suffix(): void
    {
        $json = json_encode(['ok' => true]);

        $req = new Request(
            server: ['CONTENT_TYPE' => 'application/vnd.api+json'],
            body: $json ?: '',
        );

        $body = $req->body();
        $this->assertIsArray($body);
        $this->assertTrue($body['ok']);
    }

    #[Test]
    public function body_returns_empty_array_on_invalid_json(): void
    {
        $req = new Request(
            server: ['CONTENT_TYPE' => 'application/json'],
            body: 'not-json',
        );
        $this->assertSame([], $req->body());
    }

    #[Test]
    public function raw_body_always_returns_string(): void
    {
        $req = new Request(
            server: ['CONTENT_TYPE' => 'application/json'],
            body: '{"a":1}',
        );
        $this->assertSame('{"a":1}', $req->rawBody());
    }

    #[Test]
    public function accepts_checks_accept_header(): void
    {
        $req = new Request(server: [
            'HTTP_ACCEPT' => 'text/html, application/json',
        ]);

        $this->assertTrue($req->accepts('text/html'));
        $this->assertTrue($req->accepts('application/json'));
        $this->assertFalse($req->accepts('application/xml'));
    }

    #[Test]
    public function accepts_wildcard(): void
    {
        $req = new Request(server: ['HTTP_ACCEPT' => '*/*']);
        $this->assertTrue($req->accepts('anything/here'));
    }

    #[Test]
    public function accepts_defaults_to_wildcard_when_no_header(): void
    {
        $req = new Request();
        $this->assertTrue($req->accepts('application/json'));
    }

    #[Test]
    public function route_params_are_immutable(): void
    {
        $req = new Request();
        $this->assertSame([], $req->routeParams());

        $withParams = $req->withRouteParams(['id' => '42']);
        $this->assertSame(['id' => '42'], $withParams->routeParams());
        $this->assertSame([], $req->routeParams(), 'Original unchanged');
    }

    #[Test]
    public function all_headers_are_accessible(): void
    {
        $req = new Request(headers: [
            'host' => 'example.com',
            'accept' => 'text/html',
        ]);
        $this->assertCount(2, $req->headers());
        $this->assertArrayHasKey('host', $req->headers());
    }
}
