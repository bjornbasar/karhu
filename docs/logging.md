# Logging

karhu ships a PSR-3-shape [`LoggerInterface`](api/log.md#loggerinterface) and one implementation,
[`StderrLogger`](api/log.md#stderrlogger), that writes to `php://stderr`.

## Wiring it

```php
use Karhu\Log\{LoggerInterface, StderrLogger};

$app->container()->bind(LoggerInterface::class, StderrLogger::class);
```

Then type-hint the interface anywhere:

```php
final class IssueController
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    #[Route('/issues', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $this->logger->info('issue created by {user}', ['user' => Session::get('username')]);
        // ...
    }
}
```

## Levels

The eight RFC 5424 levels, plus `log($level, ...)`:

```php
$logger->emergency('...');   // system unusable
$logger->alert('...');       // act immediately
$logger->critical('...');    // critical condition
$logger->error('...');       // runtime error needing attention
$logger->warning('...');     // exceptional, not an error
$logger->notice('...');      // normal but significant
$logger->info('...');        // informational
$logger->debug('...');       // developer detail
```

## Context interpolation

`{key}` placeholders are replaced from the context array. Scalars become strings; anything else is
JSON-encoded.

```php
$logger->error('user {id} not found in {table}', ['id' => 42, 'table' => 'users']);
// [2026-08-19 05:34:57] ERROR: user 42 not found in users
```

!!! warning "Context without a placeholder is dropped"
    Unlike Monolog, leftover context is **not** appended to the line. This logs nothing useful:

    ```php
    $logger->error('lookup failed', ['id' => 42, 'table' => 'users']);
    // [2026-08-19 05:34:57] ERROR: lookup failed
    ```

    Put every value you want to see in the message template.

## Why stderr

Docker captures a container's stderr as its log stream, so `StderrLogger` needs no file path, no
rotation, and no write permissions anywhere. `Karhu\Error\ExceptionHandler` logs the same way, for
the same reason, and it does so unconditionally — uncaught exceptions are always logged even with
no logger bound.

Under PHP-FPM, stderr goes to the FPM error log; add `catch_workers_output = yes` to the pool
config if lines are not appearing.

## Limitations, and what to do about them

`StderrLogger` is about 60 lines and deliberately minimal:

- **No level filtering.** Every call writes, so `debug()` in production is real volume.
- **No structured output.** Lines are plain text, not JSON — fine for `docker logs`, awkward for a
  log aggregator that wants fields.
- **No handlers or channels.**

All three are solved by binding something else. The interface matches PSR-3 method-for-method, so
a real PSR-3 logger drops in with a pass-through adapter:

```php
final class MonologAdapter implements Karhu\Log\LoggerInterface
{
    public function __construct(private readonly \Psr\Log\LoggerInterface $inner) {}

    public function log(string $level, string $message, array $context = []): void
    {
        $this->inner->log($level, $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->inner->error($message, $context);
    }

    // ... the remaining seven levels delegate the same way
}
```

```php
$app->container()->set(LoggerInterface::class, new MonologAdapter($monolog));
```

Nothing in karhu's own code depends on the implementation, only on the interface — so swapping it
changes no framework behaviour.

A minimum-level filter, if that is all you need, is on the
[API page](api/log.md#stderrlogger).
