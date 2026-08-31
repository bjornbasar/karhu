# karhu

Minimal PHP microframework — attribute-routed, zero runtime dependencies, PHP 8.3+.

[![CI](https://github.com/bjornbasar/karhu/actions/workflows/ci.yml/badge.svg)](https://github.com/bjornbasar/karhu/actions/workflows/ci.yml)

## Why karhu?

A small framework you can read end-to-end. ~1200 LOC core, no closures-as-routes, no YAML, no required dependencies — controllers declare routes via PHP attributes. Extras (DB, queue, view) ship as separate packages.

## Install

```bash
composer require bjornbasar/karhu
```

Or start from the skeleton app:

```bash
composer create-project bjornbasar/karhu-skeleton myapp
cd myapp
composer serve   # http://localhost:8080
```

Full setup, including web-server config: **[Installation](https://docs.twobots.dev/karhu/installation/)**.

## Hello world

```php
use Karhu\Attributes\Route;
use Karhu\Http\Request;
use Karhu\Http\Response;

final class HomeController {
    #[Route('/hello/{name}', methods: ['GET'])]
    public function hello(Request $request): Response {
        return (new Response())->json(['hello' => $request->routeParams()['name']]);
    }
}
```

Register the controller in `config/controllers.php` and karhu's attribute scanner wires the route automatically.

Three things worth noting, because each is easy to guess wrong: the **path comes first** in `#[Route]` (`methods` is the second, named argument), handlers receive the **`Request`** rather than unpacked route parameters, and `json()` is an **instance** method on `Response`, not a static one.

## What's in the box

| Capability | Where |
|---|---|
| Attribute routing (`#[Route]`) | `src/Http/Router.php` + `src/Attributes/Route.php` |
| Middleware pipeline (PSR-15 shape) | `src/Http/MiddlewarePipeline.php` |
| Auto-wiring DI container (PSR-11 shape) | `src/Container/Container.php` |
| RBAC + PasswordHasher (argon2id) | `src/Auth/` |
| Session, CSRF, CORS, RequireRole middleware | `src/Middleware/` |
| Validation attributes (`#[Required]`, `#[StringLength]`, `#[In]`) | `src/Attributes/` + `src/Http/Validation.php` |
| RFC 7807 error responses | `src/Error/ExceptionHandler.php` |
| CLI dispatcher (`#[Command]`) | `src/Cli/` + `bin/karhu` |
| Logger interface (PSR-3 shape) | `src/Log/` |
| Production route cache | `bin/karhu route:cache` |

## Companion packages

| Package | Purpose |
|---|---|
| [karhu-skeleton](https://github.com/bjornbasar/karhu-skeleton) | Starter app template |
| [karhu-db](https://github.com/bjornbasar/karhu-db) | Thin PDO wrapper + active-record base + `PdoUserRepository` |
| [karhu-queue](https://github.com/bjornbasar/karhu-queue) | Queue/worker abstraction, ships with DB driver |
| [karhu-view](https://github.com/bjornbasar/karhu-view) | Template engine bridge (Twig + Plates adapters) |
| [istrbuddy](https://github.com/bjornbasar/istrbuddy) | Reference issue-tracker app dogfooding the full stack |

## Development

```bash
composer install
composer check           # cs-check + phpstan + tests
composer test            # PHPUnit only
composer analyse         # PHPStan level 8
composer cs-fix          # php-cs-fixer (PER-CS2.0)
bin/karhu route:cache    # compile route cache for production
```

PHP 8.3 + 8.4 are tested via the GitHub Actions matrix.

## Documentation

- [DOCS.md](DOCS.md) — tech stack, directory layout, design decisions
- [docs/](docs/) — MkDocs Material source (full reference, ADRs)
- [docs/adr/](docs/adr/) — architectural decision records

## License

MIT
