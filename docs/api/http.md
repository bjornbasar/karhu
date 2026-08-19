# `Karhu\Http`

The HTTP layer: the request/response pair, the router, the middleware pipeline, and the
error-handling seam. PSR-7 and PSR-15 in **shape**, not in compliance — karhu implements the
methods controllers actually call and skips the rest
([ADR 0005](../adr/0005-psr7-shape-not-compliance.md)).

---

## `Request`

[`src/Http/Request.php`](https://github.com/bjornbasar/karhu/blob/main/src/Http/Request.php)

An **immutable** value object wrapping one incoming HTTP request. Handlers receive it as their
only argument.

```php
public function __construct(
    array $server = [],
    array $get = [],
    array $post = [],
    string $body = '',
    array $headers = [],
)
```

Prefer `Request::fromGlobals()` in application code. The constructor is public so tests can build
a request without touching superglobals.

| Method | Returns | Description |
|---|---|---|
| `static fromGlobals()` | `self` | Build from `$_SERVER`, `$_GET`, `$_POST` and `php://input`. |
| `method()` | `string` | HTTP method, upper-cased. Defaults to `GET`. |
| `path()` | `string` | Path with the query string stripped. Defaults to `/`. |
| `header(string $name, string $default = '')` | `string` | One header, **case-insensitive**. |
| `headers()` | `array<string,string>` | All headers, keys lower-cased. |
| `query(string $key, string $default = '')` | `string` | One `$_GET` value. |
| `post(string $key, string $default = '')` | `string` | One `$_POST` value. |
| `body()` | `array\|string` | JSON bodies are decoded to an array (lazily, once); anything else returns the raw string. |
| `rawBody()` | `string` | The unparsed body, always. |
| `accepts(string $type)` | `bool` | Whether the `Accept` header contains `$type` **or** `*/*`. |
| `prefersJson()` | `bool` | `accepts('application/json') && !accepts('text/html')`. |
| `routeParams()` | `array<string,string>` | `{placeholder}` values, injected by `App` after matching. |
| `withRouteParams(array $params)` | `self` | A **clone** carrying those route params. |

### Reading route parameters

```php
#[Route('/posts/{postId}/comments/{commentId}')]
public function show(Request $request): Response
{
    ['postId' => $postId, 'commentId' => $commentId] = $request->routeParams();
    // ...
}
```

### `accepts()` is deliberately loose, `prefersJson()` is not

`accepts()` returns `true` for a wildcard `Accept`, so `accepts('application/json')` alone is
`true` for a browser. Use **`prefersJson()`** for content negotiation — it is the rule karhu
applies internally everywhere:

| Client | `Accept` | `prefersJson()` |
|---|---|---|
| `curl -H 'Accept: application/json'` | `application/json` | `true` |
| Browser | `text/html,application/xml;q=0.9,*/*` | `false` |
| `curl` (default) | `*/*` | `false` |
| No header | — | `false` |

---

## `Response`

[`src/Http/Response.php`](https://github.com/bjornbasar/karhu/blob/main/src/Http/Response.php)

An **immutable** response. Every `with*()` method — and `json()` and `redirect()` — returns a
**clone**; none mutate in place.

```php
public function __construct(int $status = 200)
```

| Method | Returns | Description |
|---|---|---|
| `withStatus(int $status)` | `self` | Clone with a new status code. |
| `withHeader(string $name, string $value)` | `self` | Clone with a header set (keys stored lower-cased). |
| `withBody(string $body)` | `self` | Clone with a new body. |
| `json(mixed $data, int $status = 200)` | `self` | Clone with `Content-Type: application/json` and the JSON-encoded payload. **Not static.** |
| `redirect(string $url, int $status = 302)` | `self` | Clone with a `Location` header. |
| `status()` | `int` | The status code. |
| `header(string $name)` | `string` | One header value, or `''`. |
| `body()` | `string` | The body. |
| `emit()` | `void` | Send status line, headers and body to the SAPI. Called by `App::run()`. |

!!! warning "`json()` is an instance method"
    `Response::json([...])` is a static call to an instance method and raises an `Error`. Write
    `(new Response())->json([...])`.

```php
return (new Response())
    ->withHeader('Cache-Control', 'no-store')
    ->json(['ok' => true]);

return (new Response())->redirect('/login');          // 302
return (new Response())->redirect('/moved', 301);     // 301
```

`json()` encodes with `JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES`, so an unencodable payload
raises `JsonException` rather than silently producing `false`.

---

## `Router`

[`src/Http/Router.php`](https://github.com/bjornbasar/karhu/blob/main/src/Http/Router.php)

Compiles route paths to regexes and matches them in registration order. Populated by scanning
`#[Route]` attributes, or explicitly.

| Method | Returns | Description |
|---|---|---|
| `setBasePath(string $basePath)` | `void` | Prefix for sub-directory deployments. Normalised; `/` becomes `''`. |
| `basePath()` | `string` | The current base path. |
| `group(string $prefix, callable $callback)` | `void` | Register routes under a shared prefix. `$callback` receives the router. Nestable. |
| `addRoute(string $path, array $methods, string $handler, ?string $name = null)` | `void` | Register one route. `$handler` is `'Class::method'`. |
| `scanControllers(array $controllers)` | `void` | Reflect over the classes and register every `#[Route]` found on public methods. |
| `match(string $method, string $path)` | [`RouteResult`](#routeresult) | Match a request. |
| `urlFor(string $name, array $params = [])` | `string` | Build a path for a named route, including the base path. |
| `dumpCache()` | `array` | The compiled table, for `route:cache`. |
| `loadCache(array $cache)` | `void` | Replace the table with a cached one, skipping reflection. |
| `routes()` | `array` | The raw compiled table. Mostly for debugging. |

**Throws** — `urlFor()` raises `InvalidArgumentException` when the route name is unknown **or** a
required parameter is missing.

### Matching rules

- Paths are matched **in registration order**; the first match wins.
- `{param}` compiles to `([^/]+)` — it matches one segment and never crosses a `/`.
- A trailing slash is stripped before matching, so `/users/` matches a `/users` route. The root
  `/` is exempt.
- **`HEAD` is implicit for every `GET` route** (RFC 9110), unless `HEAD` is registered explicitly.
- A path that matches with the wrong method yields `405` and a sorted, de-duplicated `Allow` list
  rather than a 404.
- `OPTIONS` against a known path returns the allowed methods **with `OPTIONS` appended**.
- If `basePath` is set and the path does not start with it, the result is `notFound()`.

```php
$app->router()->group('/api/v1', function (Router $r): void {
    $r->addRoute('/users', ['GET'], UserController::class . '::index', 'users.index');
    $r->addRoute('/users/{id}', ['GET'], UserController::class . '::show', 'users.show');
});

$app->router()->urlFor('users.show', ['id' => '42']);   // /api/v1/users/42
```

---

## `RouteResult`

[`src/Http/RouteResult.php`](https://github.com/bjornbasar/karhu/blob/main/src/Http/RouteResult.php)

The outcome of `Router::match()`. The constructor is private — build one via the three named
constructors.

| Method | Returns | Description |
|---|---|---|
| `static found(string $handler, string $method, array $params = [])` | `self` | A match. |
| `static notFound()` | `self` | No route matched the path. |
| `static methodNotAllowed(array $allowedMethods)` | `self` | Path matched, method did not. |
| `isMethodNotAllowed()` | `bool` | `true` when not found **and** `allowedMethods` is non-empty. |

Public readonly properties: `bool $found`, `string $handler`, `string $method`,
`array $params`, `array $allowedMethods`.

---

## `MiddlewarePipeline`

[`src/Http/MiddlewarePipeline.php`](https://github.com/bjornbasar/karhu/blob/main/src/Http/MiddlewarePipeline.php)

A PSR-15-shape pipeline with no external dependencies.

| Method | Returns | Description |
|---|---|---|
| `pipe(callable $middleware)` | `self` | Append middleware. Chainable. |
| `handle(Request $request, callable $handler)` | `Response` | Run the stack, terminating at `$handler`. |

Middleware runs **FIFO** — the first piped runs first, and its `$next` wraps everything after it.
Each middleware is `fn(Request, callable $next): Response`; not calling `$next` short-circuits
the rest of the stack.

```php
$pipeline->pipe($sessionMw)->pipe($csrfMw);   // session runs first, csrf second
```

---

## `AbstractResourceController`

[`src/Http/AbstractResourceController.php`](https://github.com/bjornbasar/karhu/blob/main/src/Http/AbstractResourceController.php)

Base class that dispatches on HTTP verb, so one route entry can serve a whole resource.

| Method | Returns | Description |
|---|---|---|
| `dispatch(Request $request)` | `Response` | Route to the matching action by verb and `{id}` presence. |

Dispatch table:

| Verb | `{id}` | Calls |
|---|---|---|
| `GET` | absent | `index($request)` |
| `GET` | present | `show($request, $id)` |
| `POST` | — | `create($request)` |
| `PUT` | present | `update($request, $id)` |
| `DELETE` | present | `delete($request, $id)` |
| anything else | — | `405 Method Not Allowed` |

The five actions are `protected` and default to `405 Not Implemented`; override the ones you
support. A `protected respond(Request $request, array $data, string $html = '')` helper returns
JSON or HTML by the same negotiation rule as `prefersJson()`.

```php
final class WidgetController extends AbstractResourceController
{
    #[Route('/widgets', methods: ['GET', 'POST'])]
    #[Route('/widgets/{id}', methods: ['GET', 'PUT', 'DELETE'])]
    public function __invoke(Request $request): Response
    {
        return $this->dispatch($request);
    }

    protected function index(Request $request): Response
    {
        return $this->respond($request, ['widgets' => []], '<h1>Widgets</h1>');
    }
}
```

!!! note "`dispatch()` reads `{id}` specifically"
    The parameter must be named `id`. `/widgets/{widgetId}` dispatches to `index()`, not `show()`.

---

## `Validation`

[`src/Http/Validation.php`](https://github.com/bjornbasar/karhu/blob/main/src/Http/Validation.php)

Attribute-driven validation against a DTO class. Scope-fenced at exactly six validators, with no
nested validation and no custom-rule registry — deliberately, to avoid the
validation-library-bloat failure mode.

| Method | Returns | Description |
|---|---|---|
| `static validate(array $data, string $dto)` | `array<string,string>` | Errors keyed by field name. **Empty array means valid.** |

Rules: `#[Required]` runs first and short-circuits the rest for that field. Any field that is
`null` or `''` and *not* required skips every remaining rule, so optional fields validate only
when present. At most one error per field is returned.

See [`Karhu\Attributes`](attributes.md) for the six attributes.

```php
final class CreateIssueDto
{
    #[Required]
    #[StringLength(min: 3, max: 120)]
    public string $title = '';

    #[In(['low', 'normal', 'high'])]
    public string $priority = 'normal';
}

$errors = Validation::validate($request->body(), CreateIssueDto::class);

if ($errors !== []) {
    return (new Response())->json(['errors' => $errors], 422);
}
```

---

## `ErrorHandler`

[`src/Http/ErrorHandler.php`](https://github.com/bjornbasar/karhu/blob/main/src/Http/ErrorHandler.php)

The **branding seam** for 404s, added in v0.1.4. Bind an implementation on this interface and
`App` will use it.

| Method | Returns | Description |
|---|---|---|
| `handle(Request $request, ?Throwable $error, array $context)` | `Response` | Build the error response. `$error` is `null` on the unmatched-route path. `$context` carries at least `['status' => int]`. |

It is an **interface** rather than an abstract class on purpose: `Container::has()` falls back to
`class_exists()`, which is `false` for interfaces — so an unbound `ErrorHandler` correctly
reports as absent and `App` falls through to `DefaultErrorHandler`.

Full guide: [Error Handling](../errors.md).

---

## `DefaultErrorHandler`

[`src/Http/DefaultErrorHandler.php`](https://github.com/bjornbasar/karhu/blob/main/src/Http/DefaultErrorHandler.php)

The fallback used when nothing is bound. Implements [`ErrorHandler`](#errorhandler).

| Method | Returns | Description |
|---|---|---|
| `handle(Request $request, ?Throwable $error, array $context)` | `Response` | Plaintext body chosen by `$context['status']`, plus `Cache-Control: no-store`. |

Bodies: `404` → `Not Found`, `405` → `Method Not Allowed`, `400` → `Bad Request`,
`403` → `Forbidden`, anything else → `Error`. These are byte-for-byte the pre-v0.1.4 hard-coded
strings, so upgrading changes nothing until you bind your own handler.

---

## `NotFoundException`

[`src/Http/NotFoundException.php`](https://github.com/bjornbasar/karhu/blob/main/src/Http/NotFoundException.php)

Throw this from a controller to mean *"this resource does not exist"*. Extends `RuntimeException`.

```php
public function __construct(string $message = 'Not Found', ?\Throwable $previous = null)
```

`App::callHandler()` catches it and routes it through the same [`ErrorHandler`](#errorhandler) as
an unmatched route, so 404 branding lives in one place. If it escapes the request pipeline — a
CLI command, a queue worker — `Karhu\Error\ExceptionHandler` maps it to a 404 as well.

!!! warning "Not the container's `NotFoundException`"
    `Karhu\Container\NotFoundException` is a wiring error, not a 404.

---

## `Cookie`

[`src/Http/Cookie.php`](https://github.com/bjornbasar/karhu/blob/main/src/Http/Cookie.php)

Static helpers over `$_COOKIE` and `setcookie()`, with secure defaults.

| Method | Returns | Description |
|---|---|---|
| `static get(string $name, string $default = '')` | `string` | Read a cookie. |
| `static has(string $name)` | `bool` | Whether the cookie is present. |
| `static set(string $name, string $value, array $options = [])` | `void` | Write a cookie. |
| `static delete(string $name, array $options = [])` | `void` | Expire a cookie and unset it from `$_COOKIE`. |

Defaults: `path=/`, `httponly=true`, `samesite=Lax`, `expires=0` (session cookie), and `secure`
**auto-detected** from `HTTPS=on` or `X-Forwarded-Proto: https` — pass `secure` explicitly to
override. `samesite` is normalised to `Lax`, `None` or `Strict`; anything else becomes `Lax`.

!!! warning "`delete()` must match the original `path` and `domain`"
    Cookies are keyed on name **plus** path and domain. Deleting with different options silently
    leaves the original in place.

```php
Cookie::set('theme', 'dark', ['expires' => time() + 86400 * 30]);
$theme = Cookie::get('theme', 'light');
Cookie::delete('theme');
```
