<?php

declare(strict_types=1);

namespace NGSOFT\Container;

use NGSOFT\Container\Exception\ResolverException;
use NGSOFT\Container\Internal\Parameter;
use NGSOFT\Container\Internal\UnmatchedEntry;
use NGSOFT\Reflection\Reflect;
use NGSOFT\Reflection\ReflectParameter;
use Psr\Container\ContainerExceptionInterface;

class CallableResolver implements Resolver, CanResolve
{
    private Reflect $reflect;

    public function __construct(
        private readonly Container $container
    ) {
        $this->reflect = new Reflect();
    }

    public function resolve(mixed $value, array $params): mixed
    {
        static $unmatched = new UnmatchedEntry();

        $this->checkParams($params);

        try
        {
            $reflector = $this->reflect->getReflector($value);
            $missing   = $this->reflect->reflect($value);
        } catch (\ReflectionException)
        {
            return $unmatched;
        }

        $class            = null;
        $method           = null;
        $fn               = false;

        if (is_string($value))
        {
            if ( ! $this->instantiable($value))
            {
                return $unmatched;
            }

            if ( ! $this->hasConstructor($value))
            {
                if ( ! empty($params))
                {
                    throw new ResolverException(
                        sprintf('too many parameters provided for %s.', $value)
                    );
                }

                return new $value();
            }

            $class = $value;
        } elseif ($value instanceof \Closure)
        {
            $fn = true;
        }

        if (is_array($value) && 2 === count($value))
        {
            [$class, $method] = $value;

            if (is_object($class))
            {
                $class = get_class($class);
                $value = $value[0];
            }
        }

        $all              = [];
        $variadic         = (end($missing) ?: null)?->isVariadic();
        $isList           = array_is_list($params);
        $isStatic         = false;

        if ($reflector instanceof \ReflectionMethod)
        {
            if ( ! $reflector->isPublic())
            {
                return $unmatched;
            }
            $isStatic = $reflector->isStatic();
        }

        if (count($params) > count($missing) && ! $variadic)
        {
            throw new ResolverException(
                sprintf('too many parameters provided for %s.', is_string($value)
                    ? $value
                    : get_debug_type($value))
            );
        }

        if ($ok = count($missing) === count($params))
        {
            $all = $params;
        }

        if ( ! $ok)
        {
            // fill missing params
            $keys           = array_keys($missing);
            $providedParams = $this->parseParameters($params);

            $index          = -1;

            /**
             * @var string           $name
             * @var ReflectParameter $def
             */
            foreach ($keys as $name)
            {
                ++$index;
                $def = $missing[$name];

                if ($isList)
                {
                    /** @var ?Parameter $provided */
                    $provided = $providedParams[0] ?? null;

                    if ( ! array_key_exists($index, $all))
                    {
                        if ($provided && (
                            in_array($provided->getType(), $def->getTypes())
                                || in_array('mixed', $def->getTypes())
                        ))
                        {
                            $all[$index] = $provided->getValue();
                            array_splice($providedParams, 0, 1);
                            unset($missing[$name], $params[$provided->getIndex()]);
                            continue;
                        }

                        // default value
                        if ($def->hasDefaultValue())
                        {
                            unset($missing[$name]);
                            $all[$index] = $def->getDefaultValue();
                            continue;
                        }

                        // provided not matching
                        if ( ! $def->isOptional())
                        {
                            if ($def->isNullable())
                            {
                                $all[$index] = null;
                                unset($missing[$name]);
                                continue;
                            }

                            $entry       = $this->tryGetEntryFromContainer($def->getTypes());

                            if (null === $entry)
                            {
                                // cannot match
                                return $unmatched;
                            }

                            $all[$index] = $entry;
                            unset($missing[$name]);
                            continue;
                        }

                        // is optional
                        if ($def->isVariadic())
                        {
                            unset($missing[$name]);
                            break;
                        }

                        if ($def->isNullable())
                        {
                            $all[$index] = null;
                            unset($missing[$name]);
                            continue;
                        }

                        // we cannot match
                        if ($this->hasBuiltin($def->getTypes()))
                        {
                            return $unmatched;
                        }
                    }

                    continue;
                }

                // not a list
                if ( ! array_key_exists($name, $all))
                {
                    /** @var ?Parameter $provided */
                    if ($provided = $providedParams[$name] ?? null)
                    {
                        $all[$name] = $provided->getValue();
                        unset(
                            $missing[$name],
                            $providedParams[$provided->getIndex()],
                            $providedParams[$name]
                        );
                        continue;
                    }

                    // default value
                    if ($def->hasDefaultValue())
                    {
                        unset($missing[$name]);
                        $all[$name] = $def->getDefaultValue();
                        continue;
                    }

                    // provided not matching
                    if ( ! $def->isOptional())
                    {
                        if ($def->isNullable())
                        {
                            $all[$name] = null;
                            unset($missing[$name]);
                            continue;
                        }

                        $entry      = $this->tryGetEntryFromContainer($def->getTypes());

                        if (null === $entry)
                        {
                            // cannot match
                            return $unmatched;
                        }

                        $all[$name] = $entry;
                        unset($missing[$name]);
                        continue;
                    }

                    // is optional
                    if ($def->isVariadic())
                    {
                        unset($missing[$name]);
                        break;
                    }

                    if ($def->isNullable())
                    {
                        $all[$index] = null;
                        unset($missing[$name]);
                        continue;
                    }

                    // we cannot match
                    if ($this->hasBuiltin($def->getTypes()))
                    {
                        return $unmatched;
                    }
                }
            }

            $ok             = ! count($missing);
        }

        if ($ok)
        {
            if ($variadic)
            {
                $all = array_values($all);

                if ( ! empty($providedParams))
                {
                    $all = [
                        ...$all,
                        ...array_map(fn (Parameter $p) => $p->getValue(), $providedParams),
                    ];
                }
            }

            if ($fn)
            {
                return $value(...$all);
            }

            if ($class)
            {
                if ( ! $method)
                {
                    return new $class(...$all);
                }

                if ($isStatic)
                {
                    return $class::{$method}(...$all);
                }

                $instance = $value;

                // class instance
                if (is_string($instance) && class_exists($instance))
                {
                    // instantiate class
                    $instance = $this->container->make($value);
                }

                if (is_object($instance))
                {
                    return $instance->{$method}(...$all);
                }
            }
        }

        return $unmatched;
    }

