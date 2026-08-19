# Error Handling

karhu has **two** error seams, and they catch different things. Picking the wrong one is the
most common source of confusion, so start here:

| | `Karhu\Http\ErrorHandler` | `Karhu\Error\ExceptionHandler` |
|---|---|---|
| **Catches** | 404s discovered *inside* dispatch | any uncaught `Throwable` |
| **Invoked by** | `Karhu\App` during dispatch | `set_exception_handler()` |
| **Has container access** | Yes | **No** — it is constructed before `App` |
| **Use it to** | render a branded 404 page | be the last-resort net for 500s |
| **Applies to** | web requests | web requests, CLI commands, queue workers |

In short: **`ErrorHandler` is the branding hook, `ExceptionHandler` is the safety net.** Most
apps bind the first and register the second.

---

## `ErrorHandler` — branded 404s

Added in v0.1.4. `App` calls it in two situations, both of which mean *"the thing you asked for
does not exist"*:

1. the router matched **no route**, and
2. a matched controller threw `Karhu\Http\NotFoundException`.

Routing both through one interface is the point: the "not found" body lives in one place instead
of being duplicated across every controller.

### Throwing from a controller

```php
use Karhu\Http\NotFoundException;

#[Route('/widgets/{id}')]
public function show(Request $request): Response
{
    $widget = $this->widgets->find($request->routeParams()['id']);

    if ($widget === null) {
        throw new NotFoundException('widget not found');
    }

    return (new Response())->json($widget);
}
```

### Binding your own

Implement the interface and bind it on `Karhu\Http\ErrorHandler`:

```php
use Karhu\Http\ErrorHandler;
use Karhu\Http\Request;
use Karhu\Http\Response;

final class BrandedErrorHandler implements ErrorHandler
{
    public function __construct(private Twig\Environment $twig) {}

    public function handle(Request $request, ?\Throwable $error, array $context): Response
    {
        $status = $context['status'];

        // $request->prefersJson() is the negotiation helper — API clients get
        // JSON, browsers get the template.
        if ($request->prefersJson()) {
            return (new Response($status))->json(['error' => 'Not Found']);
        }

        return (new Response($status))
            ->withHeader('Content-Type', 'text/html; charset=UTF-8')
            ->withBody($this->twig->render('errors/404.html.twig'));
    }
}

// In bootstrap:
$app->container()->set(ErrorHandler::class, new BrandedErrorHandler($twig));
```

### The `handle()` contract

```php
public function handle(Request $request, ?\Throwable $error, array $context): Response;
```

| Parameter | Meaning |
|---|---|
| `$request` | The incoming request — use it for content negotiation and session/nav lookups |
| `$error` | The `NotFoundException` on the controller-threw path (its message may carry a hint like `"widget 42 not found"`); **`null`** when the router simply matched nothing |
| `$context` | At minimum `['status' => int]`. Reserved for future keys such as `allowed_methods` |

!!! warning "Do not leak `$error->getMessage()` into a production page"
    On the controller-threw path the message is developer-facing. Render it in dev only.

### A broken handler still returns a 404

`App::handleNotFound()` wraps the bound handler in a `try`/`catch`. If your handler throws — a
missing template, a database failure in a nav lookup — karhu falls back to `DefaultErrorHandler`
and still serves a 404 rather than cascading into a 500.

```php
try {
    return $handler->handle($request, $error, ['status' => 404]);
} catch (\Throwable) {
    return (new DefaultErrorHandler())->handle($request, $error, ['status' => 404]);
}
```

### `DefaultErrorHandler` — the fallback

Used when nothing is bound. It maps the status to a plaintext body (`404` → `Not Found`,
`405` → `Method Not Allowed`, `400` → `Bad Request`, `403` → `Forbidden`, anything else →
`Error`) and sets `Cache-Control: no-store`, so adding a route later is visible immediately
rather than after a CDN TTL expires.

Its bodies are byte-for-byte the pre-v0.1.4 hard-coded strings, so **upgrading changes nothing
until you bind your own handler**.

!!! note "Why `ErrorHandler` is an interface, not an abstract class"
    `Container::has()` falls back to `class_exists()`. An abstract class would make `has()`
    return `true` even with no binding, and `get()` would then fail because abstract classes
    cannot be instantiated. `class_exists()` is `false` for interfaces, so `has()` correctly
    reports "unbound" and `App` falls through to `DefaultErrorHandler`.

### 405 is not routed through this seam

A method mismatch is still handled inline in `App::dispatch()`, returning `405` with an `Allow`
header. The `ErrorHandler` seam is 404-first; the `$context` array exists so 405 can be unified
later without a breaking change.

---

## `ExceptionHandler` — the safety net

Converts any uncaught throwable into a content-negotiated response, and logs it to stderr.

```php
// public/index.php, before $app->run():
$handler = new \Karhu\Error\ExceptionHandler();
$handler->register();
```

`register()` installs **both** `set_exception_handler()` and `set_error_handler()` — the latter
promotes PHP warnings and notices into `ErrorException`, so they surface instead of being
silently printed.

### Behaviour

- **JSON clients** — RFC 7807 `application/problem+json`
- **Browsers** — an HTML error page
- **Dev mode** (`APP_ENV=local`, or pass `new ExceptionHandler(true)`) — full stack trace in both
- **Production** — status and title only, no internals
- **Always** — logged to stderr with a timestamp

Negotiation is deliberately narrow: JSON is chosen only when the client accepts
`application/json` **and not** `text/html`. A browser sending `Accept: */*` gets the HTML page.

### Status code mapping

| Exception | Status |
|---|---|
| `Karhu\Error\ForbiddenException` | 403 Forbidden |
| `Karhu\Http\NotFoundException` | 404 Not Found |
| `InvalidArgumentException` | 400 Bad Request |
| Everything else | 500 Internal Server Error |

`NotFoundException` is mapped here as defence in depth. Web dispatch catches it in
`App::callHandler()` first, so this branch is rarely hit in-request — but without it a queue
worker or CLI command throwing `NotFoundException` would report a misleading 500.

### `ForbiddenException` can redirect

If constructed with a `redirectTo` URL it short-circuits to a 302 instead of rendering a 403 —
used for stale-session recovery, where bouncing someone to a setup page beats showing them a
wall.

```php
throw new ForbiddenException('stale session', redirectTo: '/household/setup');
```

### Example problem+json (dev mode)

```json
{
    "type": "about:blank",
    "title": "Internal Server Error",
    "status": 500,
    "detail": "Call to undefined method ...",
    "exception": "Error",
    "file": "/app/src/Controllers/BrokenController.php:42",
    "trace": ["..."]
}
```

In production only `type`, `title` and `status` are present.

---

## Wiring both together

```php
// public/index.php
$exceptions = new \Karhu\Error\ExceptionHandler();
$exceptions->register();                                   // safety net first

$app = new \Karhu\App();
$app->container()->set(\Karhu\Http\ErrorHandler::class, new BrandedErrorHandler($twig));
$app->run();                                               // branded 404s
```

Register the safety net **before** constructing the app, so a failure during boot is still
caught and rendered.

## See also

- [API reference — `Karhu\Http`](api/http.md) for `ErrorHandler`, `DefaultErrorHandler`, `NotFoundException`
- [API reference — `Karhu\Error`](api/error.md) for `ExceptionHandler`, `ForbiddenException`
