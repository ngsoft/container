<?php

declare(strict_types=1);

namespace NGSOFT\Container;

use NGSOFT\Container\Exceptions\ContainerError;
use NGSOFT\Container\Exceptions\NotFound;
use NGSOFT\Traits\StringableObject;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

final class StackableContainer implements ContainerInterface, \Stringable
{
    use StringableObject;

    protected ?ContainerInterface $container = null;
    protected ?self $next                    = null;

    public function __construct(
        ContainerInterface|array $containers
    ) {
        if ( ! is_array($containers))
        {
            $containers = [$containers];
        }

        if (empty($containers))
        {
            throw new \InvalidArgumentException('No container supplied');
        }

        foreach (array_values($containers) as $index => $container)
        {
            if ( ! $container instanceof ContainerInterface)
            {
                throw new \InvalidArgumentException(sprintf('Invalid $containers[%d] type: %s expected, %s given', $index, ContainerInterface::class, \get_debug_type($container)));
            }
            $this->addContainer($container);
        }
    }

    /**
     * Check if container already stacked.
     */
    public function hasContainer(ContainerInterface $container): bool
    {
        if ($this->container === $container)
        {
            return true;
        }
        return $this->next?->hasContainer($container) ?? false;
    }

    /**
     * Stacks a new Container on top.
     */
    public function addContainer(ContainerInterface $container): void
    {
        if ($container instanceof self)
        {
            throw new ContainerError(sprintf('%s instances cannot be stacked.', self::class));
        }

        if ($this->hasContainer($container))
        {
            throw new ContainerError(sprintf('Cannot stack the same container (%s#%d) twice.', get_class($container), spl_object_id($container)));
        }

        if ($this->container)
        {
            $next       = new self($this->container);
            $next->next = $this->next;
            $this->next = $next;
        }
        $this->container = $container;
    }

    public function get(string $id): mixed
    {
        try
        {
            return $this->container->get($id);
        } catch (ContainerExceptionInterface $prev)
        {
            if ($this->next)
            {
                return $this->next->get($id);
            }
            throw NotFound::for($id, $prev);
        }
    }

    public function has(string $id): bool
    {
        return $this->container->has($id) || ($this->next?->has($id) ?? false);
    }
}
