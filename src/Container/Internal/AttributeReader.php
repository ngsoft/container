<?php

declare(strict_types=1);

namespace NGSOFT\Container\Internal;

use Spiral\Attributes\AttributeReader as SpiralAttributeReader;

readonly class AttributeReader
{
    private SpiralAttributeReader $reader;

    public function __construct()
    {
        $this->reader = new SpiralAttributeReader();
    }

    /**
     * @template T
     * @template C
     *
     * @param class-string<T>           $attribute
     * @param class-string<C>|object<C> $class
     *
     * @return \Traversable<[T,class-string<C>|object<C>]>
     */
    public function getClassAttributes(string $attribute, object|string $class): \Traversable
    {
        if ( ! $this->exists($class) || ! $this->exists($attribute))
        {
            return;
        }

        foreach ($this->getClassParents($class) as $context)
        {
            foreach ($this->reader->getClassMetadata($context) as $metadata)
            {
                yield [$metadata, $class];
            }
        }
    }

    /**
     * @template T
     * @template C
     *
     * @param class-string<T>           $attribute
     * @param class-string<C>|object<C> $class
     * @param ?string                   $method
     *
     * @return \Traversable<[T,class-string<C>|object<C>,string]>
     */
    public function getClassMethodAttributes(string $attribute, object|string $class, ?string $method = null): \Traversable
    {
        if ( ! $this->exists($class) || ! $this->exists($attribute))
        {
            return;
        }

        if ($method)
        {
            try
            {
                foreach ($this->reader->getFunctionMetadata(
                    new \ReflectionMethod($class, $method),
                    $attribute
                ) as $metadata)
                {
                    yield [$metadata, $class, $method];
                }
            } catch (\ReflectionException)
            {
            }

            return;
        }

        $names = [];

        foreach ($this->getClassParents($class) as $context)
        {
            foreach ($context->getMethods() as $reflector)
            {
                $name         = $reflector->getName();

                if (isset($names[$name]))
                {
                    continue;
                }
                $names[$name] = true;

                foreach ($this->reader->getFunctionMetadata($reflector, $attribute) as $metadata)
                {
                    yield [$metadata, $class, $reflector->getName()];
                }
            }
        }
    }

    /**
     * @template T
     * @template C
     *
     * @param class-string<T>           $attribute
     * @param class-string<C>|object<C> $class
     * @param ?string                   $property
     *
     * @return \Traversable<[T,class-string<C>|object<C>,string]>
     */
    public function getClassPropertiesAttributes(string $attribute, object|string $class, ?string $property = null): \Traversable
    {
        if ( ! $this->exists($class) || ! $this->exists($attribute))
        {
            return;
        }

        if ($property)
        {
            try
            {
                foreach ($this->reader->getPropertyMetadata(
                    new \ReflectionProperty($class, $property),
                    $attribute
                ) as $metadata)
                {
                    yield [$metadata, $class, $property];
                }
            } catch (\ReflectionException)
            {
            }

            return;
        }
        $names = [];

        foreach ($this->getClassParents($class) as $context)
        {
            foreach ($context->getProperties() as $reflector)
            {
                $name         = $reflector->getName();

                if (isset($names[$name]))
                {
                    continue;
                }
                $names[$name] = true;

                foreach ($this->reader->getPropertyMetadata($reflector, $attribute) as $metadata)
                {
                    yield [$metadata, $class, $reflector->getName()];
                }
            }
        }
    }

    private function exists(object|string $class): bool
    {
        if (is_object($class))
        {
            return true;
        }
        return class_exists($class);
    }

    private function getClassParents(object|string $subject, bool $traits = false): \Traversable
    {
        try
        {
            $context = new \ReflectionClass($subject);

            do
            {
                yield $context;

                if ($traits)
                {
                    foreach ($context->getTraits() as $reflector)
                    {
                        yield $reflector;
                    }
                }
            } while (false !== $context = $context->getParentClass());
        } catch (\ReflectionException)
        {
        }
    }
}
