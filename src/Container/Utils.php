<?php

namespace NGSOFT\Container;

/**
 * @internal
 */
class Utils
{
    public static function classBasename(object|string $class): string
    {
        return basename(str_replace('\\', '/', is_object($class) ? get_class($class) : $class));
    }

    public static function isInstantiable(string $class): bool
    {
        return class_exists($class) && (new \ReflectionClass($class))->isInstantiable();
    }
}
