<?php

declare(strict_types=1);

namespace Karhu\Tests\Http;

use Karhu\App;
use Karhu\Attributes\Route;
use Karhu\Error\ExceptionHandler;
use Karhu\Http\DefaultErrorHandler;
use Karhu\Http\ErrorHandler;
use Karhu\Http\NotFoundException;
use Karhu\Http\Request;
use Karhu\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/* --- Stub controllers --- */

// v0.1.4 — a controller that throws NotFoundException so we can test the
// callHandler try/catch path (the same handler must fire whether the route
// was unmatched OR a matched controller threw NFE).
final class StubErrorController
{
    #[Route('/valid-route-that-throws', methods: ['GET'])]
    public function throws(Request $request): Response
    {
        throw new NotFoundException('Widget 42 not in inventory');
    }
}

/* --- Handler stubs --- */

// Custom handler stub that captures its inputs so the tests can assert on
// exactly what App handed to the handler (request path, error slot, context
// array shape). Distinct body ("BRANDED-404-BODY") makes response identity
// unambiguous — no risk of collision with karhu's default "Not Found".
final class RecordingErrorHandler implements ErrorHandler
{
    public ?Request $lastRequest = null;
    public ?\Throwable $lastError = null;

    /** @var array<string, mixed>|null */
    public ?array $lastContext = null;

    public int $callCount = 0;

    public function handle(Request $request, ?\Throwable $error, array $context): Response
    {
        $this->lastRequest = $request;
        $this->lastError = $error;
        $this->lastContext = $context;
        $this->callCount++;
        return (new Response(404))->withBody('BRANDED-404-BODY');
    }
}

// A handler that intentionally throws — verifies the defensive fallback in
// App::resolveErrorHandler routes to DefaultErrorHandler instead of bubbling
// a 500. This models the real-world "Twig template missing / NavContext DB
// error / any other Throwable inside handle()" failure mode.
final class ThrowingErrorHandler implements ErrorHandler
{
    public function handle(Request $request, ?\Throwable $error, array $context): Response
    {
        throw new \RuntimeException('handler exploded');
    }
}

/* --- Tests --- */

final class ErrorHandlerTest extends TestCase
{
    private function createApp(): App
    {
        $app = new App();
        $app->router()->scanControllers([StubErrorController::class]);
        return $app;
    }

    // --- The default-handler path (istrbuddy safety) ---

