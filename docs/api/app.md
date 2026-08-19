# `Karhu\App`

The front controller. It owns the router, the container and the middleware pipeline, and turns a
`Request` into a `Response`.

---

## `App`

[`src/App.php`](https://github.com/bjornbasar/karhu/blob/main/src/App.php)

```php
public function __construct(?Container $container = null)
```

Pass your own [`Container`](container.md#container) to share one with the rest of your bootstrap;
otherwise `App` creates one. The constructor **self-registers** three services, so they are
always resolvable:

| Id | Instance |
|---|---|
| `Karhu\App::class` | the app itself |
| `Karhu\Http\Router::class` | its router |
| `Karhu\Container\Container::class` | the container itself |

| Method | Returns | Description |
|---|---|---|
| `router()` | `Router` | The router, for registering routes. |
| `container()` | `Container` | The container, for binding services. |
| `pipe(callable $middleware)` | `self` | Append middleware. Chainable. |
| `setBasePath(string $basePath)` | `self` | Delegates to `Router::setBasePath()`. Chainable. |
| `handle(Request $request)` | `Response` | Run pipeline → router → controller and **return** the response. |
| `run(?Request $request = null)` | `void` | `handle()` then `emit()`. Defaults to `Request::fromGlobals()`. |

Use `run()` from `public/index.php` and `handle()` in tests, where you want the `Response` object
rather than output.

## A minimal front controller

```php
<?php
// public/index.php
require __DIR__ . '/../vendor/autoload.php';

$exceptions = new Karhu\Error\ExceptionHandler();
$exceptions->register();

$app = new Karhu\App();
$app->router()->scanControllers(require __DIR__ . '/../config/controllers.php');

$app->pipe(new Karhu\Middleware\Session())
    ->pipe(new Karhu\Middleware\Csrf());

$app->run();
```

## What `handle()` does

1. Runs the middleware pipeline, terminating at the internal `dispatch()`.
2. `dispatch()` calls `Router::match()`.
3. **405** — returns immediately with an `Allow` header. This path does *not* go through
   `ErrorHandler`; the seam is 404-first.
4. **No match** — routes through the bound [`ErrorHandler`](http.md#errorhandler), or
   `DefaultErrorHandler`.
5. **Match** — injects route params into the `Request`, re-registers it in the container under
   `Request::class`, resolves the controller from the container, and invokes the method with the
   request.

!!! note "Handlers receive the `Request`, not unpacked route parameters"
    The controller method is called as `$controller->{$method}($request)`. Read placeholders with
    `$request->routeParams()`.

### Return-value coercion

A handler does not have to return a `Response`:

| Returned | Becomes |
|---|---|
| `Response` | used as-is |
| `string` | `200` with that body |
| `array` | `200` with a JSON body |
| anything else | an empty `200` |

### A broken 404 handler cannot cause a 500

`handleNotFound()` wraps the bound handler in `try`/`catch`. If your handler throws — a missing
template, a failed database lookup — karhu falls back to `DefaultErrorHandler` so a 404 is always
served.

## See also

- [`Karhu\Http`](http.md) — `Request`, `Response`, `Router`
- [Error Handling](../errors.md) — the two error seams
- [Deployment](../deployment.md) — route caching and production wiring
