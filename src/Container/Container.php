<?php

declare(strict_types=1);

namespace NGSOFT\Container;

use NGSOFT\Container\Exception\CircularDependencyException;
use NGSOFT\Container\Exception\NotFoundException;
use NGSOFT\Container\Exception\ResolverException;
use NGSOFT\Container\Internal\UnmatchedEntry;
use Psr\Container\ContainerExceptionInterface;

final class Container implements Version, ContainerInterface
{
    /** @var array<string,string> */
    private array $aliases     = [];
    /** @var array<string,ServiceProvider> */
    private array $services    = [];

    /** @var array<string,bool> */
    private array $loaded      = [];

    /** @var array<string,bool> */
    private array $resolve     = [];

    /** @var array<string, \Closure> */
    private array $definitions = [];

    /** @var array<string, mixed> */
    private array $shared      = [];

    /** @var array<int, Resolver[]> */
    private array $resolvers   = [];

    public function __construct(?iterable $definitions = null)
    {
        $this->shared[__CLASS__] = $this;
        $this->aliases[ContainerInterface::class]
                                 = $this->aliases[\Psr\Container\ContainerInterface::class]
                                 = __CLASS__;

        $this->addResolver(new RequiredResolver($this));

        $this->addResolver(new CallableResolver($this));

        if ($definitions)
        {
            $this->setMany($definitions);
        }
    }

    public function addResolver(Resolver $resolver, int $priority = 128): static
    {
        $this->resolvers[$priority] ??= [];
        $this->resolvers[$priority][] = $resolver;
        krsort($this->resolvers);
        return $this;
    }

    public function make(string $id, array $parameters = []): mixed
    {
        try
        {
            $abstract = $this->a($id);

            if (isset($this->definitions[$abstract]))
            {
                return $this->resolve($abstract, $parameters);
            }

            $value    = $this->r($abstract, $parameters);

            if (null !== $value)
            {
                return $value;
            }
        } catch (ContainerExceptionInterface $previous)
        {
            throw NotFoundException::of($id, $previous);
        }

        throw NotFoundException::of($id);
    }

    public function call(array|object|string $callable, array $parameters = []): mixed
    {
        try
        {
            if (is_string($callable))
            {
                $cm       = preg_split('#[:@]+#', $callable);

                $callable = match (count($cm))
                {
                    2       => $cm,
                    1       => $cm[0],
                    default => throw new ResolverException('Invalid Callable: ' . $callable),
                };
            }
            return $this->r($callable, $parameters);
        } catch (ContainerExceptionInterface $previous)
        {
            throw ResolverException::invalidCallable($callable, $previous);
        }
    }

    public function set(string $id, mixed $value): void
    {
        $id                = $this->a($id);
        unset($this->shared[$id]);

        if ($value instanceof \Closure)
        {
            $this->definitions[$id] = $value;
            return;
        }
        $this->shared[$id] = $value;
    }

    public function get(string $id)
    {
        try
        {
            // services can set/override aliases
            $this->load($this->a($id));
            $abstract = $this->a($id);
            return $this->shared[$abstract] ??= $this->make($abstract);
        } catch (ContainerExceptionInterface $previous)
        {
            if ($previous instanceof NotFoundException)
            {
                throw $previous;
            }
            throw NotFoundException::of($id, $previous);
        }
    }

    public function alias(array|string $alias, string $id): void
    {
        if ( ! is_array($alias))
        {
            $alias = [$alias];
        }
        $alias = array_values(
            array_filter(
                array_unique($alias),
                fn ($a) => $a !== $id
            )
        );

        $this->aliases += array_fill_keys($alias, $id);
    }

    public function has(string $id): bool
    {
        $id = $this->a($id);
        return isset($this->shared[$id])
            || isset($this->services[$id])
            || isset($this->definitions[$id])
            || $this->canResolve($id);
    }

    public function setMany(iterable $definitions): void
    {
        foreach ($definitions as $id => $value)
        {
            $this->set($id, $value);
        }
    }

    public function register(ServiceProvider $service): void
    {
        if (empty($service->provides()))
        {
            return;
        }

        foreach (array_unique($service->provides()) as $id)
        {
            unset($this->loaded[$id], $this->shared[$id]);
            $this->services[$id] = $service;
        }
    }

    private function canResolve(string $id): bool
    {
        foreach ($this->resolvers as $resolvers)
        {
            foreach ($resolvers as $resolver)
            {
                if ($resolver instanceof CanResolve && $resolver->canResolve($id))
                {
                    return true;
                }
            }
        }

        return false;
    }

    private function load(string $id): void
    {
        if (
            ! isset($this->loaded[$id])
            && $provider = $this->services[$id] ?? null
        ) {
            foreach ($provider->provides() as $service)
            {
                $this->loaded[$service] = true;
            }
            $provider->register($this);
        }
    }

    private function a(string $id): string
    {
        if (isset($this->aliases[$id]))
        {
            return $this->a($this->aliases[$id]);
        }
        return $id;
    }

    private function r(mixed $value, array $parameters = []): mixed
    {
        foreach ($this->resolvers as $resolvers)
        {
            foreach ($resolvers as $resolver)
            {
                $resolved = $resolver->resolve($value, $parameters);

                if ($resolved instanceof UnmatchedEntry)
                {
                    continue;
                }

                return $resolved;
            }
        }

        throw new ResolverException('Cannot resolve value.');
    }

    private function resolve(string $id, array $parameters = []): mixed
    {
        if ( ! empty($this->resolve[$id]))
        {
            throw CircularDependencyException::of($id);
        }

        try
        {
            $this->resolve[$id] = true;
            return $this->r($this->definitions[$id] ?? $id, $parameters);
        } finally
        {
            $this->resolve[$id] = false;
        }
    }
}
