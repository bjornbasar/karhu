# CLI

karhu includes a built-in CLI command dispatcher. No symfony/console required.

## Running commands

```bash
bin/karhu <command> [options]
bin/karhu list                # show all commands
bin/karhu help <command>      # show help for a command
bin/karhu route:cache         # built-in: compile route cache
bin/karhu route:clear         # built-in: remove route cache
```

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
bin/karhu greet --name=Bjorn
# Hello, Bjorn!
```

## Argument parsing

- `--key=value` → `$args['key']` = `'value'`
- `--flag` → `$args['flag']` = `true`
- Positional args → `$args['0']`, `$args['1']`, etc.
