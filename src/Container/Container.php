<?php

declare(strict_types=1);

namespace Karhu\Container;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * PSR-11-shape auto-wiring DI container.
 *
 * Resolves constructor dependencies via reflection. Supports:
 * - Singleton bindings (shared instances)
 * - Factory closures
 * - Interface-to-concrete bindings
 * - Auto-wiring of concrete classes
 * - Circular dependency detection
 */
final class Container implements ContainerInterface
{
    /** @var array<string, mixed> Shared singleton instances */
    private array $instances = [];

    /** @var array<string, callable(): mixed> Factory closures */
    private array $factories = [];

    /** @var array<string, class-string> Interface → concrete mappings */
    private array $bindings = [];

    /** @var array<string, true> Currently resolving (circular-dep guard) */
    private array $resolving = [];

    /**
     * Register a pre-built instance (singleton).
     */
    public function set(string $id, mixed $instance): void
    {
        $this->instances[$id] = $instance;
    }

    /**
     * Register a factory closure. Called once; result cached as singleton.
     *
     * @param callable(): mixed $factory
     */
    public function factory(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
    }

    /**
     * Bind an interface/abstract to a concrete class.
     *
     * @param class-string $concrete
     */
    public function bind(string $abstract, string $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    public function get(string $id): mixed
    {
        // Already resolved as singleton
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        // Has a factory
        if (isset($this->factories[$id])) {
            $this->instances[$id] = ($this->factories[$id])();
            unset($this->factories[$id]);
            return $this->instances[$id];
        }

        // Has an interface binding
        if (isset($this->bindings[$id])) {
            $instance = $this->resolve($this->bindings[$id]);
            $this->instances[$id] = $instance;
            return $instance;
        }

        // Try auto-wiring
        if (class_exists($id)) {
            $instance = $this->resolve($id);
            $this->instances[$id] = $instance;
            return $instance;
        }

        throw new NotFoundException("No entry found for '{$id}'.");
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->instances)
            || isset($this->factories[$id])
            || isset($this->bindings[$id])
            || class_exists($id);
    }

    /**
     * Auto-wire a concrete class by resolving its constructor dependencies.
     *
     * @param class-string $class
     */
    private function resolve(string $class): object
    {
        if (isset($this->resolving[$class])) {
            throw new ContainerException(
                "Circular dependency detected while resolving '{$class}'."
            );
        }

        $this->resolving[$class] = true;

        try {
            $ref = new ReflectionClass($class);

            if (!$ref->isInstantiable()) {
                throw new ContainerException("'{$class}' is not instantiable.");
            }

            $constructor = $ref->getConstructor();

            if ($constructor === null) {
                return $ref->newInstance();
            }

            $params = array_map(
                fn(ReflectionParameter $param) => $this->resolveParameter($param, $class),
                $constructor->getParameters(),
            );

            return $ref->newInstanceArgs($params);
        } finally {
            unset($this->resolving[$class]);
        }
    }

    /** Resolve a single constructor parameter. */
    private function resolveParameter(ReflectionParameter $param, string $forClass): mixed
    {
        $type = $param->getType();

        // Typed parameter pointing to a class/interface — recurse
        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            $typeName = $type->getName();
            return $this->get($typeName);
        }

        // Has a default value
        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        // Nullable — return null
        if ($type !== null && $type->allowsNull()) {
            return null;
        }

        throw new ContainerException(
            "Cannot resolve parameter '\${$param->getName()}' "
            . "of '{$forClass}': no type hint, no default, not nullable."
        );
    }
}
