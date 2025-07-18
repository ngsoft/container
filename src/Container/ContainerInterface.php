<?php

declare(strict_types=1);

namespace NGSOFT\Container;

interface ContainerInterface extends \Psr\Container\ContainerInterface
{
    /**
     * Resolves an entry by its name. If given a class name, it will return a fresh instance of that class.
     */
    public function make(string $id, array $parameters = []): mixed;

    /**
     * Call the given function using the given parameters.
     */
    public function call(array|object|string $callable, array $parameters = []): mixed;

    /**
     * Add a definition to the container.
     */
    public function set(string $id, mixed $value): void;

    /**
     * Adds multiple definitions.
     */
    public function setMany(iterable $definitions): void;

    /**
     * Register a service.
     */
    public function register(ServiceProvider $service): void;

    /**
     * Alias an entry to a different name.
     */
    public function alias(array|string $alias, string $id): void;
}
