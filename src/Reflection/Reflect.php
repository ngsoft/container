<?php

declare(strict_types=1);

namespace NGSOFT\Reflection;

final readonly class Reflect
{
    /**
     * @param array|object|string $subject
     *
     * @return ReflectParameter[]
     */
    public function reflect(array|object|string $subject): array
    {
        /** @var \ReflectionFunction|\ReflectionMethod $reflector */
        $context = $this->getReflector($subject);

        if ( ! $context)
        {
            return [];
        }

        $results = [];
        $index   = -1;

        foreach ($context->getParameters() as $parameter)
        {
            ++$index;

            $results[$parameter->getName()] = $this->parseParameter($parameter, $context)
                ->setIndex($index);
        }

        return $results;
    }

    public function reflectProperty(\ReflectionProperty $context): ReflectParameter
    {
        return $this->parseParameter($context, $context->getDeclaringClass());
    }

    public function getReflector(array|object|string $subject): null|\ReflectionFunction|\ReflectionMethod
    {
        $reflector = null;

        if (is_string($subject))
        {
            $reflector = new \ReflectionClass($subject);

            if ( ! $reflector->isInstantiable())
            {
                throw new \ReflectionException($subject . ' is not instantiable');
            }

            $reflector = $reflector->getConstructor();

            if ( ! $reflector)
            {
                return null;
            }
        } elseif ($subject instanceof \Closure)
        {
            $reflector = new \ReflectionFunction($subject);
        } elseif (is_object($subject))
        {
            $subject = [$subject, '__invoke'];
        }

        if (is_array($subject) && 2 === count($subject))
        {
            $reflector = new \ReflectionMethod(is_object($subject[0]) ? get_class($subject[0]) : $subject[0], $subject[1]);
        }

        return $reflector;
    }

    private function parseParameter(\ReflectionParameter|\ReflectionProperty $parameter, \ReflectionClass|\ReflectionFunctionAbstract $context): ReflectParameter
    {
        $name         = $parameter->getName();
        $variadic     = false;
        $optional     = false;

        if ($parameter instanceof \ReflectionParameter)
        {
            if ( ! $parameter->canBePassedByValue())
            {
                throw new \ReflectionException(
                    sprintf(
                        'Cannot reflect Argument (&$%s) that can only be passed by reference.',
                        $parameter->getName()
                    )
                );
            }
            $variadic        = $parameter->isVariadic();
            $nullable        = $parameter->allowsNull();
            $optional        = $parameter->isOptional();
            $hasDefaultValue = $parameter->isDefaultValueAvailable();
        } else
        {
            $hasDefaultValue = $parameter->hasDefaultValue();
            $nullable        = $parameter->getType()?->allowsNull() ?? false;
        }

        $types        = explode('|', (string) ($parameter->getType() ?? 'mixed'));
        $uniques      = [];

        $defaultValue = $hasDefaultValue ? $parameter->getDefaultValue() : null;

        if ($hasDefaultValue)
        {
            $uniques[get_debug_type($defaultValue)] = get_debug_type($defaultValue);
        }

        foreach ($types as $type)
        {
            if (str_contains($type, '&'))
            {
                continue;
            }

            if (str_starts_with($type, '?'))
            {
                $nullable = true;
                $type     = substr($type, 1);
            }

            if ('self' === $type)
            {
                if ($context instanceof \ReflectionMethod)
                {
                    $type = $context->getDeclaringClass()->getName();
                } elseif ($context instanceof \ReflectionClass)
                {
                    $type = $context->getName();
                }
            }

            if ($type)
            {
                $uniques[$type] = $type;
            }
        }

        if ($nullable)
        {
            $uniques['null'] = 'null';
        }

        if (empty($uniques))
        {
            $uniques['mixed'] = 'mixed';
        }

        return new ReflectParameter([
            'name'            => $name,
            'variadic'        => $variadic,
            'optional'        => $optional,
            'types'           => array_values($uniques),
            'hasDefaultValue' => $hasDefaultValue,
            'defaultValue'    => $defaultValue,
        ]);
    }
}
