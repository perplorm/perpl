<?php

declare(strict_types = 1);

namespace Propel\Runtime\Formatter;

use Propel\Runtime\ActiveQuery\BaseModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\DataFetcher\DataFetcherInterface;
use Propel\Runtime\Exception\LogicException;
use function array_keys;
use function serialize;

/**
 * Object formatter for Propel query
 * format() returns a ObjectCollection of Propel model objects
 *
 * @template RowFormat of \Propel\Runtime\ActiveRecord\ActiveRecordInterface
 * @template ListType of \Propel\Runtime\Collection\Collection
 * @extends \Propel\Runtime\Formatter\AbstractFormatter<RowFormat, ListType>
 */
class ObjectFormatter extends AbstractFormatter
{
    /**
     * @var array<string, RowFormat>
     */
    protected array $localInstancePool = [];

    /**
     * @var array<string>
     */
    protected array $virtualColumnNames = [];

    /**
     * @param \Propel\Runtime\ActiveQuery\BaseModelCriteria $criteria
     * @param \Propel\Runtime\DataFetcher\DataFetcherInterface|null $dataFetcher
     *
     * @return $this The current formatter object
     */
    #[\Override]
    public function init(BaseModelCriteria $criteria, ?DataFetcherInterface $dataFetcher = null)
    {
        parent::init($criteria);

        $this->virtualColumnNames = array_keys($criteria->getAsColumns());

        return $this;
    }

    /**
     * @param \Propel\Runtime\DataFetcher\DataFetcherInterface|null $dataFetcher
     *
     * @throws \Propel\Runtime\Exception\LogicException
     *
     * @return ListType
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

        if (!$this->populatesListOnTarget()) {
            // only many-to-one relationships
            foreach ($dataFetcher as $row) {
                $collection[] = $this->getAllObjectsFromRow($row);
            }
        } else {
            if ($this->hasLimit) {
                $dataFetcher->close();

                throw new LogicException('Cannot use limit() in conjunction with with() on a one-to-many relationship. Please remove the with() call, or the limit() call.');
            }
            foreach ($dataFetcher as $row) {
                $object = $this->getAllObjectsFromRow($row);
                $pk = $object->getPrimaryKey();
                $serializedPk = serialize($pk);

                if (!isset($this->localInstancePool[$serializedPk])) {
                    $this->localInstancePool[$serializedPk] = $object;
                    $collection[] = $object;
                }
            }
        }
        $dataFetcher->close();

        return $collection;
    }

    /**
     * @return class-string|null
     */
    #[\Override]
    public function getCollectionClassName(): ?string
    {
        return $this->getTableMap()->getCollectionClassName();
    }

    /**
     * @param \Propel\Runtime\DataFetcher\DataFetcherInterface|null $dataFetcher
     *
     * @throws \Propel\Runtime\Exception\LogicException
     *
     * @return RowFormat|null
     */
    #[\Override]
    public function formatOne(?DataFetcherInterface $dataFetcher = null): ?ActiveRecordInterface
    {
        $this->checkInit();
        $result = null;

        if ($this->populatesListOnTarget() && $this->hasLimit) {
            throw new LogicException('Cannot use limit() in conjunction with with() on a one-to-many relationship. Please remove the with() call, or the limit() call.');
        }

        if ($dataFetcher) {
            $this->setDataFetcher($dataFetcher);
        } else {
            $dataFetcher = $this->getDataFetcher();
        }

        foreach ($dataFetcher as $row) {
            $result = $this->getAllObjectsFromRow($row);
        }

        return $result;
    }

    /**
     * @return bool
     */
    #[\Override]
    public function isObjectFormatter(): bool
    {
        return true;
    }

    /**
     * Hydrates a series of objects from a result row
     * The first object to hydrate is the model of the Criteria
     * The following objects (the ones added by way of ModelCriteria::with()) are linked to the first one
     *
     * @param array $row associative array indexed by column number,
     *                   as returned by DataFetcher::fetch()
     *
     * @return RowFormat
     */
    public function getAllObjectsFromRow(array $row): ActiveRecordInterface
    {
        $indexType = $this->getDataFetcher()->getIndexType();

        // main object
        [$mainObject, $rowOffset] = $this->getTableMap()->populateObject($row, 0, $indexType);

        $pk = $mainObject->getPrimaryKey();
        $serializedPk = serialize($pk);

        if (isset($this->localInstancePool[$serializedPk])) {
            //if instance pooling is disabled, we need to make sure we're working on the correct (already fetched) object
            //so one-to-many relations are correctly loaded.
            $mainObject = $this->localInstancePool[$serializedPk];
        }

        /** @var array<string, object> $hydratedObjectsByPhpName */
        $hydratedObjectsByPhpName = [];

        // related objects added using populateRelation()
        foreach ($this->getRelatedModelsToPopulate() as $relationPopulator) {
            [$relatedObject, $rowOffset] = $relationPopulator->getTableMap()->populateObject($row, $rowOffset, $indexType);

            $parentAlias = $relationPopulator->getParentJoinIdentifier();

            if ($parentAlias && !isset($hydratedObjectsByPhpName[$parentAlias])) { // parent object not available (yet), current object probably referenced through parent
                continue;
            }

            $parentObject = $parentAlias === null ? $mainObject : $hydratedObjectsByPhpName[$parentAlias];
            if (!$relatedObject || $relatedObject->isPrimaryKeyNull()) {
                $relationPopulator->initRelationOnTarget($parentObject);

                continue;
            }

            $hydratedObjectsByPhpName[$relationPopulator->getJoinIdentifier()] = $relatedObject;
            $relationPopulator->addModelToTarget($relatedObject, $parentObject);
            $relationPopulator->resetPartialRelationOnTarget($parentObject);
        }

        // columns added using withColumn()
        foreach ($this->virtualColumnNames as $columnName) {
            $mainObject->setVirtualColumn($columnName, $row[$rowOffset]);
            $rowOffset++;
        }

        return $mainObject;
    }
}
