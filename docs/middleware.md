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

Preflight OPTIONS auto-handled. Default: all origins allowed.

### RequireRole

```php
use Karhu\Middleware\RequireRole;

$app->pipe(RequireRole::for($rbac, ['admin', 'editor']));
```

Returns 401 if not logged in, 403 if missing required role. Reads username from session.
