# karhu

Minimal PHP microframework — attribute-routed, zero runtime dependencies, PHP 8.3+.

[![CI](https://github.com/bjornbasar/karhu/actions/workflows/ci.yml/badge.svg)](https://github.com/bjornbasar/karhu/actions/workflows/ci.yml)

## Why karhu?

A small framework you can read end-to-end. ~1200 LOC core, no closures-as-routes, no YAML, no required dependencies — controllers declare routes via PHP attributes. Extras (DB, queue, view) ship as separate packages.

## Install

```bash
composer create-project bjornbasar/karhu-skeleton myapp
cd myapp
composer serve   # http://localhost:8080
```

Or add karhu to an existing project:

```bash
composer require bjornbasar/karhu
```

## Hello world

```php
use Karhu\Attributes\Route;
use Karhu\Http\Response;

final class HomeController {
    #[Route('GET', '/hello/{name}')]
    public function hello(string $name): Response {
        return Response::json(['hello' => $name]);
    }
}
```

Register the controller in `config/controllers.php` and karhu's attribute scanner wires the route automatically.

## What's in the box

| Capability | Where |
|---|---|
| Attribute routing (`#[Route]`) | `src/Http/Router.php` + `src/Attributes/Route.php` |
| Middleware pipeline (PSR-15 shape) | `src/Http/MiddlewarePipeline.php` |
| Auto-wiring DI container (PSR-11 shape) | `src/Container/Container.php` |
| RBAC + PasswordHasher (argon2id) | `src/Auth/` |
| Session, CSRF, CORS, RequireRole middleware | `src/Middleware/` |
| Validation attributes (`#[Required]`, `#[StringLength]`, `#[In]`) | `src/Http/Validation/` |
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
