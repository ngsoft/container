<?php

declare(strict_types=1);

namespace NGSOFT\Container\Resolvers;

use NGSOFT\Container\Priority;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

/**
 * Injects Logger.
 */
class LoggerAwareResolver extends ContainerResolver
{
    public function resolve(mixed $value): mixed
    {
        if ($value instanceof LoggerAwareInterface)
        {
            $value->setLogger($this->container->get(LoggerInterface::class));
        }

        return $value;
    }

    public function getDefaultPriority(): int
    {
        return Priority::LOW->value;
    }
}
