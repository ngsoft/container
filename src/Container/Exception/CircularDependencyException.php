<?php

namespace NGSOFT\Container\Exception;

class CircularDependencyException extends ResolverException
{
    public static function of(string $id): static
    {
        return new static(sprintf('Already resolving "%s"', $id));
    }
}
