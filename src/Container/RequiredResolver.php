<?php

declare(strict_types=1);

namespace NGSOFT\Container;

use Closure;
use NGSOFT\Container\Attribute\Required;
use NGSOFT\Container\Exception\ResolverException;
use NGSOFT\Container\Internal\AttributeReader;
use NGSOFT\Container\Internal\UnmatchedEntry;
use NGSOFT\Reflection\Reflect;
use NGSOFT\Reflection\ReflectParameter;

readonly class RequiredResolver implements Resolver
{
    private Reflect $reflect;

    private AttributeReader $reader;

    private CallableResolver $callableResolver;

    public function __construct(
        private Container $container,
    ) {
        $this->reflect          = new Reflect();
        $this->reader           = new AttributeReader();
        $this->callableResolver = new CallableResolver($this->container);
    }

    public function resolve(mixed $value, array $params): mixed
    {
        static $unmatched = new UnmatchedEntry();

        // Closure is an object
        if ($value instanceof \Closure)
        {
            return $unmatched;
        }

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
                    $parameter = $this->reflect->reflectProperty($context = new \ReflectionProperty($value, $property));

                    if ($type = $this->findClassName($parameter))
                    {
                        // throw if not found
                        $instance = $this->container->get($type);

                        $context->setValue($value, $instance);
                        continue;
                    }

                    if ($parameter->hasDefaultValue())
                    {
                        $context->setValue($value, $parameter->getDefaultValue());
                        continue;
                    }

                    if ($parameter->isNullable())
                    {
                        $context->setValue($value, null);
                        continue;
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
            if (class_exists($type) || interface_exists($type))
            {
                return $type;
            }
        }
        return null;
    }
}
