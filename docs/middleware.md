# Middleware

karhu uses a PSR-15-shape callable middleware pipeline. Each middleware receives a `Request` and a `$next` callable, and returns a `Response`.

## Writing middleware

```php
use Karhu\Http\Request;
use Karhu\Http\Response;

$app->pipe(function (Request $request, callable $next): Response {
    // Before: modify request or short-circuit
    $response = $next($request);
    // After: modify response
    return $response->withHeader('X-Custom', 'value');
});
```

## Execution order

Middleware runs in FIFO order (first piped = first to run). The onion model: each middleware wraps the next, so "after" logic runs in reverse order.

Not calling `$next()` short-circuits everything after it — that is exactly how `Csrf` and
`RequireRole` reject a request.

!!! warning "Middleware wraps routing, not just the controller"
    The pipeline runs *before* a route is matched, so every middleware also sees requests that
    will end in a 404. Anything that assumes a matched route needs to cope with that.

The order that matters in practice:

```php
$app->pipe(new Cors($config))   // 1. answer preflights before anything else
    ->pipe(new Session())        // 2. the session must exist before CSRF reads it
    ->pipe(new Csrf())           // 3. reject forged writes
    ->pipe(RequireRole::for($rbac, ['admin']));   // 4. authorise last
```

`Session` before `Csrf` is load-bearing: reversed, the CSRF token silently falls back to a
double-submit cookie instead of the session.

## Shipped middleware

### Session

```php
$app->pipe(new \Karhu\Middleware\Session());
```

Native PHP sessions with secure defaults: HttpOnly, SameSite=Lax, Secure (auto-detected on HTTPS). Call `Session::regenerate()` after login.

### CSRF

```php
$app->pipe(new \Karhu\Middleware\Csrf());
```

Session-backed signed token. Bypasses GET/HEAD/OPTIONS. In forms: `<?= \Karhu\Middleware\Csrf::field() ?>`. For AJAX: send the `X-CSRF-Token` header.

### CORS

```php
$app->pipe(new \Karhu\Middleware\Cors([
    'origins' => ['https://app.example.com'],
    'methods' => ['GET', 'POST', 'PUT', 'DELETE'],
    'credentials' => true,
    'max_age' => 3600,
]));
```

Preflight OPTIONS requests are answered immediately, before routing.

!!! warning "The default allows every origin"
    `origins => []` means *allow any*, not *allow none*. Set it explicitly in production. Note
    also that browsers reject `*` combined with `credentials => true`.

### RequireRole

```php
use Karhu\Middleware\RequireRole;

$app->pipe(RequireRole::for($rbac, ['admin', 'editor']));
```

Returns 401 if not logged in, 403 if missing required role. Reads username from session.

The check passes when the user holds **any** of the listed roles, not all of them. `for()` uses
the default session key `username`; construct the class directly to change it:

```php
$app->pipe(new RequireRole($rbac, ['admin'], sessionKey: 'user_login'));
```

!!! note "This gates the whole pipeline, not one route"
    Piped middleware runs for every request. For per-route gating, check inside the controller.

## See also

- [API reference — `Karhu\Middleware`](api/middleware.md) — every method and config key
- [Sessions & Cookies](sessions.md) — cookie flags, CSRF interaction
- [Auth](auth.md) — the login flow behind `RequireRole`
