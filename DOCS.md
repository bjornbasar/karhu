# karhu — Project Documentation

**Version:** 0.1.0 | **License:** MIT | **PHP:** >=8.3

Minimal PHP microframework — attribute-routed, zero runtime dependencies.

---

## Tech Stack

| Component | Technology |
|-----------|-----------|
| Language | PHP 8.3+ |
| Autoloading | Composer PSR-4 |
| Testing | PHPUnit 11 |
| Static analysis | PHPStan level 8 |
| Code style | php-cs-fixer (PER-CS2.0) |
| Docs | MkDocs Material |
| CI | GitHub Actions (self-hosted runner) |

---

## Directory Structure

```
karhu/
├── src/
│   ├── Attributes/       # Route, Command, validation attributes
│   ├── Auth/              # PasswordHasher, Rbac, UserRepositoryInterface
│   ├── Cli/              # CommandDispatcher, Commands/
│   ├── Config/           # Config (dot-notation + env override)
│   ├── Container/        # PSR-11-shape auto-wiring DI
│   ├── Error/            # ExceptionHandler (RFC 7807)
│   ├── Http/             # Request, Response, Router, MiddlewarePipeline,
│   │                     # AbstractResourceController, Cookie, Validation
│   ├── Log/              # LoggerInterface (PSR-3 shape), StderrLogger
│   └── Middleware/       # Session, Csrf, Cors, RequireRole
│   └── App.php           # Front controller
├── bin/karhu             # CLI entry point
├── tests/                # PHPUnit test suite (151 tests)
├── examples/istrbuddy/   # Dogfood reference app
├── docs/                 # MkDocs Material source
└── composer.json         # Zero runtime deps, dev-only tooling
```

---

## Key Design Decisions

- **Zero runtime deps** — `require` section has only `php: >=8.3`
- **PSR-7/11/15 shape** — compatible interfaces without requiring psr/* packages
- **Attribute-only routing** — no closures, no YAML; routes declared on handlers
- **~1200 LOC core** — intentionally minimal; extensions via separate packages
- **Production route cache** — `bin/karhu route:cache` eliminates reflection cost

See `docs/adr/` for full architectural decision records.

---

## Development

```bash
composer install
composer check           # cs-check + phpstan + tests
composer serve           # php -S localhost:8080 -t public (skeleton)
bin/karhu route:cache    # compile route cache for production
```

---

## CI/CD

GitHub Actions on self-hosted runner (Hurska):
- PHP 8.3 + 8.4 matrix
- php-cs-fixer, PHPStan level 8, PHPUnit, composer audit
- Docs deploy to GitHub Pages on docs/** changes

---

## Related Repos

| Repo | Purpose |
|------|---------|
| [karhu-skeleton](https://github.com/bjornbasar/karhu-skeleton) | Starter app template |
| [chukwu](https://github.com/bjornbasar/chukwu) | Archived predecessor |
| [Peopsquik](https://github.com/bjornbasar/Peopsquik) | Archived minimal fork |
