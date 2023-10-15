<?php

declare(strict_types=1);

namespace NGSOFT\Container\Resolvers;

use NGSOFT\Container\ContainerInterface;

abstract class ContainerResolver
{
    public const PRIORITY_LOW    = 32;
    public const PRIORITY_MEDIUM = 64;
    public const PRIORITY_HIGH   = 128;

    public function __construct(
        protected ContainerInterface $container
    ) {
    }

    /**
     * Set the default priority.
     */
    public function getDefaultPriority(): int
    {
        return self::PRIORITY_MEDIUM;
    }

    /**
     * Resolves an entry from the container.
     */
    abstract public function resolve(mixed $value): mixed;
}
