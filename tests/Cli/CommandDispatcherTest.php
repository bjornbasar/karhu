<?php

declare(strict_types=1);

namespace Karhu\Tests\Cli;

use Karhu\Attributes\Command;
use Karhu\Cli\CommandDispatcher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/* --- Stub command classes --- */

final class StubGreetCommand
{
    /** @var array<string, string|true> Captured args for assertion */
    public static array $lastArgs = [];

    #[Command('greet', 'Say hello')]
    public function handle(array $args): int
    {
        self::$lastArgs = $args;
        return 0;
    }
}

final class StubFailCommand
{
    #[Command('fail', 'Always fails')]
    public function handle(array $args): int
    {
        return 1;
    }
}

/* --- Tests --- */

final class CommandDispatcherTest extends TestCase
{
    #[Test]
    public function scan_discovers_commands(): void
    {
        $d = new CommandDispatcher();
        $d->scanCommands([StubGreetCommand::class, StubFailCommand::class]);

        $cmds = $d->commands();
        $this->assertArrayHasKey('greet', $cmds);
        $this->assertSame('Say hello', $cmds['greet']['description']);
        $this->assertArrayHasKey('fail', $cmds);
    }

    #[Test]
    public function dispatch_calls_handler(): void
    {
        $d = new CommandDispatcher();
        $d->scanCommands([StubGreetCommand::class]);

        $exit = $d->dispatch(['karhu', 'greet', '--name=world']);
        $this->assertSame(0, $exit);
        $this->assertSame('world', StubGreetCommand::$lastArgs['name']);
    }

    #[Test]
    public function dispatch_returns_handler_exit_code(): void
    {
        $d = new CommandDispatcher();
        $d->scanCommands([StubFailCommand::class]);

        $exit = $d->dispatch(['karhu', 'fail']);
        $this->assertSame(1, $exit);
    }

    #[Test]
    public function unknown_command_returns_1(): void
    {
        $d = new CommandDispatcher();

        ob_start();
        $exit = $d->dispatch(['karhu', 'nonexistent']);
        ob_end_clean();

        $this->assertSame(1, $exit);
    }

    #[Test]
    public function list_command_returns_0(): void
    {
        $d = new CommandDispatcher();
        $d->scanCommands([StubGreetCommand::class]);

        ob_start();
        $exit = $d->dispatch(['karhu', 'list']);
        ob_end_clean();

        $this->assertSame(0, $exit);
    }

    #[Test]
    public function no_args_shows_list(): void
    {
        $d = new CommandDispatcher();

        ob_start();
        $exit = $d->dispatch(['karhu']);
        ob_end_clean();

        $this->assertSame(0, $exit);
    }

    #[Test]
    public function parse_args_named_and_positional(): void
    {
        $parsed = CommandDispatcher::parseArgs(['file.txt', '--verbose', '--port=8080']);

        $this->assertSame('file.txt', $parsed['0']);
        $this->assertTrue($parsed['verbose']);
        $this->assertSame('8080', $parsed['port']);
    }

    #[Test]
    public function add_command_explicit(): void
    {
        $d = new CommandDispatcher();
        $d->scanCommands([StubGreetCommand::class]);
        $d->addCommand('custom', StubGreetCommand::class . '::handle', 'Custom desc');

        $this->assertArrayHasKey('custom', $d->commands());
    }
}
