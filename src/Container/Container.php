<?php

declare(strict_types=1);

namespace Panulat\Container;

use ReflectionClass;
use ReflectionNamedType;

/**
 * @phpstan-type ParameterMetadata array{name: string, type: ?string, builtin: bool, hasDefault: bool, default: mixed}
 */
final class Container implements ContainerInterface
{
    /** @var array<string, array{concrete: mixed, singleton: bool, factory: bool}> */
    private array $bindings = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var array<string, array<string, mixed>> */
    private array $contextual = [];

    /** @var array<string, list<ParameterMetadata>> */
    private array $metadata = [];

    /** @var list<class-string> */
    private array $buildStack = [];

    public function instance(string $id, mixed $instance): void
    {
        $this->instances[$id] = $instance;
    }

    public function bind(string $id, mixed $concrete = null): void
    {
        unset($this->instances[$id]);

        $this->bindings[$id] = ['concrete' => $concrete ?? $id, 'singleton' => false, 'factory' => false];
    }

    public function singleton(string $id, mixed $concrete = null): void
    {
        unset($this->instances[$id]);

        $this->bindings[$id] = ['concrete' => $concrete ?? $id, 'singleton' => true, 'factory' => false];
    }

    public function factory(string $id, callable $factory): void
    {
        unset($this->instances[$id]);

        $this->bindings[$id] = ['concrete' => $factory, 'singleton' => false, 'factory' => true];
    }

    public function contextual(string $consumer, string $needs, mixed $give): void
    {
        $this->contextual[$consumer][$needs] = $give;
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (isset($this->bindings[$id])) {
            $binding = $this->bindings[$id];
            $object = $this->resolveConcrete($binding['concrete'], $id, $binding['factory']);

            if ($binding['singleton']) {
                $this->instances[$id] = $object;
            }

            return $object;
        }

        if (! class_exists($id)) {
            throw new NotFoundException(sprintf('Service [%s] was not found.', $id));
        }

        /** @var class-string $id */
        return $this->build($id);
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->bindings[$id]) || class_exists($id);
    }

    /** @param array<string, list<ParameterMetadata>> $metadata */
    public function loadMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
    }

    /** @return array<string, list<ParameterMetadata>> */
    public function exportMetadata(): array
    {
        return $this->metadata;
    }

    /** @param class-string $class */
    public function warm(string $class): void
    {
        $seen = [];
        $this->warmClass($class, $seen);
    }

    /**
     * @param class-string $class
     * @param array<class-string, true> $seen
     */
    private function warmClass(string $class, array &$seen): void
    {
        if (isset($seen[$class])) {
            return;
        }

        $seen[$class] = true;
        $reflection = new ReflectionClass($class);

        if (! $reflection->isInstantiable()) {
            return;
        }

        $parameters = $this->metadata[$class] ?? $this->inspectConstructor($reflection, $class);

        foreach ($parameters as $parameter) {
            $type = $parameter['type'];
            if ($type !== null && ! $parameter['builtin'] && class_exists($type)) {
                /** @var class-string $type */
                $this->warmClass($type, $seen);
            }
        }
    }

    private function resolveConcrete(mixed $concrete, string $id, bool $factory): mixed
    {
        if ($factory && is_callable($concrete)) {
            return $concrete($this);
        }

        if (is_callable($concrete) && ! is_string($concrete)) {
            return $concrete($this);
        }

        if (is_string($concrete)) {
            if (! class_exists($concrete)) {
                if ($concrete === $id) {
                    throw new NotFoundException(sprintf('Service [%s] was not found.', $id));
                }

                return $concrete;
            }

            /** @var class-string $concrete */
            return $this->build($concrete);
        }

        return $concrete;
    }

    /** @param class-string $class */
    private function build(string $class): object
    {
        if (in_array($class, $this->buildStack, true)) {
            throw new ContainerException(sprintf('Circular dependency while building [%s].', $class));
        }

        $this->buildStack[] = $class;
        $reflection = new ReflectionClass($class);

        if (! $reflection->isInstantiable()) {
            array_pop($this->buildStack);
            throw new ContainerException(sprintf('Class [%s] is not instantiable.', $class));
        }

        $parameters = $this->metadata[$class] ?? $this->inspectConstructor($reflection, $class);
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $dependencies[] = $this->resolveParameter($class, $parameter);
        }

        array_pop($this->buildStack);

        return $reflection->newInstanceArgs($dependencies);
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @param class-string $class
     * @return list<ParameterMetadata>
     */
    private function inspectConstructor(ReflectionClass $reflection, string $class): array
    {
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $this->metadata[$class] = [];
        }

        $parameters = [];

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            $parameters[] = [
                'name' => $parameter->getName(),
                'type' => $type instanceof ReflectionNamedType ? $type->getName() : null,
                'builtin' => $type instanceof ReflectionNamedType && $type->isBuiltin(),
                'hasDefault' => $parameter->isDefaultValueAvailable(),
                'default' => $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null,
            ];
        }

        return $this->metadata[$class] = $parameters;
    }

    /** @param ParameterMetadata $parameter */
    private function resolveParameter(string $consumer, array $parameter): mixed
    {
        $nameKey = '$' . $parameter['name'];

        if (array_key_exists($nameKey, $this->contextual[$consumer] ?? [])) {
            return $this->contextual[$consumer][$nameKey];
        }

        $type = $parameter['type'];

        if ($type !== null && array_key_exists($type, $this->contextual[$consumer] ?? [])) {
            return $this->contextual[$consumer][$type];
        }

        if ($type !== null && ! $parameter['builtin']) {
            return $this->get($type);
        }

        if ($parameter['hasDefault']) {
            return $parameter['default'];
        }

        throw new ContainerException(sprintf('Cannot resolve parameter [$%s] for [%s].', $parameter['name'], $consumer));
    }
}
