# `Karhu\Config`

Configuration from plain PHP array files, with environment-variable overrides and dot-notation
access. No `.env` parser and no YAML — a config file is PHP that returns an array
([ADR 0003](../adr/0003-zero-runtime-deps.md)).

---

## `Config`

[`src/Config/Config.php`](https://github.com/bjornbasar/karhu/blob/main/src/Config/Config.php)

```php
public function __construct(array $items = [])
```

Pass a pre-built array, or start empty and call `loadDir()`.

| Method | Returns | Description |
|---|---|---|
| `loadDir(string $path)` | `void` | Load every `*.php` in the directory. The filename becomes the top-level key. |
| `get(string $key, mixed $default = null)` | `mixed` | Read by dot notation, **environment first**. |
| `set(string $key, mixed $value)` | `void` | Write by dot notation, creating intermediate arrays. |
| `has(string $key)` | `bool` | Whether the key exists in the loaded items. |
| `all()` | `array<string,mixed>` | Everything, as a nested array. |

### Loading a directory

```php
// config/database.php
return [
    'host' => 'localhost',
    'port' => 5432,
];
```

```php
$config = new Config();
$config->loadDir(__DIR__ . '/../config');

$config->get('database.host');   // 'localhost'
```

A file that does not return an array is skipped silently. Files are read in `glob()` order, and
each one **replaces** its top-level key rather than merging into it.

### Environment overrides

`get()` checks the environment **before** the loaded values. The key is upper-cased with dots
turned into underscores:

| `get()` key | Environment variable |
|---|---|
| `database.host` | `DATABASE_HOST` |
| `app.name` | `APP_NAME` |
| `mail.smtp.port` | `MAIL_SMTP_PORT` |

This is what makes a container deployment work without a config file per environment.

!!! warning "An environment override is always a string"
    `getenv()` returns strings. `DATABASE_PORT=5432` makes `get('database.port')` return
    `'5432'`, not `5432` — while the same key read from the PHP file returns an `int`. Cast at
    the point of use if the type matters:

    ```php
    $port = (int) $config->get('database.port', 5432);
    ```

!!! warning "`has()` ignores the environment"
    `has()` only inspects the loaded items. A key that exists **solely** as an environment
    variable reports `false` even though `get()` would return its value. Prefer
    `get($key, $default)` over `has()`-then-`get()`.

### Precedence summary

```
getenv(UPPER_SNAKE)   →   loaded config files   →   the $default argument
```

`set()` writes to the loaded items, so it does **not** override an environment variable that is
already present — a later `get()` still returns the environment value.
