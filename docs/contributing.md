# Contributing

Source: [`CONTRIBUTING.md`](https://github.com/bjornbasar/karhu/blob/main/CONTRIBUTING.md) ·
[`MAINTENANCE.md`](https://github.com/bjornbasar/karhu/blob/main/MAINTENANCE.md)

## Setup

```bash
git clone git@github.com:bjornbasar/karhu.git
cd karhu
composer install
```

## Checks

```bash
composer check        # everything below, in order
composer cs-check     # php-cs-fixer, dry run
composer analyse      # PHPStan level 8 over src/ and tools/
composer test         # PHPUnit — 172 tests
composer docs-check   # the API reference against src/
composer cs-fix       # auto-format
```

`composer check` must pass before a PR is opened. CI runs the same set on **PHP 8.3 and 8.4**,
plus `composer audit`.

## Pull requests

1. Branch from `main`.
2. Write the test first — karhu is developed TDD.
3. Make `composer check` pass.
4. One commit per logical change, messaged `type(scope): summary`.
5. Open the PR against `main` with a clear description.

Types: `feat`, `fix`, `docs`, `chore`, `refactor`, `test`.

Style is [PER-CS 2.0](https://www.php-fig.org/per/coding-style/); `composer cs-fix` applies it.

---

## Documentation changes

The docs live in [`docs/`](https://github.com/bjornbasar/karhu/tree/main/docs) and are built with
MkDocs Material. Preview locally:

```bash
docker run --rm -p 8000:8000 -v "$PWD:/docs" squidfunk/mkdocs-material:9.5.44
```

### The API reference is checked, not trusted

`composer docs-check` reflects over `src/` and fails when:

1. a public method is **not** documented on its namespace page,
2. a method table documents something that **no longer exists**, or
3. a page cites a `src/` path that is **not there**.

It runs in CI and gates the docs deploy. **Adding a public method to `src/` will fail the build
until it is documented** — that is intended. This check exists because the README's hello-world
example was fatally broken for months and nothing noticed.

### The format the checker expects

Each class gets a `## ClassName` section on the page its namespace maps to (the map is
`NAMESPACE_PAGES` in
[`tools/check-docs.php`](https://github.com/bjornbasar/karhu/blob/main/tools/check-docs.php)), and
every public method appears in a table row:

```markdown
## Widget

| Method | Returns | Description |
|---|---|---|
| `frobnicate(int $times)` | `self` | Frobnicates the widget. |
| `static build()` | `self` | Named constructor. |
```

A **new namespace** under `src/` must be added to `NAMESPACE_PAGES` and given a page, or the check
fails with `UNMAPPED NS` rather than skipping it silently.

Paths in fenced code blocks are ignored by the path check, so example stack traces can name files
that do not exist.

### Run every example you write

The broken README example type-errored on the first line of `scanControllers()` — it had clearly
never been executed. Run new snippets against the real framework before committing them.

---

## What belongs in core

Core is intentionally around 3,100 lines. **New capabilities should generally ship as a separate
`karhu-*` package**, not as an addition here. Open an issue to discuss before writing code.

The bar for an exception is high, and v0.1.4 is the worked example: `Http\ErrorHandler`,
`Http\NotFoundException` and `Request::prefersJson()` went into core because they touch
`App::dispatch()` — a core dispatch-flow concern. A sub-package would have required `App` to
expose a setter API, which is more surface than the ~60-line change itself.

When in doubt, prefer the sub-package.

## Versioning

[Semantic Versioning 2.0.0](https://semver.org/). karhu is **pre-1.0**, so `0.x` minor releases
may break the API. Consumers should pin (`^0.1.5`).

Maintenance commitments: security patches promptly; the PHP floor tracks supported releases and
bumps in the next minor once the current floor reaches EOL; bug fixes by PR with tests.

## Reporting bugs

Open a [GitHub issue](https://github.com/bjornbasar/karhu/issues) with the karhu version, the PHP
version, steps to reproduce, and expected versus actual behaviour.

## Archive criteria

If no commit lands in 12 months and no security issue is pending, the repo gets an `ARCHIVED.md`
and a README sunset notice — the same treatment given to chukwu and Peopsquik. That is not
defeatism; people depending on this deserve to know what is maintained.
