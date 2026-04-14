<?php

declare(strict_types=1);

namespace Karhu\Tests\Http;

use Karhu\Http\AbstractResourceController;
use Karhu\Http\Request;
use Karhu\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StubItemController extends AbstractResourceController
{
    protected function index(Request $request): Response
    {
        return $this->respond($request, ['items' => [1, 2, 3]], '<ul><li>1</li></ul>');
    }

    protected function show(Request $request, string $id): Response
    {
        return $this->respond($request, ['id' => $id], "<p>Item {$id}</p>");
    }

    protected function create(Request $request): Response
    {
        return (new Response())->json(['created' => true], 201);
    }

    protected function delete(Request $request, string $id): Response
    {
        return (new Response(204));
    }
}

final class AbstractResourceControllerTest extends TestCase
{
    private StubItemController $ctrl;

    protected function setUp(): void
    {
        $this->ctrl = new StubItemController();
    }

    #[Test]
    public function get_without_id_calls_index(): void
    {
        $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/items']);
        $res = $this->ctrl->dispatch($req);
        $this->assertSame(200, $res->status());
    }

    #[Test]
    public function get_with_id_calls_show(): void
    {
        $req = (new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/items/42']))
            ->withRouteParams(['id' => '42']);
        $res = $this->ctrl->dispatch($req);
        $this->assertSame(200, $res->status());
    }

    #[Test]
    public function post_calls_create(): void
    {
        $req = new Request(server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/items']);
        $res = $this->ctrl->dispatch($req);
        $this->assertSame(201, $res->status());
    }

    #[Test]
    public function delete_with_id(): void
    {
        $req = (new Request(server: ['REQUEST_METHOD' => 'DELETE', 'REQUEST_URI' => '/items/5']))
            ->withRouteParams(['id' => '5']);
        $res = $this->ctrl->dispatch($req);
        $this->assertSame(204, $res->status());
    }

    #[Test]
    public function respond_json_when_accepts_json(): void
    {
        $req = new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/items',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $res = $this->ctrl->dispatch($req);
        $this->assertSame('application/json', $res->header('content-type'));
        $this->assertStringContainsString('"items"', $res->body());
    }

    #[Test]
    public function respond_html_when_accepts_html(): void
    {
        $req = new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/items',
            'HTTP_ACCEPT' => 'text/html',
        ]);
        $res = $this->ctrl->dispatch($req);
        $this->assertStringContainsString('text/html', $res->header('content-type'));
        $this->assertStringContainsString('<ul>', $res->body());
    }

    #[Test]
    public function unimplemented_update_returns_405(): void
    {
        $req = (new Request(server: ['REQUEST_METHOD' => 'PUT', 'REQUEST_URI' => '/items/1']))
            ->withRouteParams(['id' => '1']);
        $res = $this->ctrl->dispatch($req);
        $this->assertSame(405, $res->status());
    }
}
