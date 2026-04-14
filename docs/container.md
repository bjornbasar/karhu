# Container

PSR-11-shape auto-wiring DI container. Resolves constructor dependencies via reflection.

## Registering services

```php
$app = new \Karhu\App();
$container = $app->container();

// Instance (singleton)
$container->set(MyService::class, new MyService());

// Factory (called once, cached)
$container->factory(MyService::class, fn () => new MyService('config'));

// Interface → concrete binding
$container->bind(UserRepositoryInterface::class, PdoUserRepository::class);
```

## Auto-wiring

Concrete classes with typed constructor parameters are resolved automatically:

```php
final class OrderService {
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly LoggerInterface $logger,
    ) {}
}

// Just ask for it — dependencies resolve recursively
$service = $container->get(OrderService::class);
```

## Default values and nullable parameters

- Parameters with default values use those defaults when the type isn't resolvable.
- Nullable parameters resolve to `null` when the dependency isn't available (but will resolve the dependency if it is available).

## Circular dependency detection

The container detects circular dependencies and throws `ContainerException` with a clear message.
