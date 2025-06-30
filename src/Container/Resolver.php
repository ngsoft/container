<?php

namespace NGSOFT\Container;

interface Resolver
{
    public function resolve(mixed $value, array $params): mixed;

    public function canResolve(string $id): bool;
}
