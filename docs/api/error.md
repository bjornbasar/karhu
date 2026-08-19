# `Karhu\Error`

The last-resort exception net. This namespace catches what escapes the request pipeline; for the
404 branding seam that runs *inside* dispatch, see [`Karhu\Http\ErrorHandler`](http.md#errorhandler)
and the [Error Handling guide](../errors.md).

---

## `ExceptionHandler`

[`src/Error/ExceptionHandler.php`](https://github.com/bjornbasar/karhu/blob/main/src/Error/ExceptionHandler.php)

Converts any `Throwable` into a content-negotiated response, and logs it.

```php
public function __construct(?bool $devMode = null)
```

`$devMode` defaults to `getenv('APP_ENV') === 'local'`. Pass `true`/`false` to force it, which is
what tests do.

| Method | Returns | Description |
|---|---|---|
| `handle(\Throwable $e, ?Request $request = null)` | `Response` | Log the throwable and build a response for it. |
| `register()` | `void` | Install as PHP's global exception **and** error handler. |

### `register()` installs two handlers

- `set_exception_handler()` — builds a `Request` from globals (suppressing any failure, since we
  are already in error handling), renders, and **emits** the response.
- `set_error_handler()` — promotes every PHP warning and notice into an `ErrorException`, so they
  surface as real errors instead of being printed into the page.

Call it before constructing the `App`, so failures during boot are caught too:

```php
$exceptions = new Karhu\Error\ExceptionHandler();
$exceptions->register();

$app = new Karhu\App();
$app->run();
```

### Status mapping

| Throwable | Status |
|---|---|
| [`ForbiddenException`](#forbiddenexception) | 403 |
| [`Karhu\Http\NotFoundException`](http.md#notfoundexception) | 404 |
| `InvalidArgumentException` | 400 |
| everything else | 500 |

`NotFoundException` is mapped here as defence in depth. `App::callHandler()` catches it first
during a web request, so this branch mostly matters for CLI commands and queue workers — without
it, a background job would report a misleading 500.

### Content negotiation

JSON is chosen only when the request accepts `application/json` **and not** `text/html` — the
same rule as `Request::prefersJson()`. A browser sending `Accept: */*` gets the HTML page.

- **JSON** — RFC 7807 `application/problem+json`, always carrying `type`, `title` and `status`.
  In dev mode it also carries `detail`, `exception`, `file` and `trace`.
- **HTML** — a full stack trace in dev; in production just the status and its title.
- **No `Request`** — passing `null` yields the HTML branch.

Every call logs one line to stderr first, regardless of format:

```
[2026-08-19 05:34:57] RuntimeException: something broke in /app/src/Foo.php:42
```

!!! warning "`register()` emits directly"
    The registered closure calls `emit()` itself, so it writes headers and body to the SAPI. Do
    not also emit the response you get back from a manual `handle()` call.

---

## `ForbiddenException`

[`src/Error/ForbiddenException.php`](https://github.com/bjornbasar/karhu/blob/main/src/Error/ForbiddenException.php)

`final class ForbiddenException extends \RuntimeException`

```php
public function __construct(
    string $message = 'Forbidden',
    public readonly ?string $redirectTo = null,
)
```

| Property | Type | Description |
|---|---|---|
| `$redirectTo` | `?string` | When set, `ExceptionHandler` returns a **302** to this URL instead of a 403. |

The redirect form exists for stale-session recovery. Someone removed from a household mid-session
is better served by being sent to the setup page than by a 403 wall:

```php
throw new ForbiddenException('stale session', redirectTo: '/household/setup');
```

!!! note "The redirect is honoured by `ExceptionHandler` only"
    It short-circuits at the top of `handle()`, before status mapping. Catching the exception
    yourself means handling `$redirectTo` yourself.
