# Configuration

PHP array files with env-var override. No runtime `.env` library required.

## Config files

Place PHP files in `config/`:

```php
// config/database.php
return [
    'host' => 'localhost',
    'port' => 5432,
    'name' => 'myapp',
];
```

## Loading

```php
$config = new \Karhu\Config\Config();
$config->loadDir(__DIR__ . '/../config');

$host = $config->get('database.host');         // 'localhost'
$port = $config->get('database.port');         // 5432
$missing = $config->get('database.foo', 'bar'); // 'bar' (default)
```

## Env override

Environment variables override file values. The key is uppercased with dots replaced by underscores:

```
database.host → DATABASE_HOST
app.name → APP_NAME
```

```bash
DATABASE_HOST=prod-db.example.com php -S localhost:8080 -t public
```

The env value wins over the file value.

!!! warning "An environment override is always a string"
    `getenv()` returns strings, so `DATABASE_PORT=5432` makes `get('database.port')` return
    `'5432'` — while the same key read from the PHP file returns the integer `5432`. Code that
    works locally can fail in production on a strict comparison. Cast at the point of use:

    ```php
    $port = (int) $config->get('database.port', 5432);
    ```

!!! warning "`has()` does not see environment variables"
    It only inspects the loaded files, so a key that exists *solely* in the environment reports
    `false` even though `get()` would return its value. Prefer `get($key, $default)` over
    `has()`-then-`get()`.

## Precedence

```
getenv(UPPER_SNAKE)   →   loaded config files   →   the $default argument
```

`set()` writes into the loaded items, so it does **not** override an environment variable that is
already present.

## For development

Use `vlucas/phpdotenv` as a dev convenience (not a karhu dependency):

```bash
composer require --dev vlucas/phpdotenv
```

```php
// In public/index.php (dev only):
if (file_exists(__DIR__ . '/../.env')) {
    Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->load();
}
```

## See also

- [API reference — `Karhu\Config`](api/config.md)
- [Deployment](deployment.md) — environment configuration in production
