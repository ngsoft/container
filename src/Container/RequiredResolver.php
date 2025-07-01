<?php

namespace NGSOFT\Container;

use NGSOFT\Container\Attribute\Required;
use NGSOFT\Container\Exception\ResolverException;
use NGSOFT\Container\Internal\AttributeReader;
use NGSOFT\Container\Internal\UnmatchedEntry;
use NGSOFT\Reflection\Reflect;
use NGSOFT\Reflection\ReflectParameter;

class RequiredResolver implements Resolver
{
    private Reflect $reflect;

    private AttributeReader $reader;

    private readonly CallableResolver $callableResolver;

    public function __construct(
        private readonly Container $container,
    ) {
        $this->reflect          = new Reflect();
        $this->reader           = new AttributeReader();
        $this->callableResolver = new CallableResolver($this->container);
    }

    public function resolve(mixed $value, array $params): mixed
    {
        static $unmatched = new UnmatchedEntry();

        if (empty($params))
        {
            if (is_string($value) && class_exists($value))
            {
                $instance = $this->callableResolver->resolve($value, $params);

                if ($instance instanceof UnmatchedEntry)
                {
                    return $instance;
                }
                $value    = $instance;
            }

            if (is_object($value))
            {
                // call the methods
                foreach ($this->reader->getClassMethodAttributes(Required::class, $value) as list(, , $method))
                {
                    // throw if not found
                    $this->container->call([$value, $method]);
                }

                // inject instance
                foreach ($this->reader->getClassPropertiesAttributes(Required::class, $value) as list(, , $property))
                {
                    /** @var string $property */
                    $parameter = $this->reflect->reflectProperty(new \ReflectionProperty($value, $property));

                    if ($type = $this->findClassName($parameter))
                    {
                        // throw if not found
                        $instance = $this->container->get($type);

                        try
                        {
                            $context = new \ReflectionProperty($value, $property);
                            $context->setValue($value, $instance);
                            continue;
                        } catch (\ReflectionException)
                        {
                        }
                    } elseif ($parameter->hasDefaultValue())
                    {
                        try
                        {
                            $context = new \ReflectionProperty($value, $property);
                            $context->setValue($value, $parameter->getDefaultValue());
                            continue;
                        } catch (\ReflectionException)
                        {
                        }
                    } elseif ($parameter->isNullable())
                    {
                        try
                        {
                            $context = new \ReflectionProperty($value, $property);
                            $context->setValue($value, null);
                            continue;
                        } catch (\ReflectionException)
                        {
                        }
                    }
                    throw new ResolverException(
                        sprintf(
                            'cannot resolve (%s) %s::$%s',
                            implode('|', $parameter->getTypes()),
                            get_class($value),
                            $parameter->getName()
                        )
                    );
                }

                return $value;
            }
        }

        return $unmatched;
    }

    private function findClassName(ReflectParameter $parameter): ?string
    {
        foreach ($parameter->getTypes() as $type)
        {
            if (class_exists($type))
            {
                return $type;
            }
        }
        return null;
    }
}
