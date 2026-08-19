# Deployment

Three things separate a karhu app in production from one in development: the route cache, opcache,
and not leaking internals in error pages.

## The release checklist

```bash
composer install --no-dev --optimize-autoloader   # 1. no dev deps, classmap autoloader
vendor/bin/karhu route:cache                             # 2. compile the route table
export APP_ENV=production                         # 3. no stack traces
```

Plus: document root on `public/`, opcache on, and logs going somewhere you read.

---

## 1. Route caching

Attribute scanning uses reflection on every boot. `route:cache` compiles the table to a plain PHP
array so production skips reflection entirely.

```bash
vendor/bin/karhu route:cache                       # → cache/routes.php
vendor/bin/karhu route:cache --path=var/routes.php
vendor/bin/karhu route:clear
```

The command reads `config/controllers.php` relative to the **current working directory**, so run
it from the project root. It exits `1` if that file is missing or does not return an array.

Load the cache when it exists:

```php
$app = new Karhu\App();

$cacheFile = __DIR__ . '/../cache/routes.php';

if (is_file($cacheFile)) {
    $app->router()->loadCache(require $cacheFile);
} else {
    $app->router()->scanControllers(require __DIR__ . '/../config/controllers.php');
}

$app->run();
```

!!! warning "Rebuild the cache on every deploy"
    A stale cache serves the **old** route table. New routes 404 and removed ones keep answering,
    with nothing in the logs to explain it. Make `route:cache` a build step that runs after the
    new code is in place, and never commit `cache/routes.php`.

```gitignore
/cache/
```

## 2. Opcache

The single biggest performance setting for any PHP application:

```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0     ; production only — see below
```

`validate_timestamps=0` stops PHP stat-ing every file on every request, but it also means
**changed files are ignored until the process restarts**. Reload PHP-FPM as part of the deploy:

```bash
kill -USR2 $(cat /run/php-fpm.pid)      # or: systemctl reload php8.3-fpm
```

If you deploy by swapping a container image this is free — the new container is a new process.

## 3. Environment

```bash
APP_ENV=production
```

`ExceptionHandler` shows a stack trace only when `APP_ENV=local`. Anything else gets the status
and its title. Verify it after deploying — an environment variable that did not reach PHP-FPM is
a common and quiet mistake:

```bash
curl -s https://example.com/a-route-that-throws | grep -qi 'stack\|#0 ' \
  && echo 'LEAKING TRACES' || echo 'ok'
```

[`Config`](api/config.md) reads environment overrides for every key, so
`DATABASE_HOST` overrides `database.host` with no config file per environment. Remember the value
arrives as a **string**.

---

## Docker

```dockerfile
FROM php:8.3-fpm-alpine

RUN docker-php-ext-install opcache
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts

COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative \
 && php vendor/bin/karhu route:cache

ENV APP_ENV=production
```

Build the route cache **after** the source is copied — running it before means caching an empty
table.

`StderrLogger` and `ExceptionHandler` both write to stderr, which Docker captures as the container
log, so no log files or volumes are needed.

## Web server

Covered in [Installation](installation.md#web-server-configuration). The rule that matters:
**document root on `public/`**. Serving the project root exposes `config/`, `vendor/` and your
source.

Sub-directory deployments need one line:

```php
$app->setBasePath('/myapp');
```

## Health checks

karhu has no built-in health endpoint. One route is enough:

```php
#[Route('/healthz', methods: ['GET'])]
public function health(Request $request): Response
{
    return (new Response())->json(['status' => 'ok']);
}
```

Keep it free of database calls unless you want a slow database to take the app out of the load
balancer.

---

## Upgrading

karhu is **pre-1.0** — the minor version can carry breaking changes. Pin it:

```json
{ "require": { "bjornbasar/karhu": "^0.1.4" } }
```

Before upgrading, run your suite plus `composer analyse`; PHPStan catches signature changes that
tests miss. The [ADRs](adr/index.md) record why the load-bearing decisions are what they are, and
each release documents its own upgrade notes.

## A production checklist

- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] `vendor/bin/karhu route:cache` run **after** the code is in place
- [ ] `APP_ENV=production`, verified by a real request
- [ ] opcache on, `validate_timestamps=0`, FPM reloaded on deploy
- [ ] document root is `public/`
- [ ] HTTPS, with `X-Forwarded-Proto` forwarded so session cookies get `Secure`
- [ ] CORS `origins` set explicitly — the default allows any origin
- [ ] stderr collected somewhere you will actually look
