# `Karhu\Log`

A PSR-3-shape logger interface and one stderr implementation. karhu declares its own interface
rather than requiring `psr/log` ([ADR 0003](../adr/0003-zero-runtime-deps.md)).

---

## `LoggerInterface`

[`src/Log/LoggerInterface.php`](https://github.com/bjornbasar/karhu/blob/main/src/Log/LoggerInterface.php)

The eight RFC 5424 severity levels plus a generic `log()`. Every method takes a message and an
optional context array.

| Method | Returns | Level |
|---|---|---|
| `emergency(string $message, array $context = [])` | `void` | System is unusable. |
| `alert(string $message, array $context = [])` | `void` | Action must be taken immediately. |
| `critical(string $message, array $context = [])` | `void` | Critical condition. |
| `error(string $message, array $context = [])` | `void` | Runtime error that needs attention. |
| `warning(string $message, array $context = [])` | `void` | Exceptional but not an error. |
| `notice(string $message, array $context = [])` | `void` | Normal but significant. |
| `info(string $message, array $context = [])` | `void` | Informational. |
| `debug(string $message, array $context = [])` | `void` | Developer detail. |
| `log(string $level, string $message, array $context = [])` | `void` | Log at an arbitrary level. |

Signatures match PSR-3, so wrapping a real PSR-3 logger (Monolog and friends) is a pass-through
adapter with no translation.

Bind your implementation on the interface and inject it anywhere:

```php
$container->bind(LoggerInterface::class, StderrLogger::class);
```

---

## `StderrLogger`

[`src/Log/StderrLogger.php`](https://github.com/bjornbasar/karhu/blob/main/src/Log/StderrLogger.php)

Writes one line per entry to `php://stderr`. Implements [`LoggerInterface`](#loggerinterface)
with the same nine methods. No constructor.

Output format:

```
[2026-08-19 05:34:57] ERROR: user 42 not found
```

That is `[Y-m-d H:i:s] LEVEL: message`, with the level upper-cased. Every level routes through
`log()`, so overriding that one method changes the format for all of them.

### Context interpolation

`{key}` placeholders in the message are replaced with the matching context value. Scalars are
cast to string; anything else is JSON-encoded.

```php
$logger->error('user {id} not found in {table}', ['id' => 42, 'table' => 'users']);
// [2026-08-19 05:34:57] ERROR: user 42 not found in users

$logger->info('imported {stats}', ['stats' => ['ok' => 3, 'failed' => 1]]);
// [2026-08-19 05:34:57] INFO: imported {"ok":3,"failed":1}
```

Context keys with no matching placeholder are **dropped**, not appended — unlike Monolog, which
renders leftover context at the end of the line. Put everything you want to see in the message
template.

!!! note "stderr is the right default in a container"
    Docker captures stderr as the container log, so this needs no file paths, no rotation and no
    write permissions. `Karhu\Error\ExceptionHandler` logs the same way, for the same reason.

!!! warning "No level filtering"
    Every call writes. There is no minimum-level setting, so `debug()` in production is real
    output volume. Wrap the interface if you need filtering:

    ```php
    final class LevelFilter implements LoggerInterface
    {
        private const RANK = ['debug' => 0, 'info' => 1, 'notice' => 2, 'warning' => 3,
                              'error' => 4, 'critical' => 5, 'alert' => 6, 'emergency' => 7];

        public function __construct(
            private readonly LoggerInterface $inner,
            private readonly string $min = 'info',
        ) {}

        public function log(string $level, string $message, array $context = []): void
        {
            if ((self::RANK[strtolower($level)] ?? 0) >= self::RANK[$this->min]) {
                $this->inner->log($level, $message, $context);
            }
        }

        // ... the eight level methods delegate to $this->log(...)
    }
    ```
