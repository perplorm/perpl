<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Runtime\Collection;

use ArrayIterator;

/**
 * Iterator class for iterating over Collection data
 *
 * @template RowFormat
 * @extends \ArrayIterator<(int|string), RowFormat>
 * @implements \Propel\Runtime\Collection\IteratorInterface<RowFormat>
 */
class CollectionIterator extends ArrayIterator implements IteratorInterface
{
    /**
     * @var \Propel\Runtime\Collection\Collection<RowFormat>
     */
    protected Collection $collection;

    /**
     * @var array
     */
    protected array $positions = [];

    /**
     * Constructor
     *
     * @param \Propel\Runtime\Collection\Collection<RowFormat> $collection
     */
    public function __construct(Collection $collection)
    {
        parent::__construct($collection->getData());

        $this->collection = $collection;
        $this->refreshPositions();
    }

    /**
     * Returns the collection instance
     *
     * @return \Propel\Runtime\Collection\Collection<RowFormat>
     */
    public function getCollection(): Collection
    {
        return $this->collection;
    }

    /**
     * Check if the collection is empty
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * Gets the position of the internal pointer
     * This position can be later used in seek()
     *
     * @return int
     */
    public function getPosition(): int
    {
        if (!$this->key()) {
            return 0;
        }

        return $this->positions[$this->key()];
    }

    /**
     * Move the internal pointer to the beginning of the list
     * And get the first element in the collection
     *
     * @psalm-suppress ReservedWord
     *
     * @return RowFormat|null
     */
    #[\ReturnTypeWillChange]
    public function getFirst()
    {
        if ($this->isEmpty()) {
            return null;
        }
        $this->rewind();

        return $this->current();
    }

    /**
     * Check whether the internal pointer is at the beginning of the list
     *
     * @return bool
     */
    public function isFirst(): bool
    {
        return $this->getPosition() === 0;
    }

    /**
     * Move the internal pointer backward
     * And get the previous element in the collection
     *
     * @psalm-suppress ReservedWord
     *
     * @return RowFormat|null
     */
    #[\ReturnTypeWillChange]
    public function getPrevious()
    {
        if ($this->isFirst()) {
            return null;
        }

        $this->seek($this->getPosition() - 1);

        return $this->current();
    }

    /**
     * Get the current element in the collection
     *
     * @psalm-suppress ReservedWord
     *
     * @return RowFormat|null
     */
    #[\ReturnTypeWillChange]
    public function getCurrent()
    {
        return $this->current();
    }

    /**
     * Move the internal pointer forward
     * And get the next element in the collection
     *
     * @psalm-suppress ReservedWord
     *
     * @return RowFormat|null
     */
    #[\ReturnTypeWillChange]
    public function getNext()
    {
        $this->next();

        return $this->current();
    }

    /**
     * Move the internal pointer to the end of the list
     * And get the last element in the collection
     *
     * @psalm-suppress ReservedWord
     *
     * @return RowFormat|null
     */
    #[\ReturnTypeWillChange]
    public function getLast()
    {
        if ($this->isEmpty()) {
            return null;
        }

        $this->seek(count($this->positions) - 1);

        return $this->current();
    }

    /**
     * Check whether the internal pointer is at the end of the list
     *
     * @return bool
     */
    public function isLast(): bool
    {
        if ($this->isEmpty()) {
            // empty list... so yes, this is the last
            return true;
        }

        return $this->getPosition() === count($this->positions) - 1;
    }

    /**
     * Check if the current index is an odd integer
     *
     * @return bool
     */
    public function isOdd(): bool
    {
        return (bool)($this->getPosition() % 2);
    }

    /**
     * Check if the current index is an even integer
     *
     * @return bool
     */
    public function isEven(): bool
    {
        return !$this->isOdd();
    }

    /**
     * @param string|int $offset
     * @param RowFormat $value
     *
     * @return void
     */
    #[\Override]
    public function offsetSet($offset, $value): void
    {
        $this->collection->offsetSet($offset, $value);
        parent::offsetSet($offset, $value);
        $this->refreshPositions();
    }

    /**
     * @param string|int $offset
     *
     * @return void
     */
    #[\Override]
    public function offsetUnset($offset): void
    {
        $this->collection->offsetUnset($offset);
        parent::offsetUnset($offset);
        $this->refreshPositions();
    }

    /**
     * @param RowFormat $value
     *
     * @return void
     */
    #[\Override]
    public function append($value): void
    {
        $this->collection->append($value);
        parent::append($value);
        $this->refreshPositions();
    }

    /**
     * @param int $flags Not used
     *
     * @return true
     */
    #[\Override, \ReturnTypeWillChange]
    public function asort(int $flags = SORT_REGULAR): bool
    {
        parent::asort($flags);
        $this->refreshPositions();

        return true;
    }

    /**
     * @param int $flags Not used
     *
     * @return bool
     */
    #[\Override, \ReturnTypeWillChange]
    public function ksort(int $flags = SORT_REGULAR): bool
    {
        parent::ksort($flags);
        $this->refreshPositions();

        return true;
    }

    /**
     * @param callable $cmp_function
     *
     * @return bool
     */
    #[\Override, \ReturnTypeWillChange]
    public function uasort($cmp_function): bool
    {
        parent::uasort($cmp_function);
        $this->refreshPositions();

        return true;
    }

    /**
     * @param callable $cmp_function
     *
     * @return true
     */
    #[\Override, \ReturnTypeWillChange]
    public function uksort($cmp_function): bool
    {
        parent::uksort($cmp_function);
        $this->refreshPositions();

        return true;
    }

    /**
     * @return true
     */
    #[\Override, \ReturnTypeWillChange]
    public function natsort(): bool
    {
        parent::natsort();
        $this->refreshPositions();

        return true;
    }

    /**
     * @return true
     */
    #[\Override, \ReturnTypeWillChange]
    public function natcasesort(): bool
    {
        parent::natcasesort();
        $this->refreshPositions();

        return true;
    }

    /**
     * @return void
     */
    private function refreshPositions(): void
    {
        $this->positions = array_flip(array_keys($this->getArrayCopy()));
    }
}
