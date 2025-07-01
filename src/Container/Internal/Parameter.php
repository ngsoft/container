<?php

declare(strict_types=1);

namespace NGSOFT\Container\Internal;

/**
 * @internal
 */
readonly class Parameter
{
    private mixed $value;
    private string $type;

    public function __construct(mixed $value, private int $index, private ?string $name)
    {
        $this->value = $value;
        $this->type  = get_debug_type($value);
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getIndex(): int
    {
        return $this->index;
    }

    public function getName(): ?string
    {
        return $this->name;
    }
}