    public function checkParams(array $params): void
    {
        $prev = get_debug_type(key($params));

        foreach (array_keys($params) as $name)
        {
            if ($prev !== get_debug_type($name))
            {
                throw new ResolverException(
                    sprintf(
                        'parameters can be indexed or named, not both: %s',
                        json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    )
                );
            }
        }
    }

    public function canResolve(string $id): bool
    {
        return class_exists($id) && (new \ReflectionClass($id))->isInstantiable();
    }

    private function tryGetEntryFromContainer(array|string $ids): mixed
    {
        foreach ($ids as $id)
        {
            if ($this->hasBuiltin($id))
            {
                continue;
            }

            try
            {
                return $this->container->get($id);
            } catch (ContainerExceptionInterface)
            {
            }
        }

        return null;
    }

    private function hasBuiltin(array|string $types): bool
    {
        static $builtin = [
            'self', 'parent', 'static',
            'array', 'callable', 'bool', 'float', 'int', 'string', 'iterable', 'object', 'mixed',
            'void', 'never', 'null', 'false', 'true',
        ];

        foreach ((is_array($types) ? $types : [$types]) as $type)
        {
            if (in_array($type, $builtin))
            {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array $params
     *
     * @return Parameter[]
     */
    private function parseParameters(array $params): array
    {
        $index  = -1;
        $result = [];

        foreach ($params as $name => $value)
        {
            ++$index;

            if ( ! is_string($name))
            {
                $name = null;
            }
            $result[] = $instance = new Parameter($value, $index, $name);

            if ($name)
            {
                $result[$name] = &$instance;
            }
        }

        return $result;
    }

    private function hasConstructor(string $class): bool
    {
        return (bool) (new \ReflectionClass($class))->getConstructor();
    }

    private function instantiable(string $class): bool
    {
        return class_exists($class)
            && (new \ReflectionClass($class))->isInstantiable();
    }
}
