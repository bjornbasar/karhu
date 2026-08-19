# Sessions & Cookies

karhu wraps PHP's native session and cookie handling with secure defaults, rather than
implementing its own storage.

API detail: [`Session`](api/middleware.md#session), [`Cookie`](api/http.md#cookie).

## Sessions

Pipe the middleware and the session is started for every request:

```php
$app->pipe(new Karhu\Middleware\Session());
```

Then read and write through the statics, anywhere:

```php
use Karhu\Middleware\Session;

Session::set('username', 'ada');
Session::get('username');            // 'ada'
Session::get('theme', 'light');      // default when absent
Session::has('username');            // true
Session::forget('username');
Session::destroy();                  // ends the session, clears $_SESSION
```

### Cookie parameters

The defaults are already the secure ones:

| Parameter | Default | |
|---|---|---|
| `httponly` | `true` | JavaScript cannot read the cookie |
| `samesite` | `Lax` | blocks cross-site POST, keeps normal navigation working |
| `secure` | auto | set when the request is HTTPS |
| `path` | `/` | |
| `lifetime` | `0` | expires when the browser closes |

Override at construction:

```php
new Session([
    'lifetime' => 3600,
    'samesite' => 'Strict',
    'domain' => '.example.com',
]);
```

`secure` is auto-detected from `X-Forwarded-Proto: https` **or** `$_SERVER['HTTPS'] === 'on'` —
the first is what matters behind a reverse proxy, which is the usual deployment. Setting `secure`
explicitly disables the detection.

!!! warning "Behind a proxy, forward the protocol header"
    If your reverse proxy does not send `X-Forwarded-Proto`, karhu sees plain HTTP and issues the
    session cookie **without** the `Secure` flag, even though the site is HTTPS.

### Regenerate on login

```php
if ($account = $rbac->authenticate($username, $password, $hasher)) {
    Session::regenerate();                       // first
    Session::set('username', $account['username']);
}
```

`regenerate()` issues a new session id and keeps the data. Skipping it leaves you open to session
fixation: an attacker who plants a session id before login still holds a valid session after it.

It also rotates the CSRF token, since the token lives in the session — which is the correct
behaviour on a privilege change.

!!! note "`destroy()` on logout, not `forget()`"
    `Session::forget('username')` removes one key and leaves the session alive. `destroy()` ends
    it and clears `$_SESSION`.

---

## Cookies

For anything outside the session — a theme preference, a dismissed banner:

```php
use Karhu\Http\Cookie;

Cookie::set('theme', 'dark', ['expires' => time() + 86400 * 30]);
Cookie::get('theme', 'light');
Cookie::has('theme');
Cookie::delete('theme');
```

Same defaults as sessions: `httponly`, `SameSite=Lax`, `path=/`, and `secure` auto-detected.

### Options

| Key | Default | Notes |
|---|---|---|
| `expires` | `0` | Unix timestamp. `0` = session cookie |
| `path` | `'/'` | |
| `domain` | `''` | |
| `secure` | auto | HTTPS-only when detected |
| `httponly` | `true` | |
| `samesite` | `'Lax'` | normalised to `Lax`/`None`/`Strict` |

`expires` is an **absolute timestamp**, not a duration — `time() + 3600`, not `3600`.

!!! warning "`delete()` must match `path` and `domain`"
    A cookie is identified by name *plus* path and domain. Setting one on `/admin` and deleting it
    with the default `/` leaves the original in place, and the value keeps coming back.

    ```php
    Cookie::set('flash', 'saved', ['path' => '/admin']);
    Cookie::delete('flash', ['path' => '/admin']);   // must repeat the path
    ```

!!! warning "`SameSite=None` requires `Secure`"
    Browsers reject `SameSite=None` without the `Secure` flag. Set both, and only over HTTPS.

### Reads are from `$_COOKIE`

`Cookie::get()` reads the cookies the browser **sent**, so a value you just `set()` is not
readable until the next request. `delete()` does unset the local `$_COOKIE` entry, so `has()` is
correct immediately after it.

---

## How this interacts with CSRF

[`Csrf`](api/middleware.md#csrf) stores its token in the session when one is active, and falls
back to a double-submit cookie when there is none. So `Session` must be piped **before** `Csrf`:

```php
$app->pipe(new Session())    // 1
    ->pipe(new Csrf());      // 2 — needs the session to already exist
```

Reversed, the token silently lands in a cookie instead of the session, which still works but is
weaker than intended.
