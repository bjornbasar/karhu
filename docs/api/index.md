# API Reference

Every public class, interface, method and function in karhu — **38 types, 134 public methods**.

The whole framework is about 3,100 lines. If a page here is ambiguous, reading the source is a
realistic option, so each class links to its file.

## How to read these pages

Each class is documented in one shape:

- what it is and when you would reach for it
- its constructor signature
- a table of **every** public method — signature, what it returns, what it throws
- a worked example

Method signatures are written exactly as PHP reports them. `self` as a return type always means
**a new instance** — `Request` and `Response` are immutable, and their `with*()` methods clone.

!!! info "This reference is checked, not hand-maintained"
    `composer docs-check` reflects over `src/` and fails if a public method is missing from these
    pages, or if a signature here no longer exists. It runs in CI and gates the docs deploy, so
    the reference cannot silently drift from the code.

---

## By namespace

| Namespace | Contents |
|---|---|
| [`Karhu`](app.md) | `App` — the front controller |
| [`Karhu\Http`](http.md) | `Request`, `Response`, `Router`, `RouteResult`, `MiddlewarePipeline`, `Cookie`, `Validation`, `AbstractResourceController`, `ErrorHandler`, `DefaultErrorHandler`, `NotFoundException` |
| [`Karhu\Container`](container.md) | `Container`, `ContainerInterface`, `ContainerException`, `NotFoundException` |
| [`Karhu\Attributes`](attributes.md) | `Route`, `Command`, and the six validation attributes |
| [`Karhu\Middleware`](middleware.md) | `Session`, `Csrf`, `Cors`, `RequireRole` |
| [`Karhu\Auth`](auth.md) | `PasswordHasher`, `Rbac`, `UserRepositoryInterface` |
| [`Karhu\Config`](config.md) | `Config` |
| [`Karhu\Cli`](cli.md) | `CommandDispatcher`, `Commands\RouteCacheCommand` |
| [`Karhu\Error`](error.md) | `ExceptionHandler`, `ForbiddenException` |
| [`Karhu\Log`](log.md) | `LoggerInterface`, `StderrLogger` |

---

## Full class index

| Class | Kind | Namespace page |
|---|---|---|
| `Karhu\App` | final class | [App](app.md#karhuapp) |
| `Karhu\Attributes\Command` | attribute | [Attributes](attributes.md#command) |
| `Karhu\Attributes\Email` | attribute | [Attributes](attributes.md#email) |
| `Karhu\Attributes\In` | attribute | [Attributes](attributes.md#in) |
| `Karhu\Attributes\NumericRange` | attribute | [Attributes](attributes.md#numericrange) |
| `Karhu\Attributes\Regex` | attribute | [Attributes](attributes.md#regex) |
| `Karhu\Attributes\Required` | attribute | [Attributes](attributes.md#required) |
| `Karhu\Attributes\Route` | attribute | [Attributes](attributes.md#route) |
| `Karhu\Attributes\StringLength` | attribute | [Attributes](attributes.md#stringlength) |
| `Karhu\Auth\PasswordHasher` | final class | [Auth](auth.md#passwordhasher) |
| `Karhu\Auth\Rbac` | final class | [Auth](auth.md#rbac) |
| `Karhu\Auth\UserRepositoryInterface` | interface | [Auth](auth.md#userrepositoryinterface) |
| `Karhu\Cli\CommandDispatcher` | final class | [Cli](cli.md#commanddispatcher) |
| `Karhu\Cli\Commands\RouteCacheCommand` | final class | [Cli](cli.md#routecachecommand) |
| `Karhu\Config\Config` | final class | [Config](config.md#config) |
| `Karhu\Container\Container` | final class | [Container](container.md#container) |
| `Karhu\Container\ContainerException` | class | [Container](container.md#containerexception) |
| `Karhu\Container\ContainerInterface` | interface | [Container](container.md#containerinterface) |
| `Karhu\Container\NotFoundException` | class | [Container](container.md#notfoundexception) |
| `Karhu\Error\ExceptionHandler` | final class | [Error](error.md#exceptionhandler) |
| `Karhu\Error\ForbiddenException` | final class | [Error](error.md#forbiddenexception) |
| `Karhu\Http\AbstractResourceController` | abstract class | [Http](http.md#abstractresourcecontroller) |
| `Karhu\Http\Cookie` | final class | [Http](http.md#cookie) |
| `Karhu\Http\DefaultErrorHandler` | final class | [Http](http.md#defaulterrorhandler) |
| `Karhu\Http\ErrorHandler` | interface | [Http](http.md#errorhandler) |
| `Karhu\Http\MiddlewarePipeline` | final class | [Http](http.md#middlewarepipeline) |
| `Karhu\Http\NotFoundException` | final class | [Http](http.md#notfoundexception) |
| `Karhu\Http\Request` | final class | [Http](http.md#request) |
| `Karhu\Http\Response` | final class | [Http](http.md#response) |
| `Karhu\Http\RouteResult` | final class | [Http](http.md#routeresult) |
| `Karhu\Http\Router` | final class | [Http](http.md#router) |
| `Karhu\Http\Validation` | final class | [Http](http.md#validation) |
| `Karhu\Log\LoggerInterface` | interface | [Log](log.md#loggerinterface) |
| `Karhu\Log\StderrLogger` | final class | [Log](log.md#stderrlogger) |
| `Karhu\Middleware\Cors` | final class | [Middleware](middleware.md#cors) |
| `Karhu\Middleware\Csrf` | final class | [Middleware](middleware.md#csrf) |
| `Karhu\Middleware\RequireRole` | final class | [Middleware](middleware.md#requirerole) |
| `Karhu\Middleware\Session` | final class | [Middleware](middleware.md#session) |

---

## Exception hierarchy

karhu throws few exception types, and they mean distinct things:

```
RuntimeException
├── Karhu\Container\ContainerException      cannot build a service
│   └── Karhu\Container\NotFoundException   no such entry in the container
├── Karhu\Error\ForbiddenException          403 (or 302 with redirectTo)
└── Karhu\Http\NotFoundException            404 — the resource does not exist

InvalidArgumentException                    thrown by Router::urlFor() → 400
```

!!! warning "Two classes named `NotFoundException`"
    `Karhu\Container\NotFoundException` means *"nothing is registered under that id"* — a wiring
    bug. `Karhu\Http\NotFoundException` means *"this URL has no resource"* — a 404 you throw on
    purpose. They are unrelated. Import the one you mean.
