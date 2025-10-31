<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Runtime\Formatter;

use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\DataFetcher\DataFetcherInterface;
use Propel\Runtime\Exception\LogicException;

/**
 * @deprecated class is not type-safe.
 *
 * Array formatter for Propel select query
 * format() returns a ArrayCollection of associative arrays, a string,
 * or an array
 *
 * @author Benjamin Runnels
 *
 * @extends \Propel\Runtime\Formatter\AbstractFormatter<array<string, mixed>|string, \Propel\Runtime\Collection\ArrayCollection>
 */
class SimpleArrayFormatter extends AbstractFormatter
{
    /**
     * @param \Propel\Runtime\DataFetcher\DataFetcherInterface|null $dataFetcher
     *
     * @throws \Propel\Runtime\Exception\LogicException
     *
     * @return \Propel\Runtime\Collection\ArrayCollection
     */
    #[\Override]
    public function format(?DataFetcherInterface $dataFetcher = null)
    {
        $this->checkInit();

        if ($dataFetcher) {
            $this->setDataFetcher($dataFetcher);
        } else {
            $dataFetcher = $this->getDataFetcher();
        }

        $collection = $this->getCollection();

        if ($this->isWithOneToMany() && $this->hasLimit) {
            $dataFetcher->close();

            throw new LogicException('Cannot use limit() in conjunction with with() on a one-to-many relationship. Please remove the with() call, or the limit() call.');
        }

        foreach ($dataFetcher as $row) {
            $rowArray = $this->getStructuredArrayFromRow($row);
            $collection[] = $rowArray;
        }
        $dataFetcher->close();

        return $collection;
    }

    /**
     * @return class-string<\Propel\Runtime\Collection\ArrayCollection>|null
     */
    #[\Override]
    public function getCollectionClassName(): ?string
    {
        return '\Propel\Runtime\Collection\ArrayCollection';
    }

    /**
     * @param \Propel\Runtime\DataFetcher\DataFetcherInterface|null $dataFetcher
     *
     * @throws \Propel\Runtime\Exception\LogicException
     *
     * @return array<string, mixed>|string|null
     */
    #[\Override]
    public function formatOne(?DataFetcherInterface $dataFetcher = null)
    {
        $this->checkInit();
        $result = null;

        if ($this->isWithOneToMany() && $this->hasLimit) {
            $dataFetcher->close();

            throw new LogicException('Cannot use limit() in conjunction with with() on a one-to-many relationship. Please remove the with() call, or the limit() call.');
        }

        if ($dataFetcher) {
            $this->setDataFetcher($dataFetcher);
        } else {
            $dataFetcher = $this->getDataFetcher();
        }

        foreach ($dataFetcher as $row) {
            $rowArray = $this->getStructuredArrayFromRow($row);
            $result = $rowArray;
        }
        $dataFetcher->close();

        return $result;
    }

    /**
     * Formats an ActiveRecord object
     *
     * @param \Propel\Runtime\ActiveRecord\ActiveRecordInterface|null $record the object to format
     *
     * @return array<string, mixed> The original record turned into an array
     */
    #[\Override]
    public function formatRecord(?ActiveRecordInterface $record = null): array
    {
        return $record ? $record->toArray() : [];
    }

    /**
     * @return bool
     */
    #[\Override]
    public function isObjectFormatter(): bool
    {
        return false;
    }

    /**
     * @param array $row
     *
     * @return array<string, mixed>|string
     */
    public function getStructuredArrayFromRow(array $row)
    {
        $columnNames = array_keys($this->getAsColumns());
        if (count($columnNames) <= 1 || count($row) <= 1) {
            return $row[0];
        }
        $finalRow = [];
        foreach ($row as $index => $value) {
            $key = str_replace('"', '', $columnNames[$index]);
            $finalRow[$key] = $value;
        }

        return $finalRow;
    }
}
