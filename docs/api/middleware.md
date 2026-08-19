# `Karhu\Middleware`

Four middleware ship with karhu. Each is an invokable object matching the pipeline's
`fn(Request, callable $next): Response` shape, so `$app->pipe(new Session())` is all the wiring
needed.

Order matters. The recommended stack:

```php
$app->pipe(new Cors($corsConfig))   // 1. answer preflights before anything else
    ->pipe(new Session())            // 2. session must exist before CSRF or RequireRole
    ->pipe(new Csrf())               // 3. reject forged writes
    ->pipe(RequireRole::for($rbac, ['admin']));  // 4. authorise last
```

---

## `Session`

[`src/Middleware/Session.php`](https://github.com/bjornbasar/karhu/blob/main/src/Middleware/Session.php)

Starts a native PHP session with secure cookie parameters, then hands off.

```php
public function __construct(array $cookieParams = [])
```

Defaults, merged with whatever you pass: `lifetime => 0`, `path => '/'`, `domain => ''`,
`secure => false`, `httponly => true`, `samesite => 'Lax'`.

| Method | Returns | Description |
|---|---|---|
| `__invoke(Request $request, callable $next)` | `Response` | Start the session, then call `$next`. |
| `start(Request $request)` | `void` | Start it directly. No-op if a session is already active. |
| `static regenerate()` | `void` | New session id, old data kept. **Call after login.** |
| `static get(string $key, mixed $default = null)` | `mixed` | Read a value. |
| `static set(string $key, mixed $value)` | `void` | Write a value. |
| `static forget(string $key)` | `void` | Remove one key. |
| `static has(string $key)` | `bool` | Whether the key is set. |
| `static destroy()` | `void` | Destroy the session and clear `$_SESSION`. |

`secure` is **auto-detected** when you do not set it: `X-Forwarded-Proto: https` (the reverse-proxy
case) or `$_SERVER['HTTPS'] === 'on'`. `samesite` is normalised to `Lax`, `None` or `Strict`.

!!! warning "Regenerate the session id on login"
    Without `Session::regenerate()` after authenticating, an attacker who fixed the id before
    login still holds a valid session. It also rotates the CSRF token, since that lives in the
    session.

```php
if ($user = $rbac->authenticate($username, $password, $hasher)) {
    Session::regenerate();               // first
    Session::set('username', $user['username']);
}
```

---

## `Csrf`

[`src/Middleware/Csrf.php`](https://github.com/bjornbasar/karhu/blob/main/src/Middleware/Csrf.php)

Session-backed CSRF protection with a double-submit cookie fallback. No constructor.

| Method | Returns | Description |
|---|---|---|
| `__invoke(Request $request, callable $next)` | `Response` | Verify the token; `403` on mismatch. |
| `static token()` | `string` | The current token, generating one if absent. |
| `static regenerate()` | `string` | Force a new token and store it. |
| `static field()` | `string` | A ready-made `<input type="hidden">`, HTML-escaped. |

`GET`, `HEAD` and `OPTIONS` bypass the check (safe methods, RFC 9110). Everything else must
present a token, read in this order:

1. the `X-CSRF-Token` header — for `fetch`/XHR clients
2. the `_csrf_token` POST field

Comparison uses `hash_equals()`. On failure the response is `403`, as problem-JSON when
`prefersJson()` is true and plaintext otherwise.

!!! note "The token does not rotate on every POST"
    It is session-lifetime. Per-request rotation breaks multi-tab workflows: tab 1 submits, tab 2's
    token goes stale, and its next POST 403s. The token changes when the session rotates
    (`Session::regenerate()`) or when you explicitly call `Csrf::regenerate()` before a sensitive
    action.

```html
<form method="post" action="/issues">
    <?= Karhu\Middleware\Csrf::field() ?>
    <input name="title">
</form>
```

```js
fetch('/issues', {
  method: 'POST',
  headers: { 'X-CSRF-Token': token, 'Content-Type': 'application/json' },
  body: JSON.stringify({ title }),
});
```

---

## `Cors`

[`src/Middleware/Cors.php`](https://github.com/bjornbasar/karhu/blob/main/src/Middleware/Cors.php)

Handles preflight `OPTIONS` requests and decorates real responses with CORS headers.

```php
public function __construct(array $config = [])
```

| Key | Default | Description |
|---|---|---|
| `origins` | `[]` | Allowed origins. `[]` **or** `['*']` allows any. |
| `methods` | `['GET', 'POST', 'PUT', 'DELETE']` | `Access-Control-Allow-Methods`. |
| `headers` | `['Content-Type', 'Authorization', 'Accept', 'X-Requested-With', 'X-CSRF-Token']` | `Access-Control-Allow-Headers`. |
| `credentials` | `false` | Whether to send `Access-Control-Allow-Credentials`. |
| `max_age` | `86400` | Preflight cache seconds. |

| Method | Returns | Description |
|---|---|---|
| `__invoke(Request $request, callable $next)` | `Response` | Preflight, decorate, or pass through. |

Behaviour: a request with **no `Origin` header** passes straight through — it is not a CORS
request. A disallowed origin also passes through, simply without CORS headers, so the browser
enforces the block. An allowed `OPTIONS` returns `204` immediately, **before** routing. An allowed
real request gets `Access-Control-Allow-Origin` plus `Vary: Origin`.

!!! warning "The default is wide open"
    `origins => []` means *allow any origin*, not *allow none*. Set it explicitly in production,
    and note that `*` with `credentials => true` is rejected by browsers.

---

## `RequireRole`

[`src/Middleware/RequireRole.php`](https://github.com/bjornbasar/karhu/blob/main/src/Middleware/RequireRole.php)

Gates a pipeline on RBAC roles.

```php
public function __construct(
    private readonly Rbac $rbac,
    private readonly array $roles,
    private readonly string $sessionKey = 'username',
)
```

| Method | Returns | Description |
|---|---|---|
| `static for(Rbac $rbac, array $roles)` | `callable` | Build a middleware callable in one expression. |
| `__invoke(Request $request, callable $next)` | `Response` | `401` if nobody is logged in, `403` without a required role. |

The check passes when the user has **any** of `$roles` (`Rbac::hasAnyRole()`), not all. The
username is read from the session under `$sessionKey`, so [`Session`](#session) must be piped
first. Denials are content-negotiated the same way as `Csrf`.

```php
$app->pipe(RequireRole::for($rbac, ['admin', 'editor']));
```

`for()` uses the default session key. Construct the class directly if you store the username
elsewhere:

```php
$app->pipe(new RequireRole($rbac, ['admin'], sessionKey: 'user_login'));
```

!!! note "This gates the whole pipeline, not one route"
    Piped middleware runs for every request. For per-route gating, apply the check inside the
    controller, or build a separate `App` for the protected route group.
