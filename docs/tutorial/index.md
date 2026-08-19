# Tutorial: build an issue tracker

Over five parts we build **IsTrBuddy**, a small issue tracker, from an empty directory to
something with authentication, role-based permissions, validation and a production route cache.

Every step is runnable. Nothing is hand-waved, and nothing is left as an exercise.

| Part | What you build | What you learn |
|---|---|---|
| **[1. Routes and responses](01-routes.md)** | An app that lists issues | `#[Route]`, `Request`, `Response`, the front controller |
| **[2. Controllers and services](02-controllers.md)** | A real store behind a resource controller | Constructor injection, the container, `AbstractResourceController` |
| **[3. Validating input](03-validation.md)** | Issue creation that rejects bad data | DTOs, the six validators, 422 responses |
| **[4. Authentication and roles](04-auth.md)** | Login, and permissions on write | `PasswordHasher`, `Rbac`, `Session`, `RequireRole` |
| **[5. Errors, CLI and production](05-production.md)** | A branded 404, a CLI command, a cached route table | `ErrorHandler`, `#[Command]`, `route:cache` |

## What you need

- PHP 8.3 or newer, and Composer 2
- A terminal, and `curl` for poking at the app

No database — the tutorial uses in-memory stores so the focus stays on the framework. Part 5
points at [karhu-db](../packages/db.md) for the real thing.

## Set up

```bash
mkdir istrbuddy && cd istrbuddy
composer require bjornbasar/karhu
```

Add an autoload namespace to `composer.json`:

```json
{
    "require": {
        "bjornbasar/karhu": "^0.1.4"
    },
    "autoload": {
        "psr-4": {
            "App\\": "app/"
        }
    }
}
```

```bash
composer dump-autoload
mkdir -p app/Controllers config public
```

You should have:

```
istrbuddy/
├── app/Controllers/
├── config/
├── public/
├── composer.json
└── vendor/
```

Now start with **[Part 1 — Routes and responses](01-routes.md)**.

---

!!! tip "The finished version is in the repo"
    [`examples/istrbuddy/app.php`](https://github.com/bjornbasar/karhu/blob/main/examples/istrbuddy/app.php)
    is the whole thing as one 281-line script — handy for comparing against once you are done, or
    if a step does not behave.

    ```bash
    php -S localhost:8080 -t examples/istrbuddy examples/istrbuddy/app.php
    ```
