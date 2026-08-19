# Part 5 — Errors, CLI and production

The app works. This part makes its failures presentable, adds a maintenance command, and gets it
ready to deploy.

## Consistent 404s

Right now "not found" is written twice in `IssueController`, as an inline JSON response. Two
places today, ten later, and they will drift.

karhu has a seam for this. In a controller, throw:

```php
use Karhu\Http\NotFoundException;

protected function show(Request $request, string $id): Response
{
    $issue = $this->store->find((int) $id) ?? throw new NotFoundException("issue {$id} not found");

    return $this->respond($request, $issue);
}

protected function delete(Request $request, string $id): Response
{
    if (!$this->store->delete((int) $id)) {
        throw new NotFoundException("issue {$id} not found");
    }

    return new Response(204);
}
```

!!! warning "Import `Karhu\Http\NotFoundException`"
    `Karhu\Container\NotFoundException` is a container wiring error and surfaces as a 500.

`App` catches it and routes it through the **same** handler as an unmatched URL, so a bad `/issues/99`
and a completely unknown `/nope` produce the same response. Bind one to control it:

`app/JsonErrorHandler.php`:

```php
<?php

declare(strict_types=1);

namespace App;

use Karhu\Http\ErrorHandler;
use Karhu\Http\Request;
use Karhu\Http\Response;

final class JsonErrorHandler implements ErrorHandler
{
    public function handle(Request $request, ?\Throwable $error, array $context): Response
    {
        $status = is_int($context['status'] ?? null) ? $context['status'] : 500;

        if ($request->prefersJson()) {
            return (new Response())->json([
                'type' => 'about:blank',
                'title' => 'Not Found',
                'status' => $status,
                'path' => $request->path(),
            ], $status);
        }

        return (new Response($status))
            ->withHeader('Content-Type', 'text/html; charset=UTF-8')
            ->withBody('<!doctype html><title>Not found</title><h1>404</h1><p>No such page.</p>');
    }
}
```

```php
$app->container()->set(Karhu\Http\ErrorHandler::class, new App\JsonErrorHandler());
```

```bash
curl -s -H 'Accept: application/json' localhost:8080/issues/99
curl -s -H 'Accept: application/json' localhost:8080/nope
```

Both now return the same shape. One place to change the wording, one place to add branding.

!!! note "`$error` is `null` for an unmatched route"
    It is populated only on the "controller threw" path. Never render `$error->getMessage()` in
    production — it is developer-facing.

If your handler itself throws — a missing template, a failed lookup — karhu catches that and falls
back to a plain 404 rather than cascading into a 500.

## The safety net

`ErrorHandler` covers 404s. Everything else — a bug, a failed query — needs the other seam:

```php
// public/index.php, at the very top, before the App is built
(new Karhu\Error\ExceptionHandler())->register();
```

It installs a global exception handler **and** promotes PHP warnings into exceptions, so they stop
being printed into the middle of a response. Output is content-negotiated: RFC 7807
`application/problem+json` for API clients, HTML for browsers.

Stack traces appear only when `APP_ENV=local`:

```bash
APP_ENV=local php -S localhost:8080 -t public     # traces
php -S localhost:8080 -t public                   # status and title only
```

## A CLI command

`app/Commands/StatsCommand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Commands;

use App\IssueStore;
use Karhu\Attributes\Command;

final class StatsCommand
{
    public function __construct(private readonly IssueStore $store) {}

    #[Command('issues:stats', 'Show issue counts by author')]
    public function handle(array $args): int
    {
        $counts = [];

        foreach ($this->store->all() as $issue) {
            $counts[$issue['author']] = ($counts[$issue['author']] ?? 0) + 1;
        }

        foreach ($counts as $author => $count) {
            fwrite(STDOUT, sprintf("%-12s %d\n", $author, $count));
        }

        return 0;
    }
}
```

`config/commands.php`:

```php
<?php

return [
    App\Commands\StatsCommand::class,
];
```

Commands resolve from a container too, so `IssueStore` is injected. Configure it in
`config/container.php`, which `vendor/bin/karhu` loads before dispatching:

```php
<?php

use App\IssueStore;
use Karhu\Container\Container;

return function (Container $c): void {
    $store = new IssueStore();
    $store->seed();
    $c->set(IssueStore::class, $store);
};
```

```bash
vendor/bin/karhu list
vendor/bin/karhu issues:stats
```

```
admin        1
editor       1
```

The return value is the exit code, so a command can fail a CI job properly.

!!! warning "An option can be `true`"
    `--path=x` gives a string, a bare `--path` gives `true`. Guard with
    `is_string($args['path'] ?? null)` before using it as one.

## Cache the routes

Attribute scanning reflects over every controller on every request. Compile it once:

```bash
vendor/bin/karhu route:cache
# Cached 6 route(s) to cache/routes.php
```

Then load it when present:

```php
$cacheFile = __DIR__ . '/../cache/routes.php';

if (is_file($cacheFile)) {
    $app->router()->loadCache(require $cacheFile);
} else {
    $app->router()->scanControllers(require __DIR__ . '/../config/controllers.php');
}
```

```gitignore
/cache/
```

!!! warning "Rebuild on every deploy"
    A stale cache serves the **old** route table: new routes 404, deleted ones keep answering, and
    nothing appears in the logs. Make `route:cache` a build step that runs *after* the new code is
    in place — and never commit the file.

## Going to production

```bash
composer install --no-dev --optimize-autoloader
vendor/bin/karhu route:cache
export APP_ENV=production
```

Plus the things that are not karhu's job but will bite anyway:

- **Document root on `public/`** — otherwise `config/`, `vendor/` and your source are downloadable
- **Opcache on**, with `validate_timestamps=0` and an FPM reload on deploy
- **Forward `X-Forwarded-Proto`** from your proxy, or session cookies never get the `Secure` flag
- **Set `Cors` origins explicitly** — the default allows any origin

The full list is in [Deployment](../deployment.md).

## Replacing the in-memory stores

`IssueStore` and `DemoUserRepository` reset on every request. For something real:

- **[karhu-db](../packages/db.md)** — a PDO wrapper, an active-record base, and a ready-made
  `PdoUserRepository` that drops straight into Part 4's wiring
- **[karhu-queue](../packages/queue.md)** — background work
- **[karhu-view](../packages/view.md)** — Twig or Plates instead of HTML in string literals

Because `UserRepositoryInterface` was the only coupling, switching storage is one `bind()` call:

```php
$app->container()->bind(UserRepositoryInterface::class, PdoUserRepository::class);
```

## What you built

- Attribute-routed endpoints with implicit `HEAD`, real `405`s, and named routes
- A resource controller dispatching five actions from two route declarations
- Container-wired services with constructor injection
- DTO validation returning field-keyed 422s
- argon2id logins, sessions, CSRF, and role-gated writes
- One place that renders every 404, plus a global safety net
- A CLI command and a compiled route table

## Where next

| | |
|---|---|
| Look up a method | [API Reference](../api/index.md) |
| Test what you built | [Testing](../testing.md) |
| Why it works this way | [ADRs](../adr/index.md) |
| The whole app as one file | [`examples/istrbuddy/app.php`](https://github.com/bjornbasar/karhu/blob/main/examples/istrbuddy/app.php) |
