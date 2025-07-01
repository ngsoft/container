<?php

namespace NGSOFT\Container;

interface CanResolve
{
    public function canResolve(string $id): bool;
}
