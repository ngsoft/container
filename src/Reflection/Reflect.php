<?php

declare(strict_types=1);

namespace NGSOFT\Reflection;

class Reflect
{
    private const BUILTIN_TYPES = [
        'self', 'parent', 'static',
        'array', 'callable', 'bool', 'float', 'int', 'string', 'iterable', 'object', 'mixed',
        'void', 'never', 'null', 'false', 'true',
    ];

    /**
     * @param array|object|string $subject
     *
     * @return ReflectParameter[]
     */
    public function reflect(array|object|string $subject): array
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
                return [];
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

        /** @var \ReflectionFunction|\ReflectionMethod $reflector */
        $results   = [];
        $index     = -1;

        foreach ($reflector->getParameters() as $parameter)
        {
            ++$index;
            $name            = $parameter->getName();

            if ( ! $parameter->canBePassedByValue())
            {
                throw new \ReflectionException(
                    sprintf(
                        'Cannot reflect Argument #%d (&$%s) that can only be passed by reference.',
                        $index,
                        $parameter->getName()
                    )
                );
            }

            $nullable        = $parameter->allowsNull();
            $variadic        = $parameter->isVariadic();
            $types           = explode('|', (string) $parameter->getType());
            $uniques         = [];
            $optional        = $parameter->isOptional();
            $hasDefaultValue = $parameter->isDefaultValueAvailable();
            $defaultValue    = $hasDefaultValue ? $parameter->getDefaultValue() : null;

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

            $results[]       = new ReflectParameter([
                'name'            => $name,
                'index'           => $index,
                'variadic'        => $variadic,
                'optional'        => $optional,
                'types'           => array_values($uniques),
                'hasDefaultValue' => $hasDefaultValue,
                'defaultValue'    => $defaultValue,
            ]);
        }

        return $results;
    }
}
