<?php

namespace NGSOFT\Container;

use NGSOFT\Container\Internal\UnmatchedEntry;

interface Resolver
{
    /**
     * @param mixed $value
     * @param array $params
     *
     * @return mixed|UnmatchedEntry returns UnmatchedEntry if not found as a function can return null
     */
    public function resolve(mixed $value, array $params): mixed;
}
