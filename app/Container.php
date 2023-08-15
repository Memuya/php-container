<?php

namespace Memuya;

use Closure;
use ArrayAccess;
use ReflectionClass;
use ReflectionParameter;
use ReflectionUnionType;
use Memuya\NotFoundException;
use Psr\Container\ContainerInterface;

class Container implements ContainerInterface, ArrayAccess
{
    public const IS_SINGLETON = true;
    public const IS_NOT_SINGLETON = false;
    
    /**
     * The Container instance.
     *
     * @var ContainerInterface
     */
    private static ContainerInterface $instance;

    /**
     * The bindings in the container.
     *
     * @var array<string, array<string, callable>>
     */
    private array $bindings = [];

    public function __construct()
    {
        static::$instance = $this;
    }

    /**
     * @inheritDoc
     */
    public function get(string $id): mixed
    {
        if (! $this->has($id)) {
            throw new NotFoundException("'{$id}' not found in container");
        }

        if ($this->isSingleton($id)) {
            return $this->bindings[$id]['binding'];
        }

        return $this->bindings[$id]['binding']($this);
    }

    /**
     * @inheritDoc
     */
    public function has(string $id): bool
    {
        return isset($this->bindings[$id]);
    }

    /**
     * Return a single instance of the container.
     *
     * @return static
     */
    public static function getInstance(): static
    {
        if (! isset(static::$instance)) {
            return new static;
        }

        return static::$instance;
    }

    /**
     * Bind a value to the container.
     *
     * @param string $id
     * @param callable $callable
     * @return void
     */
    public function bind(string $id, callable $callable): void
    {
        $this->bindings[$id] = [
            'binding' => $callable,
            'singleton' => self::IS_NOT_SINGLETON,
        ];
    }
    
    /**
     * Bind a singleton to the container. This will resolve the binding right away.
     *
     * @param string $id
     * @param callable $callable
     * @return void
     */
    public function singleton(string $id, callable $callable): void
    {
        $this->bindings[$id] = [
            'binding' => $callable($this),
            'singleton' => self::IS_SINGLETON,
        ];
    }

    /**
     * Check if the given ID was bound as a singleton to the container.
     *
     * @param string $id
     * @return boolean
     */
    public function isSingleton(string $id): bool
    {
        return $this->bindings[$id]['singleton'] === self::IS_SINGLETON;
    }

    /**
     * Remove a binding from the container.
     *
     * @param string $id
     * @return void
     */
    public function remove(string $id): void
    {
        unset($this->bindings[$id]);
    }

    /**
     * Construct a new object and resolve its dependencies.
     *
     * @throws ContainerException
     * @param string $object  The name of the object
     * @param array<string, mixed> $arguments  Arguments to pass into the object's constructor
     * @return mixed
     */
    public function make(string $object, array $arguments = []): mixed
    {
        $reflection = new ReflectionClass($object);

        if (! $reflection->isInstantiable()) {
            throw new ContainerException("'{$object}' is not instantiable.");
        }

        return $reflection->newInstanceArgs(
            $this->resolveDependencies($reflection, $arguments)
        );
    }

    /**
     * Resolve the dependencies for the given reflection class. If $arguments is empty,
     * then the container will attempt to resolve the dependecies for the given class.
     *
     * @throws ContainerException
     * @param ReflectionClass $reflection
     * @param array<string, mixed> $arguments
     * @return array
     */
    private function resolveDependencies(ReflectionClass $reflection, array $arguments = []): array
    {
        // If there's no contructor on the object then there are no dependencies to check.
        if (! $reflection->getConstructor()) {
            return [];
        }

        return array_map(
            function (ReflectionParameter $parameter) use ($reflection, $arguments) {
                $parameterType = $parameter->getType();
                $parameterName = $parameter->getName();

                // If an argument has been passed in that matches the parameter
                // then we'll use that instead of resolving it below.
                if (array_key_exists($parameterName, $arguments)) {
                    return $arguments[$parameterName];
                }

                if ($parameterType instanceof ReflectionUnionType) {
                    throw new ContainerException("Could not resolve argument '{$parameterName}' on '{$reflection->getName()}' as it is a union type.");
                }

                return $parameterType === null || $parameterType->isBuiltin()
                    ? $this->resolveDependency($parameter)
                    : $this->make($parameterType->getName());
            },
            $reflection->getConstructor()->getParameters()
        );
    }

    /**
     * Resolve a dependency for the given argument.
     *
     * @param ReflectionParameter $argument
     * @return mixed
     */
    private function resolveDependency(ReflectionParameter $argument): mixed
    {
        try {
            // Check if we have the argument bound in the container.
            $dependency = $this->get($argument->getName());
        } catch (NotFoundException) {
            // If we didn't have a binding in the container for the argument,
            // we want to get the default value if available or fallback to null.
            $dependency = $argument->isDefaultValueAvailable()
                ? $argument->getDefaultValue()
                : null;
        }

        return $dependency;
    }

    /**
     * @inheritDoc
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    /**
     * @inheritDoc
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    /**
     * @inheritDoc
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->bind(
            $offset,
            $value instanceof Closure ? $value : fn () => $value
        );
    }

    /**
     * @inheritDoc
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->remove($offset);
    }
}