<?php

declare(strict_types=1);

namespace NGSOFT\Container;

/**
 * @internal
 */
class PrioritySet implements \Countable, \JsonSerializable, \Stringable, \IteratorAggregate
{
    protected bool $locked    = false;

    private array $priorities = [];
    private array $storage    = [];

    /** @var array[] */
    private array $sorted     = [];

    public function __toString(): string
    {
        return sprintf('object(%s)#%d', get_class($this), spl_object_id($this));
    }

    public function __debugInfo(): array
    {
        return $this->jsonSerialize();
    }

    public function __serialize(): array
    {
        return [$this->storage, $this->priorities, $this->locked];
    }

    public function __unserialize(array $data): void
    {
        list($this->storage, $this->priorities, $this->locked) = $data;
    }

    public function __clone(): void
    {
        $this->sorted  = [];
        $this->storage = $this->cloneArray($this->storage);
    }

    /**
     * The add() method adds a new element with a specified value with a given priority.
     *
     * @param int|Priority $priority > 0 the highest the number, the highest the priority
     */
    public function add(mixed $value, int|Priority $priority = Priority::MEDIUM): static
    {
        if ($this->isLocked())
        {
            return $this;
        }

        $priority = is_int($priority) ? $priority : $priority->value;

        if ( ! $this->has($value))
        {
            $this->storage[]                          = $value;
            $this->priorities[$this->indexOf($value)] = max(1, $priority);
            // reset sorted
            $this->sorted                             = [];
        }
        return $this;
    }

    /**
     * The delete() method removes a specified value from a Set object if it is in the set.
     */
    public function delete(mixed $value): bool
    {
        if ( ! $this->isLocked())
        {
            $offset = $this->indexOf($value);

            if ($offset > -1)
            {
                unset($this->storage[$offset], $this->priorities[$offset]);
                $this->sorted = [];
                return true;
            }
        }
        return false;
    }

    /**
     * The clear() method removes all elements from a Set object.
     */
    public function clear(): void
    {
        if ($this->isLocked())
        {
            return;
        }

        $this->storage = $this->priorities = $this->sorted = [];
    }

    /**
     * The entries() method returns a new Iterator object that contains an array of [value, value] for each element in the Set object, in insertion order.
     *
     * @param bool $reversed
     *
     * @return iterable
     */
    public function entries(bool $reversed = false): iterable
    {
        foreach ($this->getIndexes($reversed) as $offset)
        {
            yield $this->storage[$offset] => $this->storage[$offset];
        }
    }

    /**
     * The has() method returns a boolean indicating whether an element with the specified value exists in a Set object or not.
     */
    public function has(mixed $value): bool
    {
        return -1 !== $this->indexOf($value);
    }

    /**
     * The values() method returns a new Iterator object that contains the values for each element in the Set object in insertion order.
     *
     * @param bool $reversed
     *
     * @return iterable
     */
    public function values(bool $reversed = false): iterable
    {
        foreach ($this->entries($reversed) as $value)
        {
            yield $value;
        }
    }

    public function getPriorityOf(mixed $value): int
    {
        $offset = $this->indexOf($value);

        if ($offset < 0)
        {
            return $offset;
        }

        return $this->priorities[$offset];
    }

    /**
     * Checks if a set is empty.
     */
    public function isEmpty(): bool
    {
        return 0 === $this->count();
    }

    /**
     * Create a new Set.
     */
    public static function create(): static
    {
        return new static();
    }

    /**
     * Lock the object.
     */
    public function lock(): void
    {
        $this->locked = true;
    }

    /**
     * Unlock the object.
     */
    public function unlock(): void
    {
        $this->locked = false;
    }

    /**
     * Get the lock status.
     */
    public function isLocked(): bool
    {
        return $this->locked;
    }

    public function getIterator(): \Traversable
    {
        yield from $this->entries();
    }

    public function count(): int
    {
        return count($this->storage);
    }

    public function jsonSerialize(): array
    {
        return iterator_to_array($this->values());
    }

    /**
     * Helper to be used with __clone() method.
     *
     * @param array $array
     * @param bool  $recursive
     *
     * @return array
     */
    protected function cloneArray(array $array, bool $recursive = true): array
    {
        $result = [];

        foreach ($array as $offset => $value)
        {
            if (is_object($value))
            {
                $result[$offset] = clone $value;
                continue;
            }

            if (is_array($value) && $recursive)
            {
                $result[$offset] = $this->cloneArray($value, $recursive);
                continue;
            }

            $result[$offset] = $value;
        }

        return $result;
    }

    /**
     * Get Index of value inside the set.
     */
    private function indexOf(mixed $value): int
    {
        $index = array_search($value, $this->storage, true);
        return false !== $index ? $index : -1;
    }

    /** @return array[] */
    private function getSorted(): array
    {
        if (empty($this->storage))
        {
            return [];
        }

        if (empty($this->sorted))
        {
            $sorted = &$this->sorted;

            foreach ($this->priorities as $offset => $priority)
            {
                $sorted[$priority] ??= [];
                $sorted[$priority][] = $offset;
            }

            krsort($sorted);
        }

        return $this->sorted;
    }

    private function getIndexes($reversed = false): iterable
    {
        $sorted = $this->getSorted();

        if ($reversed)
        {
            $sorted = array_reverse($sorted);
        }

        foreach ($sorted as $offsets)
        {
            yield from $offsets;
        }
    }
}
