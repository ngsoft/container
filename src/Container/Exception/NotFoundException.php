<?php

namespace NGSOFT\Container\Exception;

use Psr\Container\NotFoundExceptionInterface;

class NotFoundException extends \RuntimeException implements NotFoundExceptionInterface
{
    public static function of(string $id, ?\Throwable $prev = null): static
    {
        return new static(sprintf('Service "%s" not found.', $id), previous: $prev);
    }
}
