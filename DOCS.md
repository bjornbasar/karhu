# karhu — Project Documentation

**Version:** 0.1.5 | **License:** MIT | **PHP:** >=8.3

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
| CI | GitHub Actions (GitHub-hosted, `ubuntu-latest`) |

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
├── tests/                # PHPUnit test suite (172 tests, 332 assertions)
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
composer test            # PHPUnit only
composer analyse         # PHPStan level 8
composer cs-fix          # php-cs-fixer (PER-CS2.0)
composer docs-check      # API reference vs src/, by reflection
bin/karhu route:cache    # compile route cache for production
```

`composer serve` is a **karhu-skeleton** script, not one of karhu's own — this repo is a
library and has no `public/` to serve. Run it from a generated app.

---

## Documentation

The full documentation lives in `docs/` and is published at
**[docs.twobots.dev/karhu/](https://docs.twobots.dev/karhu/)** — Installation, Getting Started, a
five-part tutorial, topical guides, and a complete API reference covering all **38 classes and
134 public methods**.

### The reference is machine-checked

`tools/check-docs.php` reflects over `src/` and fails when a public method is missing from
`docs/api/`, when the reference names something that no longer exists, or when a page cites a
`src/` path that has moved. It runs as `composer docs-check` in CI **and** gates the docs
deploy — so **adding a public method fails the build until it is documented.**

This exists because the reference rotted unnoticed: the README's hello-world example was
fatally broken (wrong `#[Route]` argument order, a handler signature the dispatcher never
calls, and a static call to the instance method `Response::json()`), and nothing caught it.

The format the checker expects — a `## ClassName` section plus a table row per public method,
and a `NAMESPACE_PAGES` entry for every namespace — is documented in `docs/contributing.md`.

### Three published copies, one canonical

| | |
|---|---|
| **docs.twobots.dev/karhu/** | canonical, public. nginx container on Hurska `:8100`, Ayula-fronted, deployed by `git push ruxa main` |
| **docs.bjornbasar.com/karhu/** | the SAME container, behind Cloudflare Access — the private view of every reference site at once |
| **GitHub Pages** | DR mirror, unchanged workflow |

All build from `docs/`. `site_url` in `mkdocs.yml` names docs.twobots.dev/karhu/, so
mkdocs-material emits `rel=canonical` there from **every** build and neither the mirror nor the
gated hostname competes in search results. The gated one must never be canonical: it answers an
unauthenticated fetch with a redirect to a login page, so a search engine could never reach it.

**`framework.twobots.dev` is a permanent 301** to the canonical path, preserving path and query.
It was the advertised home on Packagist, in the README and in every release note, so it has to
keep working — a 404 there is a dead link on someone else's page.

### The path prefix lives in the image, not in the proxy

Since the consolidation, the rendered site is copied to `/usr/share/nginx/html/karhu/` and Ayula
proxies the path through **unchanged** rather than stripping it. MkDocs links pages as directory
URLs, so nginx 301s `/karhu/installation` to add a slash, and with `absolute_redirect off` that
`Location` is *root*-relative — relative to the origin, not to any proxied prefix. Had the prefix
been stripped, that redirect would resolve to `docs.twobots.dev/installation/` and land on
another repo's docs. `ci/deploy.sh` asserts the raw `Location` header still carries `/karhu/`,
reading the header directly because curl's `%{redirect_url}` resolves it against the request URL
and so can never tell the two cases apart.

`/robots.txt` is consequently **not** this container's to serve: crawlers read it only at the
host root, which no per-repo container owns. The `docs.twobots.dev` vhost serves one
authoritative file naming every site's sitemap. `docs/robots.txt` is kept for the GitHub Pages
mirror.

---

## CI/CD

**GitHub Actions** (`ubuntu-latest`, per the public-repo CI policy) — the library's gate:
- PHP 8.3 + 8.4 matrix
- php-cs-fixer, PHPStan level 8 (over `src/` **and** `tools/`), PHPUnit, `composer docs-check`, `composer audit`
- Docs deploy to GitHub Pages on `docs/**` changes (the DR mirror)

**Local loop** (`git push ruxa main` → `ci/deploy.sh` on Ruxa) — the docs site. Renders MkDocs
amd64-native on Ruxa, then builds a multi-arch nginx image and deploys to Hurska. It does
**not** run the PHP suite; GitHub Actions owns that. Its gates are `check-docs.php`,
`mkdocs build --strict`, a rendered-page floor, Packagist resolvability, and post-deploy
assertions that the repo itself 404s.

> ⚠ The image is nginx serving a rendered site — **no PHP runs in it**. The `COPY` is an
> allowlist of `site/` only, because the build context is the whole framework repo.

---

## Related Repos

| Repo | Purpose |
|------|---------|
| [karhu-skeleton](https://github.com/bjornbasar/karhu-skeleton) | Starter app template — `composer create-project bjornbasar/karhu-skeleton`. Pins karhu `^0.1.5` |
| [karhu-db](https://github.com/bjornbasar/karhu-db) | PDO wrapper, active-record base, `PdoUserRepository` |
| [karhu-queue](https://github.com/bjornbasar/karhu-queue) | Queue/worker abstraction, database driver |
| [karhu-view](https://github.com/bjornbasar/karhu-view) | Twig + Plates adapters |
| [istrbuddy](https://github.com/bjornbasar/istrbuddy) | Reference app dogfooding the full stack |
| [chukwu](https://github.com/bjornbasar/chukwu) | Archived predecessor |
| [Peopsquik](https://github.com/bjornbasar/Peopsquik) | Archived minimal fork |
