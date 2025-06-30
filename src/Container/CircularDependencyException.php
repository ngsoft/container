<?php

namespace NGSOFT\Container;

class CircularDependencyException extends ResolverException
{
    public static function of(string $id): static
    {
        return new static(sprintf('Already resolving "%s"', $id));
    }
}
