<?php

declare(strict_types=1);

namespace NGSOFT\Container;

use NGSOFT\Container\Exceptions\CircularDependencyException;
use NGSOFT\Container\Exceptions\ContainerError;
use NGSOFT\Container\Exceptions\NotFound;
use NGSOFT\Container\Exceptions\ResolverException;
use NGSOFT\Container\Resolvers\ContainerResolver;
use NGSOFT\Container\Resolvers\InjectProperties;
use NGSOFT\Container\Resolvers\LoggerAwareResolver;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface as PsrContainerInterface;

class Container implements ContainerInterface
{
    public const VERSION            = '2.0.1';

    protected const RESOLVERS       = [
        InjectProperties::class,
        LoggerAwareResolver::class,
    ];

    /** @var array<string, string> */
    protected array $aliases        = [];

    /** @var ServiceProvider[] */
    protected array $services       = [];

    /** @var bool[] */
    protected array $loadedServices = [];

    /** @var \Closure[] */
    protected array $definitions    = [];

    /** @var bool[] */
    protected array $resolving      = [];

    /** @var array<string,mixed> */
    protected array $resolved       = [];
    protected ParameterResolver $parameterResolver;
    protected PrioritySet $containerResolvers;

    public function __construct(
        iterable $definitions = []
    ) {
        $this->parameterResolver  = new ParameterResolver($this);
        $this->containerResolvers = PrioritySet::create();

        foreach (self::RESOLVERS as $resolver)
        {
            $this->addContainerResolver(new $resolver($this));
        }

        $this->set(__CLASS__, $this);
        // if extended
        $this->set(static::class, $this);
        $this->alias([PsrContainerInterface::class, ContainerInterface::class, 'Container'], static::class);
        $this->setMany($definitions);
    }

    final public function __sleep(): array
    {
        throw new \BadMethodCallException('Cannot serialize ' . static::class);
    }

    final public function __wakeup(): void
    {
        throw new \BadMethodCallException('Cannot unserialize ' . static::class);
    }

    public function __toString(): string
    {
        return sprintf('object(%s)#%d', get_class($this), spl_object_id($this));
    }

    public function __debugInfo()
    {
        $entries            = [];

        foreach (array_keys($this->resolved) as $id)
        {
            $value        = $this->resolved[$id];

            if (is_object($value))
            {
                $entries[$id] = sprintf('object(%s)#%d', get_class($value), spl_object_id($value));
                continue;
            }

            $entries[$id] = get_debug_type($value);
        }

        $entries['aliases'] = $this->aliases;

        return $entries;
    }

    public function alias(array|string $alias, string $id): void
    {
        $alias = array_unique((array) $alias);

        if (in_array($id, $alias))
        {
            throw new ContainerError(sprintf(
                '[%s] is aliased to itself.',
                $id
            ));
        }
        $this->aliases += array_fill_keys($alias, $id);
    }

    public function has(string $id): bool
    {
        $this->loadService($id);
        $abstract = $this->getAlias($id);
        return array_key_exists($abstract, $this->resolved) || array_key_exists($abstract, $this->definitions) || $this->canResolve($id);
    }

    public function get(string $id): mixed
    {
        try
        {
            $this->loadService($id);
            return $this->resolved[$this->getAlias($id)] ??= $this->resolve($id);
        } catch (ContainerExceptionInterface $prev)
        {
            throw NotFound::for($id, $prev);
        }
    }

    public function make(string $id, array $parameters = []): mixed
    {
        try
        {
            return $this->resolve($id, $parameters);
        } catch (ContainerExceptionInterface $prev)
        {
            throw NotFound::for($id, $prev);
        }
    }

    public function call(array|object|string $callable, array $parameters = []): mixed
    {
        try
        {
            return $this->resolveCall($callable, $parameters);
        } catch (ContainerExceptionInterface $prev)
        {
            throw new ContainerError('Cannot call callable: ' . (is_string($callable) ? $callable : $this->debugString($callable)), previous: $prev);
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
            $this->services[$id] = $service;
            unset($this->resolved[$id], $this->loadedServices[$id]);
        }
    }

    public function set(string $id, mixed $value): void
    {
        $abstract                  = $this->getAlias($id);
        unset($this->resolved[$abstract]);

        if ($value instanceof \Closure)
        {
            $this->definitions[$abstract] = $value;
            return;
        }

        $this->resolved[$abstract] = $value;
    }

    public function setMany(iterable $definitions): void
    {
        foreach ($definitions as $id => $value)
        {
            $this->set($id, $value);
        }
    }

    /**
     * Adds a handler to manage entry resolution (after params have been resolved).
     */
    public function addContainerResolver(ContainerResolver $resolver, ?int $priority = null): void
    {
        if ( ! $priority)
        {
            $priority = $resolver->getDefaultPriority();
        }
        $this->containerResolvers->add($resolver, $priority);
    }

    protected function debugString(array|object $callable): string
    {
        if (is_array($callable))
        {
            $class = is_object($callable[0]) ? get_class($callable[0]) : $callable[0];

            if (count($callable) > 1)
            {
                $method = $callable[1];

                return sprintf('%s::%s()', $class, $method);
            }

            return sprintf('%s', $class);
        }

        return get_class($callable);
    }

    /**
     * Resolves alias.
     */
    protected function getAlias(string $id): string
    {
        return isset($this->aliases[$id]) ? $this->getAlias($this->aliases[$id]) : $id;
    }

    protected function loadService(string $id): void
    {
        if (
            ! isset($this->loadedServices[$id])
            && $provider = $this->services[$id] ?? null
        ) {
            foreach ($provider->provides() as $service)
            {
                $this->loadedServices[$service] = true;
            }

            $provider->register($this);
        }
    }

    protected function resolveCall(array|object|string $callable, array $providedParams): mixed
    {
        // Class@method(), Class::method()
        if (is_string($callable))
        {
            $cm       = preg_split('#[:@]+#', $callable);

            $callable = match (count($cm))
            {
                2       => $cm,
                1       => $cm[0],
                default => throw new ContainerError('Invalid Callable: ' . $callable),
            };
        }

        return $this->parameterResolver->resolve($callable, $providedParams);
    }

    protected function resolve(string $id, array $providedParams = []): mixed
    {
        $resolving = &$this->resolving;

        $abstract  = $this->getAlias($id);

        if (isset($resolving[$abstract]))
        {
            throw new CircularDependencyException(
                sprintf(
                    'Container is already resolving [%s].',
                    $id
                )
            );
        }

        $resolved  = null;

        if ($this->canResolve($abstract))
        {
            $resolving[$abstract] = true;
            $def                  = $this->definitions[$abstract] ?? null;

            if ($def instanceof \Closure)
            {
                $resolved = $this->parameterResolver->resolve($def, $providedParams);
            } elseif (Utils::isInstantiable($abstract))
            {
                $resolved = $this->parameterResolver->resolve($abstract, $providedParams);
            }

            unset($resolving[$abstract]);
        }

        if (is_null($resolved))
        {
            throw new ResolverException(
                sprintf(
                    'Cannot resolve [%s]',
                    $id
                )
            );
        }

        /** @var ContainerResolver $resolver */
        foreach ($this->containerResolvers as $resolver)
        {
            $resolved = $resolver->resolve($resolved);
        }

        return $resolved;
    }

    protected function canResolve(string $id): bool
    {
        return $this->parameterResolver->canResolve($id, $this->definitions[$id] ?? null);
    }
}
