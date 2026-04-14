<?php

declare(strict_types=1);

namespace Karhu\Tests\Http;

use Karhu\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    #[Test]
    public function defaults_to_200(): void
    {
        $res = new Response();
        $this->assertSame(200, $res->status());
        $this->assertSame('', $res->body());
    }

    #[Test]
    public function constructor_accepts_status(): void
    {
        $res = new Response(404);
        $this->assertSame(404, $res->status());
    }

    #[Test]
    public function with_status_is_immutable(): void
    {
        $a = new Response();
        $b = $a->withStatus(201);
        $this->assertSame(200, $a->status());
        $this->assertSame(201, $b->status());
    }

    #[Test]
    public function with_header_is_case_insensitive(): void
    {
        $res = (new Response())
            ->withHeader('Content-Type', 'text/html')
            ->withHeader('X-Custom', 'value');

        $this->assertSame('text/html', $res->header('content-type'));
        $this->assertSame('value', $res->header('x-custom'));
        $this->assertSame('', $res->header('missing'));
    }

    #[Test]
    public function with_body(): void
    {
        $res = (new Response())->withBody('hello');
        $this->assertSame('hello', $res->body());
    }

    #[Test]
    public function json_sets_content_type_and_encodes(): void
    {
        $res = (new Response())->json(['name' => 'karhu'], 201);

        $this->assertSame(201, $res->status());
        $this->assertSame('application/json', $res->header('content-type'));
        $this->assertSame('{"name":"karhu"}', $res->body());
    }

    #[Test]
    public function json_does_not_escape_slashes(): void
    {
        $res = (new Response())->json(['url' => 'https://example.com/path']);
        $this->assertStringContainsString('https://example.com/path', $res->body());
    }

    #[Test]
    public function redirect_sets_location_and_status(): void
    {
        $res = (new Response())->redirect('/login');
        $this->assertSame(302, $res->status());
        $this->assertSame('/login', $res->header('location'));
    }

    #[Test]
    public function redirect_with_custom_status(): void
    {
        $res = (new Response())->redirect('/new', 301);
        $this->assertSame(301, $res->status());
        $this->assertSame('/new', $res->header('location'));
    }

    #[Test]
    public function immutability_chain(): void
    {
        $a = new Response();
        $b = $a->withStatus(201)->withHeader('X-Test', 'yes')->withBody('done');

        $this->assertSame(200, $a->status());
        $this->assertSame('', $a->body());
        $this->assertSame(201, $b->status());
        $this->assertSame('yes', $b->header('x-test'));
        $this->assertSame('done', $b->body());
    }
}
