# CLI

karhu includes a built-in CLI command dispatcher. No symfony/console required.

## Running commands

```bash
vendor/bin/karhu <command> [options]
vendor/bin/karhu list                # show all commands
vendor/bin/karhu help <command>      # show help for a command
vendor/bin/karhu route:cache         # built-in: compile route cache
vendor/bin/karhu route:clear         # built-in: remove route cache
```

!!! warning "`vendor/bin/karhu` needs v0.1.5 or newer"
    The `bin` key that makes Composer link the CLI into `vendor/bin/` was added after v0.1.4.
    On v0.1.4 the shim is simply not created, and the entry point has to be run by its real
    path:

    ```bash
    php vendor/bjornbasar/karhu/bin/karhu list
    ```

    Inside the karhu repo itself it is just `bin/karhu`.

## Writing commands

```php
use Karhu\Attributes\Command;

final class GreetCommand
{
    #[Command('greet', 'Say hello to someone')]
    public function handle(array $args): int
    {
        $name = $args['name'] ?? 'world';
        echo "Hello, {$name}!\n";
        return 0; // exit code
    }
}
```

Register in `config/commands.php`:

```php
return [
    App\Commands\GreetCommand::class,
];
```

Run:

```bash
vendor/bin/karhu greet --name=Bjorn
# Hello, Bjorn!
```

## Argument parsing

- `--key=value` → `$args['key']` = `'value'`
- `--flag` → `$args['flag']` = `true`
- Positional args → `$args['0']`, `$args['1']`, etc.

Positional keys are **strings**, not integers. There is no `-x` short-option form — a single dash
is treated as positional.

!!! warning "An option is `string|true`, so check before using it"
    `--name=Bjorn` gives a string, but a bare `--name` gives `true`. Reading it straight into a
    string context breaks on the second form:

    ```php
    $name = is_string($args['name'] ?? null) ? $args['name'] : 'world';
    ```

    That is the guard the shipped `route:cache` command uses for `--path`.

## Dependency injection for commands

Commands are resolved from a container, so they take constructor dependencies exactly like
controllers. `vendor/bin/karhu` looks for **`config/container.php`** in the current working directory and
loads it *before* building the dispatcher. The file may return either a fully built `Container`,
or a callable that configures a fresh one:

```php
<?php
// config/container.php
use Karhu\Container\Container;

return function (Container $c): void {
    $c->factory(PDO::class, fn() => new PDO(getenv('DATABASE_DSN')));
    $c->bind(IssueRepository::class, PdoIssueRepository::class);
};
```

```php
final class ReindexCommand
{
    public function __construct(private readonly IssueRepository $issues) {}

    #[Command('search:reindex', 'Rebuild the search index')]
    public function handle(array $args): int
    {
        $this->issues->reindex();
        return 0;
    }
}
```

Without that file the dispatcher uses an empty container, so commands with only auto-wirable
dependencies still work.

## Your own entry point

`vendor/bin/karhu` is the framework's. An application usually wants its own, so the container is built
the same way as in `public/index.php`:

```php
#!/usr/bin/env php
<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$dispatcher = new Karhu\Cli\CommandDispatcher($container);
$dispatcher->scanCommands([
    Karhu\Cli\Commands\RouteCacheCommand::class,
    ...require __DIR__ . '/../config/commands.php',
]);

exit($dispatcher->dispatch($argv));
```

## Exit codes

The handler's return value becomes the process exit code when it is an `int`; anything else
yields `0`. An unknown command exits `1`.

```php
if ($file === null) {
    fwrite(STDERR, "usage: data:import <file>\n");
    return 1;
}
```

## See also

- [API reference — `Karhu\Cli`](api/cli.md) — `CommandDispatcher`, `RouteCacheCommand`
- [Deployment](deployment.md) — running `route:cache` as a release step
