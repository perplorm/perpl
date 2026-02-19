<?php

declare(strict_types = 1);

namespace Propel\Runtime\DataFetcher;

use Countable;
use Iterator;

/**
 * Interface class for DataFetcher.
 *
 * @extends \Iterator<int|string, mixed>
 */
interface DataFetcherInterface extends Iterator, Countable
{
    /**
     * Sets the dataObject.
     *
     * @param mixed $dataObject
     *
     * @return void
     */
    public function setDataObject($dataObject): void;

    /**
     * Returns the current data object that holds or references to actual data.
     *
     * @return mixed
     */
    public function getDataObject();

    /**
     * Return the current element
     *
     * @psalm-suppress ReservedWord
     *
     * @link http://php.net/manual/en/iterator.current.php
     *
     * @return mixed Can return any type.
     */
    #[\Override, \ReturnTypeWillChange]
    public function current();

    /**
     * Move forward to next element
     *
     * @link http://php.net/manual/en/iterator.next.php
     *
     * @return void Any returned value is ignored.
     */
    #[\Override]
    public function next(): void;

    /**
     * Return the key of the current element
     *
     * @psalm-suppress ReservedWord
     *
     * @link http://php.net/manual/en/iterator.key.php
     *
     * @return string|int|null scalar on success, or null on failure.
     */
    #[\Override, \ReturnTypeWillChange]
    public function key();

    /**
     * Checks if current position is valid
     *
     * @link http://php.net/manual/en/iterator.valid.php
     *
     * @return bool The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     */
    #[\Override]
    public function valid(): bool;

    /**
     * Rewind the Iterator to the first element
     *
     * @link http://php.net/manual/en/iterator.rewind.php
     *
     * @return void Any returned value is ignored.
     */
    #[\Override]
    public function rewind(): void;

    /**
     * Returns the data of the first column of the next row,
     * based on this->fetch();
     *
     * @param int|null $index
     *
     * @return mixed|null
     */
    public function fetchColumn(?int $index = null);

    /**
     * Returns the data of the next row,
     * based on this->next() && this->current();
     *
     * @return array|bool|null
     */
    public function fetch();

    /**
     * Frees the resultSet.
     *
     * @return void
     */
    public function close(): void;

    /**
     * Returns the count of items in the resultSet.
     *
     * @return int
     */
    #[\Override]
    public function count(): int;

    /**
     * Returns the TableMap::TYPE_*
     * depends on your resultSet. We need this information
     * to be able to populate objects based on your array of fetch().
     *
     * @return string one of TableMap::TYPE_*
     */
    public function getIndexType(): string;
}