    #[Test]
    public function default_handler_returns_bland_404_for_unknown_path(): void
    {
        // No handler bound → App falls back to DefaultErrorHandler which
        // returns 'Not Found' byte-for-byte matching pre-v0.1.4 behavior.
        // This is the istrbuddy safety contract — a karhu consumer that
        // never binds ErrorHandler sees zero behavior change on upgrade.
        $app = $this->createApp();
        $res = $app->handle(new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/unknown',
        ]));

        $this->assertSame(404, $res->status());
        $this->assertSame('Not Found', $res->body());
        $this->assertSame('no-store', $res->header('cache-control'));
    }

    #[Test]
    public function default_handler_status_appropriate_body(): void
    {
        // DefaultErrorHandler dispatches body by $context['status'] so
        // future 405/500 wiring (when those seams get added) reuses the
        // same handler without a re-refactor.
        $handler = new DefaultErrorHandler();
        $request = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/x']);

        $this->assertSame('Not Found', $handler->handle($request, null, ['status' => 404])->body());
        $this->assertSame('Method Not Allowed', $handler->handle($request, null, ['status' => 405])->body());
        $this->assertSame('Error', $handler->handle($request, null, ['status' => 500])->body());
    }

    // --- Custom-handler wiring ---

    #[Test]
    public function bound_handler_receives_unmatched_route_dispatch(): void
    {
        // App::dispatch line 99-101 rewrite: unmatched route resolves the
        // container-bound handler and calls handle(request, null, ['status'
        // => 404]). $error is null (no exception thrown — just no route
        // matched); status hints the future 405/500 discriminator.
        $app = $this->createApp();
        $recorder = new RecordingErrorHandler();
        $app->container()->set(ErrorHandler::class, $recorder);

        $res = $app->handle(new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/never-registered',
        ]));

        $this->assertSame(404, $res->status());
        $this->assertSame('BRANDED-404-BODY', $res->body());
        $this->assertSame(1, $recorder->callCount);
        $this->assertNull($recorder->lastError, 'unmatched-route path passes null in $error slot');
        $this->assertSame(['status' => 404], $recorder->lastContext);
        $this->assertSame('/never-registered', $recorder->lastRequest?->path());
    }

    #[Test]
    public function bound_handler_receives_thrown_notfound_exception(): void
    {
        // App::callHandler try/catch — when a matched controller throws
        // NotFoundException, the SAME handler fires with $error populated.
        // This is the "resource-by-id not found" idiomatic path — the
        // controller can throw instead of hand-crafting a 404 response.
        $app = $this->createApp();
        $recorder = new RecordingErrorHandler();
        $app->container()->set(ErrorHandler::class, $recorder);

        $res = $app->handle(new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/valid-route-that-throws',
        ]));

        $this->assertSame(404, $res->status());
        $this->assertSame(1, $recorder->callCount);
        $this->assertInstanceOf(NotFoundException::class, $recorder->lastError);
        $this->assertSame('Widget 42 not in inventory', $recorder->lastError?->getMessage());
        $this->assertSame(['status' => 404], $recorder->lastContext);
    }

    // --- Defensive fallback ---

    #[Test]
    public function throwing_handler_falls_back_to_default(): void
    {
        // Belt-and-braces: if the bound handler itself throws (missing
        // template, DB blip in a nav lookup, etc.) the fallback in
        // App::resolveErrorHandler MUST still produce a 404. Today's
        // bland 'Not Found' fallback always works; v0.1.4 must not
        // regress this guarantee even when the branded handler explodes.
        $app = $this->createApp();
        $app->container()->set(ErrorHandler::class, new ThrowingErrorHandler());

        $res = $app->handle(new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/unknown',
        ]));

        $this->assertSame(404, $res->status());
        $this->assertSame('Not Found', $res->body());
        $this->assertSame('no-store', $res->header('cache-control'));
    }

    #[Test]
    public function throwing_handler_falls_back_on_controller_throw_path(): void
    {
        // Same defensive posture on the callHandler NFE-catch path.
        $app = $this->createApp();
        $app->container()->set(ErrorHandler::class, new ThrowingErrorHandler());

        $res = $app->handle(new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/valid-route-that-throws',
        ]));

        $this->assertSame(404, $res->status());
        $this->assertSame('Not Found', $res->body());
    }

    // --- prefersJson (Request helper) ---

    #[Test]
    public function prefers_json_matches_csrf_deny_semantics(): void
    {
        // The prefersJson helper mirrors Csrf::deny at Middleware/Csrf.php:121
        // exactly — "accepts JSON AND does not accept HTML". This is the
        // pragmatic content-negotiation short-cut used consistently across
        // karhu; the naive str_contains('application/json') would misroute
        // browsers whose default Accept header includes */* + text/html.
        $this->assertTrue(
            $this->requestWithAccept('application/json')->prefersJson(),
            'curl -H "Accept: application/json" prefers JSON'
        );

        $this->assertFalse(
            $this->requestWithAccept('text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8')
                 ->prefersJson(),
            'browser default Accept prefers HTML'
        );

        $this->assertFalse(
            $this->requestWithAccept('*/*')->prefersJson(),
            '*/* means client accepts anything — do not assume JSON'
        );

        $this->assertFalse(
            $this->requestWithAccept('')->prefersJson(),
            'empty Accept defaults to */* — do not assume JSON'
        );

        $this->assertFalse(
            $this->requestWithAccept('text/html')->prefersJson(),
            'text/html-only prefers HTML'
        );

        $this->assertTrue(
            $this->requestWithAccept('application/json, text/plain')->prefersJson(),
            'JSON present + no HTML → prefers JSON'
        );
    }

    // --- ExceptionHandler CLI-fallback map ---

    #[Test]
    public function exception_handler_maps_notfound_exception_to_404(): void
    {
        // Defense-in-depth: web dispatch catches NFE in App::callHandler
        // before it ever reaches ExceptionHandler. But CLI commands + queue
        // workers throw exceptions outside the request pipeline, and
        // ExceptionHandler (registered via set_exception_handler) is the
        // catch-all. Without this map entry, a background job throwing
        // NotFoundException would be rendered as a 500 — misleading.
        $handler = new ExceptionHandler(devMode: false);
        $response = $handler->handle(new NotFoundException('missing'), null);

        $this->assertSame(404, $response->status());
    }

    // --- Helpers ---

    private function requestWithAccept(string $accept): Request
    {
        return new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
            headers: ['accept' => $accept],
        );
    }
}
