<?php

namespace NGSOFT\Reflection;

class ReflectParameter
{
    private string $name;
    private int $index;
    private bool $variadic;
    private bool $optional;
    private array $types;

    private bool $hasDefaultValue;
    private mixed $defaultValue;

    public function __construct(array $data)
    {
        foreach ($data as $name => $value)
        {
            $this->{$name} = $value;
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getIndex(): int
    {
        return $this->index;
    }

    public function isVariadic(): bool
    {
        return $this->variadic;
    }

    public function isOptional(): bool
    {
        return $this->optional;
    }

    /**
     * @return string[]
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    public function hasDefaultValue(): bool
    {
        return $this->hasDefaultValue;
    }

    public function getDefaultValue(): mixed
    {
        return $this->defaultValue;
    }
}
