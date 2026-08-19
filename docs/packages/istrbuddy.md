# IsTrBuddy — the reference application

**Issue Tracking Buddy** is the first real application built on karhu, and it exists to prove the
framework works end to end rather than only in examples. If you want to see how the pieces fit at
full size, read this rather than the tutorial.

[github.com/bjornbasar/istrbuddy](https://github.com/bjornbasar/istrbuddy) · MIT

## Run it

```bash
git clone https://github.com/bjornbasar/istrbuddy.git
cd istrbuddy
composer install
vendor/bin/karhu db:seed
composer serve
```

Then open <http://localhost:8080/issues>.

Seed passwords come from the environment and default to `changeme`:

```bash
SEED_ADMIN_PASS=… SEED_EDITOR_PASS=… SEED_VIEWER_PASS=… vendor/bin/karhu db:seed
```

| User | Roles | Can |
|---|---|---|
| `admin` | admin, editor | everything, including delete |
| `editor` | editor | create issues, change status |
| `viewer` | viewer | read only |

## What it demonstrates

| karhu feature | Where it shows up |
|---|---|
| [`#[Route]`](../api/attributes.md#route) | every controller |
| [`Session`](../api/middleware.md#session) | the auth flow |
| [`Csrf`](../api/middleware.md#csrf) | every state-changing form |
| [`Cors`](../api/middleware.md#cors) | the JSON API |
| [`RequireRole`](../api/middleware.md#requirerole) | create and delete gates |
| [`Validation`](../api/http.md#validation) | `app/Dto/CreateIssueDto.php` |
| [`Rbac`](../api/auth.md#rbac) + `PasswordHasher` | login, argon2id hashes |
| [`#[Command]`](../api/attributes.md#command) | `vendor/bin/karhu db:seed` |
| Content negotiation | one controller serves both HTML and JSON |
| [karhu-db](db.md) | `app/Repository/IssueRepository.php` |

The content-negotiation point is the interesting one: the same controllers serve the browser UI
and the JSON API, chosen by `prefersJson()`. There is no separate API layer.

## Shape

```
istrbuddy/
├── app/
│   ├── Commands/          SeedCommand
│   ├── Controllers/       AuthController, IssueController
│   ├── Dto/               CreateIssueDto and friends
│   └── Repository/        IssueRepository, backed by karhu-db
├── config/                controllers.php, commands.php, container.php
├── db/                    SQLite file + schema
├── public/index.php
└── tests/
```

SQLite for zero-config persistence, swappable to PostgreSQL by changing the DSN. Views are inline
PHP with inline styles — no template engine and no build step, deliberately, so the repo has
nothing to compile.

## The single-file version

karhu also ships a condensed version as an example: the same ideas in 281 lines with in-memory
stores.

```bash
php -S localhost:8080 -t examples/istrbuddy examples/istrbuddy/app.php
curl localhost:8080/issues
```

[`examples/istrbuddy/app.php`](https://github.com/bjornbasar/istrbuddy) is what the
[Tutorial](../tutorial/index.md) builds up to, one piece at a time.

## Why it matters to the framework

istrbuddy is karhu's dogfood. Several parts of the framework exist because building this found
them missing — the `config/container.php` loading in `vendor/bin/karhu` and the container's
default-value fallback both came from CLI commands needing real dependencies.

It is also the compatibility canary: karhu's own test suite asserts that changes do not break
istrbuddy's usage patterns, which is why
[`DefaultErrorHandler`](../api/http.md#defaulterrorhandler) reproduces the pre-v0.1.4 response
bodies byte for byte.

## Related

- [Tutorial](../tutorial/index.md) — build a smaller version yourself
- [karhu-db](db.md) — the persistence layer it uses
- [ADRs](../adr/index.md) — the decisions it validated
