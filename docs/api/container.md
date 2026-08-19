# `Karhu\Container`

A PSR-11-shape auto-wiring dependency injection container. It resolves constructor dependencies
by reflection, caches everything it builds as a singleton, and detects circular dependencies.

---

## `Container`

[`src/Container/Container.php`](https://github.com/bjornbasar/karhu/blob/main/src/Container/Container.php)

Implements [`ContainerInterface`](#containerinterface). No constructor.

| Method | Returns | Description |
|---|---|---|
| `set(string $id, mixed $instance)` | `void` | Register a pre-built instance. |
| `factory(string $id, callable $factory)` | `void` | Register a factory. Called **once**; the result is cached. |
| `bind(string $abstract, string $concrete)` | `void` | Map an interface (or abstract) to a concrete class. |
| `get(string $id)` | `mixed` | Resolve an entry. |
| `has(string $id)` | `bool` | Whether `get()` would succeed. |

**Throws** — `get()` raises [`NotFoundException`](#notfoundexception) when nothing is registered
and the id is not an existing class. Resolution raises
[`ContainerException`](#containerexception) for a circular dependency, a non-instantiable class,
or a constructor parameter that cannot be resolved.

### Resolution order

`get()` tries, in order:

1. an instance registered with `set()`
2. a `factory()` — invoked, cached, and the factory then discarded
3. a `bind()` mapping — the concrete class is auto-wired
4. **auto-wiring**, if `class_exists($id)`
5. otherwise `NotFoundException`

Everything resolved is cached, so **every entry behaves as a singleton**. There is no "new
instance every time" mode; register a factory that returns a builder if you need one.

### Factories receive the container

```php
$container->factory(PDO::class, fn(Container $c) => new PDO(
    $c->get(Config::class)->get('database.dsn'),
));
```

The container is passed as the first argument, so a factory can resolve other entries without
capturing `$container` by `use`. Zero-argument factories still work — PHP discards the extra
positional argument.

### Auto-wiring rules

For each constructor parameter:

- **Class/interface type** — resolved recursively via `get()`. If that throws `NotFoundException`,
  the parameter's **default value** is used; failing that, `null` if the parameter is nullable;
  otherwise the exception propagates.
- **Built-in type** — the default value, or `null` if nullable, otherwise `ContainerException`.

That fallback is what makes this legal without registering a queue:

```php
public function __construct(private ?QueueInterface $queue = null) {}
```

PSR-11 does not mandate the behaviour, but league/container and PHP-DI both do it, and it is the
difference between commands staying clean and every CLI entry point duplicating DI bootstrap.

!!! warning "`has()` returns `true` for any existing class"
    `has()` ends with `class_exists($id)`, so it reports `true` for concrete classes that were
    never registered — they *would* auto-wire. It correctly returns `false` for an **unbound
    interface**, because `class_exists()` is `false` for interfaces. That is exactly why
    [`ErrorHandler`](http.md#errorhandler) is an interface.

```php
$container = new Container();

$container->set(Config::class, $config);                       // instance
$container->bind(UserRepositoryInterface::class, PdoUsers::class);  // interface → concrete
$container->factory(Mailer::class, fn(Container $c) => new SmtpMailer(/* ... */));

$rbac = $container->get(Rbac::class);   // auto-wired: needs UserRepositoryInterface, which is bound
```

---

## `ContainerInterface`

[`src/Container/ContainerInterface.php`](https://github.com/bjornbasar/karhu/blob/main/src/Container/ContainerInterface.php)

The PSR-11-shape contract. karhu declares its own rather than requiring `psr/container`
([ADR 0003](../adr/0003-zero-runtime-deps.md)).

| Method | Returns | Description |
|---|---|---|
| `get(string $id)` | `mixed` | Resolve an entry. |
| `has(string $id)` | `bool` | Whether the entry is resolvable. |

Method signatures match PSR-11, so a `psr/container` type-hint can be satisfied by an adapter of
about three lines if a third-party library demands the real interface.

---

## `ContainerException`

[`src/Container/ContainerException.php`](https://github.com/bjornbasar/karhu/blob/main/src/Container/ContainerException.php)

`class ContainerException extends \RuntimeException`

The entry exists but cannot be built. Raised for circular dependencies, non-instantiable classes
(abstract, or a private constructor), and unresolvable constructor parameters.

---

## `NotFoundException`

[`src/Container/NotFoundException.php`](https://github.com/bjornbasar/karhu/blob/main/src/Container/NotFoundException.php)

`class NotFoundException extends ContainerException`

No entry is registered under the id, and it is not an auto-wirable class. This is a **wiring
bug** — it is not an HTTP 404. For that, see
[`Karhu\Http\NotFoundException`](http.md#notfoundexception).
