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
