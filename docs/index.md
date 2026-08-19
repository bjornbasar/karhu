# karhu

**Minimal PHP microframework — attribute-routed, zero runtime dependencies, PHP 8.3+.**

```php
use Karhu\Attributes\Route;
use Karhu\Http\Request;
use Karhu\Http\Response;

final class HelloController
{
    #[Route('/hello/{name}', methods: ['GET'])]
    public function greet(Request $request): Response
    {
        return (new Response())->json(['hello' => $request->routeParams()['name']]);
    }
}
```

```bash
composer require bjornbasar/karhu
```

<div class="grid cards" markdown>

- **[Installation](installation.md)** — requirements, first app, web-server config
- **[Getting Started](getting-started.md)** — middleware, services, validation, errors
- **[Tutorial](tutorial/index.md)** — build a real application end to end
- **[API Reference](api/index.md)** — every class and method

</div>

---

## Why karhu?

**A framework you can read in an afternoon.** The core is about 3,100 lines. When something
behaves unexpectedly, opening the file is a realistic option — so every page here links to the
source.

- **Zero runtime dependencies** — the `require` block is `php: >=8.3` and nothing else
- **Attribute-only routing** — `#[Route('/path')]` on a method. No YAML, no route files, no
  closure chains ([ADR 0007](adr/0007-opinionated-attribute-only-routing.md))
- **PSR shapes without the packages** — PSR-7, PSR-11 and PSR-15 method signatures, implemented
  directly ([ADR 0005](adr/0005-psr7-shape-not-compliance.md))
- **Extras stay optional** — database, queue and templating ship as separate packages you install
  only if you want them

It is **not** trying to be Laravel or Symfony. There is no ORM, no event system, no scheduler and
no asset pipeline. If you want those, you want one of those.

## What's included

| Component | What it does |
|---|---|
| [Router](routing.md) | Attribute-scanned, regex-compiled. Groups, named routes, `urlFor()`, implicit `HEAD`, automatic `405` with `Allow` |
| [Request / Response](requests.md) | Immutable value objects, JSON auto-decode, content negotiation |
| [Middleware](middleware.md) | PSR-15-shape pipeline. `Session`, `Csrf`, `Cors`, `RequireRole` included |
| [Container](container.md) | PSR-11-shape auto-wiring DI, with circular-dependency detection |
| [Auth](auth.md) | argon2id hashing, RBAC behind a repository interface |
| [Validation](validation.md) | Six attribute validators, applied to a DTO |
| [Errors](errors.md) | RFC 7807 problem+json, plus a pluggable 404 branding seam |
| [CLI](cli.md) | `#[Command]` dispatcher, no `symfony/console` |
| [Config](config.md) | PHP arrays with environment overrides and dot notation |
| [Logging](logging.md) | PSR-3-shape interface, stderr implementation |

## Companion packages

Each is optional and independently versioned.

| Package | Purpose |
|---|---|
| [karhu-skeleton](packages/skeleton.md) | Starter application layout |
| [karhu-db](packages/db.md) | PDO wrapper, active-record base, `PdoUserRepository` |
| [karhu-queue](packages/queue.md) | Queue and worker abstraction with a database driver |
| [karhu-view](packages/view.md) | Twig and Plates adapters |
| [istrbuddy](packages/istrbuddy.md) | Reference application dogfooding the whole stack |

## Status

**v0.1.4**, MIT licensed. Pre-1.0, so minor versions can break — pin with `^0.1.4`.

The suite is 172 tests and 332 assertions, PHPStan runs at level 8, and the API reference on this
site is machine-checked against the source on every build.

## Where the documentation lives

- **[framework.twobots.dev](https://framework.twobots.dev/)** — this site, the canonical home
- **[github.com/bjornbasar/karhu](https://github.com/bjornbasar/karhu)** — source and issues
- A mirror is published to GitHub Pages for redundancy; both build from `docs/` in the repo
