<?php

namespace NGSOFT\Container\Exception;

use Psr\Container\ContainerExceptionInterface;

class ResolverException extends \RuntimeException implements ContainerExceptionInterface
{
    public static function invalidCallable(mixed $callable, ?\Throwable $prev = null): static
    {
        if (is_object($callable))
        {
            $message = sprintf('Instance of %s does not implements __invoke()', get_class($callable));
        } elseif (is_array($callable) && isset($callable[0], $callable[1]))
        {
            $class   = is_object($callable[0]) ? get_class($callable[0]) : $callable[0];
            $extra   = method_exists($class, '__call') || method_exists($class, '__callStatic') ? ' A __call() or __callStatic() method exists but magic methods are not supported.' : '';
            $message = sprintf('%s::%s() is not a callable.%s', $class, $callable[1], $extra);
        } else
        {
            $message = var_export($callable, true) . ' is not a callable';
        }

        return new static($message, previous: $prev);
    }
}
