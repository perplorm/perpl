<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Propel\Runtime\Formatter;

use LogicException;
use Propel\Runtime\ActiveQuery\BaseModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\DataFetcher\DataFetcherInterface;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Map\TableMap;
use Propel\Runtime\Propel;

/**
 * Abstract class for query formatter
 *
 * @author Francois Zaninotto
 *
 * @template RowFormat
 * @template ListType of \Traversable
 */
abstract class AbstractFormatter
{
    /**
     * @var string|null
     */
    protected $dbName;

    /**
     * @psalm-var class-string<RowFormat>|null
     *
     * @var string|null
     */
    protected $class;

    /**
     * @var \Propel\Runtime\Map\TableMap|null
     */
    protected $tableMap;

    /**
     * @var array<\Propel\Runtime\ActiveQuery\ModelWith>
     */
    protected $with = [];

    /**
     * @var array<string, string>
     */
    protected $asColumns = [];

    /**
     * @var bool
     */
    protected $hasLimit = false;

    /**
     * @var array<\Propel\Runtime\ActiveRecord\ActiveRecordInterface>
     */
    protected $currentObjects = [];

    /**
     * @var \Propel\Runtime\DataFetcher\DataFetcherInterface
     */
    protected $dataFetcher;

    /**
     * @param \Propel\Runtime\ActiveQuery\BaseModelCriteria|null $criteria
     * @param \Propel\Runtime\DataFetcher\DataFetcherInterface|null $dataFetcher
     */
    public function __construct(?BaseModelCriteria $criteria = null, ?DataFetcherInterface $dataFetcher = null)
    {
        if ($criteria !== null) {
            $this->init($criteria, $dataFetcher);
        }
    }

    /**
     * Sets a DataFetcherInterface object.
     *
     * @param \Propel\Runtime\DataFetcher\DataFetcherInterface $dataFetcher
     *
     * @return void
     */
    public function setDataFetcher(DataFetcherInterface $dataFetcher): void
    {
        $this->dataFetcher = $dataFetcher;
    }

    /**
     * Returns the current DataFetcherInterface object.
     *
     * @return \Propel\Runtime\DataFetcher\DataFetcherInterface
     */
    public function getDataFetcher(): DataFetcherInterface
    {
        return $this->dataFetcher;
    }

    /**
     * Define the hydration schema based on a query object.
     * Fills the Formatter's properties using a Criteria as source
     *
     * @param \Propel\Runtime\ActiveQuery\BaseModelCriteria $criteria
     * @param \Propel\Runtime\DataFetcher\DataFetcherInterface|null $dataFetcher
     *
     * @throws \LogicException
     *
     * @return $this The current formatter object
     */
    public function init(BaseModelCriteria $criteria, ?DataFetcherInterface $dataFetcher = null)
    {
        $this->dbName = $criteria->getDbName();
        /** @var class-string<RowFormat> $modelClassName */
        $modelClassName = $criteria->getModelName();
        if (!$modelClassName) {
            throw new LogicException('Cannot initialize formatter on a criteria without model class name (`modelName`)');
        }
        $this->setClass($modelClassName);
        $this->setWith($criteria->getWith());
        $this->asColumns = $criteria->getAsColumns();
        $this->hasLimit = $criteria->getLimit() != -1;
        if ($dataFetcher) {
            $this->setDataFetcher($dataFetcher);
        }

        return $this;
    }

    // DataObject getters & setters

    /**
     * @param string $dbName
     *
     * @return void
     */
    public function setDbName(string $dbName): void
    {
        $this->dbName = $dbName;
    }

    /**
     * @return string|null
     */
    public function getDbName(): ?string
    {
        return $this->dbName;
    }

    /**
     * @psalm-param class-string<RowFormat> $class
     *
     * @param string $class
     *
     * @return void
     */
    public function setClass(string $class): void
    {
        $this->class = $class;
        $this->tableMap = $class::TABLE_MAP;
    }

    /**
     * @return string|null
     */
    public function getClass(): ?string
    {
        return $this->class;
    }

    /**
     * @param array $withs
     *
     * @return void
     */
    public function setWith(array $withs = []): void
    {
        $this->with = $withs;
    }

    /**
     * @return array<\Propel\Runtime\ActiveQuery\ModelWith>
     */
    public function getWith(): array
    {
        return $this->with;
    }

    /**
     * @param array<string, string> $asColumns
     *
     * @return void
     */
    public function setAsColumns(array $asColumns = []): void
    {
        $this->asColumns = $asColumns;
    }

    /**
     * @return array<string, string>
     */
    public function getAsColumns(): array
    {
        return $this->asColumns;
    }

    /**
     * @param bool $hasLimit
     *
     * @return void
     */
    public function setHasLimit(bool $hasLimit = false): void
    {
        $this->hasLimit = $hasLimit;
    }

    /**
     * @return bool
     */
    public function hasLimit(): bool
    {
        return $this->hasLimit;
    }

    /**
     * Returns a Collection object or a simple array.
     *
     * @return ListType
     */
    protected function getCollection()
    {
        $class = $this->getCollectionClassName();
        if (!$class) {
            $collection = [];
        } else {
            $collection = new $class();
            $collection->setModel((string)$this->class);
            $collection->setFormatter($this);
        }

        /** @var ListType $collection */
        return $collection;
    }

    /**
     * @psalm-return class-string<\Propel\Runtime\Collection\Collection<RowFormat>|null
     *
     * @return string|null
     */
    public function getCollectionClassName(): ?string
    {
        return null;
    }

    /**
     * Formats an ActiveRecord object
     *
     * @param \Propel\Runtime\ActiveRecord\ActiveRecordInterface|null $record the object to format
     *
     * @return mixed|null The original record
     */
    public function formatRecord(?ActiveRecordInterface $record = null)
    {
        return $record;
    }

    /**
     * @param \Propel\Runtime\DataFetcher\DataFetcherInterface|null $dataFetcher
     *
     * @return ListType
     */
    abstract public function format(?DataFetcherInterface $dataFetcher = null);

    /**
     * @param \Propel\Runtime\DataFetcher\DataFetcherInterface|null $dataFetcher
     *
     * @return RowFormat|null
     */
    abstract public function formatOne(?DataFetcherInterface $dataFetcher = null);

    /**
     * @return bool
     */
    abstract public function isObjectFormatter(): bool;

    /**
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return void
     */
    public function checkInit(): void
    {
        if ($this->tableMap === null) {
            throw new PropelException('You must initialize a formatter object before calling format() or formatOne()');
        }
    }

    /**
     * @return \Propel\Runtime\Map\TableMap
     */
    public function getTableMap(): TableMap
    {
        return Propel::getServiceContainer()->getDatabaseMap($this->dbName)->getTableByPhpName((string)$this->class);
    }

    /**
     * @return bool
     */
    protected function isWithOneToMany(): bool
    {
        foreach ($this->with as $modelWith) {
            if ($modelWith->isWithOneToMany()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gets a Propel object hydrated from a selection of columns in statement row
     *
     * @param array $row associative array indexed by column number,
     *                      as returned by DataFetcher::fetch()
     * @param string $class The classname of the object to create
     * @param int $col The start column for the hydration (modified)
     *
     * @return \Propel\Runtime\ActiveRecord\ActiveRecordInterface
     */
    public function getSingleObjectFromRow(array $row, string $class, int &$col = 0): ActiveRecordInterface
    {
        $obj = new $class();
        $col = $obj->hydrate($row, $col, false, $this->getDataFetcher()->getIndexType());

        /** @phpstan-var \Propel\Runtime\ActiveRecord\ActiveRecordInterface */
        return $obj;
    }
}
