<?php

namespace NGSOFT\Container;

class CallableResolver implements Resolver
{
    public function __construct(
        private Container $container
    ) {}

    public function resolve(mixed $value, array $params): mixed
    {
        // TODO: implement this
        return null;
    }

    public function canResolve(string $id): bool
    {
        return class_exists($id) && (new \ReflectionClass($id))->isInstantiable();
    }
}
