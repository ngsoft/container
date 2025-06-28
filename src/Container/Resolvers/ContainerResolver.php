<?php

declare(strict_types=1);

namespace NGSOFT\Container\Resolvers;

use NGSOFT\Container\ContainerInterface;
use NGSOFT\Container\Priority;

abstract class ContainerResolver
{
    public function __construct(
        protected ContainerInterface $container
    ) {}

    /**
     * Set the default priority.
     */
    public function getDefaultPriority(): int
    {
        return Priority::MEDIUM->value;
    }

    /**
     * Resolves an entry from the container.
     */
    abstract public function resolve(mixed $value): mixed;
}
