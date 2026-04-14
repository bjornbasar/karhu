<?php

declare(strict_types=1);

namespace Karhu\Cli;

use Karhu\Attributes\Command;
use Karhu\Container\Container;
use ReflectionClass;
use ReflectionMethod;

/**
 * Attribute-based CLI command dispatcher.
 *
 * Scans classes for #[Command] attributes, registers them, and
 * dispatches based on the first CLI argument. No symfony/console
 * dependency — just attribute scanning + argument parsing.
 */
final class CommandDispatcher
{
    /**
     * @var array<string, array{
     *   handler: string,
     *   description: string
     * }>
     */
    private array $commands = [];

    private Container $container;

    public function __construct(?Container $container = null)
    {
        $this->container = $container ?? new Container();
    }

    /**
     * Scan classes for #[Command] attributes and register them.
     *
     * @param list<class-string> $classes
     */
    public function scanCommands(array $classes): void
    {
        foreach ($classes as $class) {
            $ref = new ReflectionClass($class);

            foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $attrs = $method->getAttributes(Command::class);

                foreach ($attrs as $attr) {
                    /** @var Command $cmd */
                    $cmd = $attr->newInstance();
                    $this->commands[$cmd->name] = [
                        'handler' => $class . '::' . $method->getName(),
                        'description' => $cmd->description,
                    ];
                }
            }
        }
    }

    /**
     * Register a command explicitly.
     */
    public function addCommand(string $name, string $handler, string $description = ''): void
    {
        $this->commands[$name] = [
            'handler' => $handler,
            'description' => $description,
        ];
    }

    /**
     * Dispatch from CLI arguments (typically $argv).
     *
     * @param list<string> $argv Raw CLI arguments including the script name
     * @return int Exit code (0 = success)
     */
    public function dispatch(array $argv): int
    {
        $scriptName = $argv[0] ?? 'karhu';
        $commandName = $argv[1] ?? null;
        $args = array_slice($argv, 2);

        // No command given — show list
        if ($commandName === null || $commandName === 'list') {
            $this->showList($scriptName);
            return 0;
        }

        // Help for a specific command
        if ($commandName === 'help') {
            return $this->showHelp($scriptName, $args[0] ?? null);
        }

        // Check for --help flag
        if (in_array('--help', $args, true) || in_array('-h', $args, true)) {
            return $this->showHelp($scriptName, $commandName);
        }

        // Dispatch the command
        if (!isset($this->commands[$commandName])) {
            $this->stderr("Unknown command: {$commandName}\n");
            $this->stderr("Run '{$scriptName} list' for available commands.\n");
            return 1;
        }

        $parsed = self::parseArgs($args);
        return $this->callHandler($this->commands[$commandName]['handler'], $parsed);
    }

    /** Show all registered commands. */
    private function showList(string $scriptName): void
    {
        $this->stdout("karhu CLI\n\n");
        $this->stdout("Usage: {$scriptName} <command> [arguments]\n\n");
        $this->stdout("Available commands:\n");

        foreach ($this->commands as $name => $info) {
            $desc = $info['description'] !== '' ? "  {$info['description']}" : '';
            $this->stdout(sprintf("  %-20s%s\n", $name, $desc));
        }
        $this->stdout("\n");
    }

    /** Show help for a single command. */
    private function showHelp(string $scriptName, ?string $commandName): int
    {
        if ($commandName === null || !isset($this->commands[$commandName])) {
            $this->stderr("Specify a command: {$scriptName} help <command>\n");
            return 1;
        }

        $info = $this->commands[$commandName];
        $this->stdout("Command: {$commandName}\n");
        if ($info['description'] !== '') {
            $this->stdout("  {$info['description']}\n");
        }
        $this->stdout("\nUsage: {$scriptName} {$commandName} [options]\n\n");
        return 0;
    }

    /**
     * Invoke the command handler method.
     *
     * @param array<string, string|true> $args Parsed arguments
     */
    private function callHandler(string $handler, array $args): int
    {
        [$class, $method] = explode('::', $handler);
        $instance = $this->container->get($class);
        $result = $instance->{$method}($args);

        return is_int($result) ? $result : 0;
    }

    /**
     * Parse CLI arguments into named options + positional args.
     *
     * --name=value → ['name' => 'value']
     * --flag       → ['flag' => true]
     * positional   → ['0' => 'value', '1' => ...]
     *
     * @param list<string> $args
     * @return array<string, string|true>
     */
    public static function parseArgs(array $args): array
    {
        /** @var array<string, string|true> $parsed */
        $parsed = [];
        $positional = 0;

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--')) {
                $key = ltrim($arg, '-');
                if (str_contains($key, '=')) {
                    [$key, $value] = explode('=', $key, 2);
                    $parsed[$key] = $value;
                } else {
                    $parsed[$key] = true;
                }
            } else {
                $parsed[strval($positional)] = $arg;
                $positional++;
            }
        }

        /** @var array<string, string|true> */
        return $parsed;
    }

    /** @return array<string, array{handler: string, description: string}> */
    public function commands(): array
    {
        return $this->commands;
    }

    private function stdout(string $text): void
    {
        fwrite(\STDOUT, $text);
    }

    private function stderr(string $text): void
    {
        fwrite(\STDERR, $text);
    }
}
