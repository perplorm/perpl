<?php

declare(strict_types = 1);

namespace Propel\Runtime\Formatter;

use Propel\Runtime\ActiveQuery\BaseModelCriteria;
use Propel\Runtime\DataFetcher\DataFetcherInterface;
use ReflectionClass;
use function array_keys;
use function in_array;

/**
 * Same as ObjectFormatter, except objects are turned to tuples and pooled by reference.
 * TODO: I think this should be generalized to ObjectFormatter code, and tuple/array specifics moved to ArrayFormatter
 *
 * @template RowFormat
 * @template ListType of \Traversable<RowFormat>
 * @extends \Propel\Runtime\Formatter\AbstractFormatter<RowFormat, ListType>
 */
abstract class AbstractFormatterWithHydration extends AbstractFormatter
{
    /**
     * Class name to primary key to tuple
     *
     * @var array<string, array<string, array<string, mixed>>>
     */
    protected $localTuplePool = [];

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
     * Hydrates a series of objects from a result row
     * The first object to hydrate is the model of the Criteria
     * The following objects (the ones added by way of ModelCriteria::with()) are linked to the first one
     *
     * @param array $row associative array indexed by column number,
     *                   as returned by DataFetcher::fetch()
     *
     * @return array
     */
    protected function &hydratePropelObjectCollection(array $row): array
    {
        $rowOffset = 0;
        $indexType = $this->getDataFetcher()->getIndexType();

        // hydrate main object or take it from registry
        $this->checkInit();
        /** @var \Propel\Runtime\Map\TableMap $tableMap */
        $tableMap = $this->tableMap;
        $mainKey = $tableMap::getPrimaryKeyHashFromRow($row, 0, $indexType);
        // we hydrate the main object even in case of a one-to-many relationship
        // in order to get the $col variable increased anyway
        $mainObject = $this->getSingleObjectFromRow($row, (string)$this->class, $rowOffset);

        $mainObjectIsNew = !isset($this->localTuplePool[$this->class][$mainKey]);
        if ($mainObjectIsNew) {
            $this->localTuplePool[$this->class][$mainKey] = $mainObject->toArray();
        }

        $relatedTuplesByPhpName = [];

        // related objects added using with()
        foreach ($this->getRelatedModelsToPopulate() as $relationAlias => $relationPopulator) {
            // determine class to use
            if (!$relationPopulator->isSingleTableInheritance()) {
                $class = $relationPopulator->getModelName();
            } else {
                /** @var class-string<object>|object $class */
                $class = $relationPopulator->getTableMap()::getOMClass($row, $rowOffset, false);
                $reflectionClass = new ReflectionClass($class);
                $class = $reflectionClass->getName();
                if ($reflectionClass->isAbstract()) {
                    $tableMapClass = "Map\\{$class}TableMap";
                    $rowOffset += $tableMapClass::NUM_COLUMNS;

                    continue;
                }
            }

            // hydrate related object or take it from registry
            $key = $relationPopulator->getTableMap()::getPrimaryKeyHashFromRow($row, $rowOffset, $indexType) ?? 'null';
            // we hydrate the main object even in case of a one-to-many relationship
            // in order to get the $col variable increased anyway
            $relatedObject = $this->getSingleObjectFromRow($row, $class, $rowOffset);
            if (!isset($this->localTuplePool[$relationAlias][$key])) {
                $this->localTuplePool[$relationAlias][$key] = $relatedObject->isPrimaryKeyNull() ? [] : $relatedObject->toArray();
            }
            $relatedTuple = &$this->localTuplePool[$relationAlias][$key];

            if ($relationPopulator->joinsToMainModel()) {
                $parentTuple = &$this->localTuplePool[$this->class][$mainKey];
            } else {
                $parentTuple = &$relatedTuplesByPhpName[$relationPopulator->getParentJoinIdentifier()];
            }

            $relationName = $relationPopulator->getRelationName();
            if (!$relationPopulator->populatesListOnTarget()) {
                $parentTuple[$relationName] = &$relatedTuple;
            } elseif (
                !isset($parentTuple[$relationName]) ||
                !in_array(
                    $this->localTuplePool[$relationAlias][$key],
                    $parentTuple[$relationName],
                    true,
                )
            ) {
                $parentTuple[$relationName][] = &$relatedTuple;
            }

            $relatedTuplesByPhpName[$relationPopulator->getJoinIdentifier()] = &$relatedTuple;
        }

        // columns added using withColumn()
        foreach ($this->virtualColumnNames as $columnName) {
            $this->localTuplePool[$this->class][$mainKey][$columnName] = $row[$rowOffset];
            $rowOffset++;
        }

        if ($mainObjectIsNew) { // NOTE: no ternary if, requires return by reference
            return $this->localTuplePool[$this->class][$mainKey];
        }

        $emptyTupleReference = [];

        return $emptyTupleReference;
    }
}
