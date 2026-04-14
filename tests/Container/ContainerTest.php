<?php

declare(strict_types=1);

namespace Karhu\Tests\Container;

use Karhu\Container\Container;
use Karhu\Container\ContainerException;
use Karhu\Container\NotFoundException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/* --- Stub classes for auto-wiring tests --- */

interface StubServiceInterface
{
    public function value(): string;
}

final class StubService implements StubServiceInterface
{
    public function value(): string
    {
        return 'stub';
    }
}

final class StubNoDeps
{
    public string $created = 'yes';
}

final class StubWithDep
{
    public function __construct(public readonly StubNoDeps $dep) {}
}

final class StubWithInterface
{
    public function __construct(public readonly StubServiceInterface $service) {}
}

final class StubWithDefault
{
    public function __construct(public readonly string $name = 'default') {}
}

final class StubWithNullable
{
    public function __construct(public readonly ?StubNoDeps $dep = null) {}
}

final class StubCircularA
{
    public function __construct(public readonly StubCircularB $b) {}
}

final class StubCircularB
{
    public function __construct(public readonly StubCircularA $a) {}
}

/* --- Tests --- */

final class ContainerTest extends TestCase
{
    #[Test]
    public function set_and_get_instance(): void
    {
        $c = new Container();
        $obj = new StubNoDeps();
        $c->set('thing', $obj);

        $this->assertSame($obj, $c->get('thing'));
    }

    #[Test]
    public function has_returns_true_for_registered_and_classes(): void
    {
        $c = new Container();
        $c->set('x', 'value');
        $this->assertTrue($c->has('x'));
        $this->assertTrue($c->has(StubNoDeps::class));
        $this->assertFalse($c->has('nonexistent.thing'));
    }

    #[Test]
    public function factory_called_once_and_cached(): void
    {
        $calls = 0;
        $c = new Container();
        $c->factory('counter', function () use (&$calls) {
            $calls++;
            return new StubNoDeps();
        });

        $a = $c->get('counter');
        $b = $c->get('counter');
        $this->assertSame($a, $b);
        $this->assertSame(1, $calls);
    }

    #[Test]
    public function auto_wire_no_deps(): void
    {
        $c = new Container();
        $obj = $c->get(StubNoDeps::class);
        $this->assertInstanceOf(StubNoDeps::class, $obj);
        $this->assertSame('yes', $obj->created);
    }

    #[Test]
    public function auto_wire_with_dep(): void
    {
        $c = new Container();
        $obj = $c->get(StubWithDep::class);
        $this->assertInstanceOf(StubWithDep::class, $obj);
        $this->assertInstanceOf(StubNoDeps::class, $obj->dep);
    }

    #[Test]
    public function bind_interface_to_concrete(): void
    {
        $c = new Container();
        $c->bind(StubServiceInterface::class, StubService::class);

        $service = $c->get(StubServiceInterface::class);
        $this->assertInstanceOf(StubService::class, $service);
        $this->assertSame('stub', $service->value());
    }

    #[Test]
    public function auto_wire_with_interface_binding(): void
    {
        $c = new Container();
        $c->bind(StubServiceInterface::class, StubService::class);

        $obj = $c->get(StubWithInterface::class);
        $this->assertInstanceOf(StubWithInterface::class, $obj);
        $this->assertSame('stub', $obj->service->value());
    }

    #[Test]
    public function auto_wire_uses_default_values(): void
    {
        $c = new Container();
        $obj = $c->get(StubWithDefault::class);
        $this->assertSame('default', $obj->name);
    }

    #[Test]
    public function auto_wire_nullable_resolves_to_null(): void
    {
        // StubNoDeps exists and is resolvable, but nullable params
        // should still resolve to the dependency when available
        $c = new Container();
        $obj = $c->get(StubWithNullable::class);
        $this->assertInstanceOf(StubNoDeps::class, $obj->dep);
    }

    #[Test]
    public function circular_dependency_throws(): void
    {
        $c = new Container();
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessageMatches('/Circular dependency/');
        $c->get(StubCircularA::class);
    }

    #[Test]
    public function not_found_throws(): void
    {
        $c = new Container();
        $this->expectException(NotFoundException::class);
        $c->get('totally.unknown');
    }

    #[Test]
    public function singleton_behavior_for_auto_wired(): void
    {
        $c = new Container();
        $a = $c->get(StubNoDeps::class);
        $b = $c->get(StubNoDeps::class);
        $this->assertSame($a, $b);
    }
}
