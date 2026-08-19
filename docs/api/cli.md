# `Karhu\Cli`

Attribute-registered console commands, dispatched by `vendor/bin/karhu`. No `symfony/console` — just
attribute scanning and argument parsing.

---

## `CommandDispatcher`

[`src/Cli/CommandDispatcher.php`](https://github.com/bjornbasar/karhu/blob/main/src/Cli/CommandDispatcher.php)

```php
public function __construct(?Container $container = null)
```

Commands are resolved from the container, so they get constructor injection exactly like
controllers. Without one, a fresh `Container` is created.

| Method | Returns | Description |
|---|---|---|
| `scanCommands(array $classes)` | `void` | Reflect over the classes and register every `#[Command]` on a public method. |
| `addCommand(string $name, string $handler, string $description = '')` | `void` | Register explicitly. `$handler` is `'Class::method'`. |
| `dispatch(array $argv)` | `int` | Run a command. Returns the process exit code. |
| `static parseArgs(array $args)` | `array<string,string\|true>` | Parse raw arguments. |
| `commands()` | `array` | The registry, keyed by name. |

### Dispatch behaviour

`dispatch()` expects a full `$argv`, script name included.

| Input | Result |
|---|---|
| no arguments, or `list` | print the command list, exit `0` |
| `help <command>` | print that command's help, exit `0` |
| `help` with no name | error to stderr, exit `1` |
| any command with `--help` or `-h` | that command's help, exit `0` |
| an unregistered name | error to stderr, exit `1` |
| a registered name | invoke it |

The handler's return value becomes the exit code when it is an `int`; anything else yields `0`.

```php
#!/usr/bin/env php
<?php
// bin/console
require __DIR__ . '/../vendor/autoload.php';

$dispatcher = new Karhu\Cli\CommandDispatcher($container);
$dispatcher->scanCommands([
    Karhu\Cli\Commands\RouteCacheCommand::class,
    App\Commands\ImportCommand::class,
]);

exit($dispatcher->dispatch($argv));
```

### Argument parsing

`parseArgs()` handles three forms:

| Argument | Parsed as |
|---|---|
| `--name=value` | `['name' => 'value']` |
| `--flag` | `['flag' => true]` |
| `value` | `['0' => 'value']`, then `'1'`, … |

```php
CommandDispatcher::parseArgs(['--path=cache/routes.php', '--force', 'widgets']);
// ['path' => 'cache/routes.php', 'force' => true, '0' => 'widgets']
```

Positional keys are **strings**, not integers. There is no `-x` short-option support: a single
dash is treated as positional.

!!! warning "Check the type before using a value"
    An option is `string|true`. `--path` with no `=` yields `true`, so a bare
    `$args['path']` can be a boolean where a string is expected. The shipped commands guard with
    `is_string($args['path'] ?? null)`.

### Writing a command

```php
use Karhu\Attributes\Command;

final class ImportCommand
{
    public function __construct(private readonly Importer $importer) {}

    #[Command('data:import', 'Import records from a CSV file')]
    public function handle(array $args): int
    {
        $file = is_string($args['0'] ?? null) ? $args['0'] : null;

        if ($file === null) {
            fwrite(STDERR, "usage: data:import <file>\n");
            return 1;
        }

        $this->importer->run($file);
        return 0;
    }
}
```

---

## `RouteCacheCommand`

[`src/Cli/Commands/RouteCacheCommand.php`](https://github.com/bjornbasar/karhu/blob/main/src/Cli/Commands/RouteCacheCommand.php)

Ships with karhu. Compiles the route table to a PHP file so production boots without reflection.

| Method | Command | Description |
|---|---|---|
| `handle(array $args)` | `route:cache` | Compile routes to a cached PHP file for production. |
| `clear(array $args)` | `route:clear` | Remove the route cache file. |

Both accept `--path=` to override the default `cache/routes.php`, resolved relative to the
current working directory. `handle()` reads **`config/controllers.php`** from `getcwd()`, which
must return an array of class names, and creates the cache directory if needed.

Exit codes: `handle()` returns `1` when `config/controllers.php` is missing or does not return an
array, `0` otherwise. `clear()` always returns `0`, whether or not a cache existed.

```bash
vendor/bin/karhu route:cache                      # → cache/routes.php
vendor/bin/karhu route:cache --path=var/routes.php
vendor/bin/karhu route:clear
```

!!! warning "Rebuild the cache on every deploy"
    A stale cache serves the old route table, and newly added routes 404 with no other symptom.
    Run `route:cache` as a build or release step, after the code is in place. See
    [Deployment](../deployment.md).
