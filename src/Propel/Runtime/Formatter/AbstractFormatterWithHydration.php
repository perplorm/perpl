<?php

declare(strict_types = 1);

namespace Propel\Runtime\Formatter;

use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Collection\ArrayCollection;
use ReflectionClass;
use function in_array;

/**
 * @template RowFormat
 * @template ListType of \Traversable<RowFormat>
 * @extends \Propel\Runtime\Formatter\AbstractFormatter<RowFormat, ListType>
 */
abstract class AbstractFormatterWithHydration extends AbstractFormatter
{
    /**
     * @var array<string, array<string, array<string, mixed>>>
     */
    protected $alreadyHydratedObjects = [];

    /**
     * @var array<ListType>
     */
    protected $emptyVariable = [];

    /**
     * @param \Propel\Runtime\ActiveRecord\ActiveRecordInterface|null $record
     *
     * @return array<string, mixed> The original record turned into an array
     */
    #[\Override]
    public function formatRecord(?ActiveRecordInterface $record = null): array
    {
        return $record ? $record->toArray() : [];
    }

    /**
     * @psalm-return class-string<\Propel\Runtime\Collection\ArrayCollection>
     *
     * @return string|null
     */
    #[\Override]
    public function getCollectionClassName(): ?string
    {
        return ArrayCollection::class;
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
        $col = 0;

        // hydrate main object or take it from registry
        $mainObjectIsNew = false;
        $this->checkInit();
        /** @var \Propel\Runtime\Map\TableMap $tableMap */
        $tableMap = $this->tableMap;
        $indexType = $this->getDataFetcher()->getIndexType();
        $mainKey = $tableMap::getPrimaryKeyHashFromRow($row, 0, $indexType);
        // we hydrate the main object even in case of a one-to-many relationship
        // in order to get the $col variable increased anyway
        $mainObject = $this->getSingleObjectFromRow($row, (string)$this->class, $col);

        if (!isset($this->alreadyHydratedObjects[$this->class][$mainKey])) {
            $this->alreadyHydratedObjects[$this->class][$mainKey] = $mainObject->toArray();
            $mainObjectIsNew = true;
        }

        $hydrationChain = [];

        // related objects added using with()
        foreach ($this->getRelatedModelsToPopulate() as $relationAlias => $relationPopulator) {
            // determine class to use
            if (!$relationPopulator->isSingleTableInheritance()) {
                $class = $relationPopulator->getModelName();
            } else {
                /** @var class-string<object>|object $class */
                $class = $relationPopulator->getTableMap()::getOMClass($row, $col, false);
                $reflectionClass = new ReflectionClass($class);
                $class = $reflectionClass->getName();
                if ($reflectionClass->isAbstract()) {
                    $tableMapClass = "Map\\{$class}TableMap";
                    $col += $tableMapClass::NUM_COLUMNS;

                    continue;
                }
            }

            // hydrate related object or take it from registry
            $key = $relationPopulator->getTableMap()::getPrimaryKeyHashFromRow($row, $col, $indexType) ?? 'null';
            // we hydrate the main object even in case of a one-to-many relationship
            // in order to get the $col variable increased anyway
            $relatedObject = $this->getSingleObjectFromRow($row, $class, $col);
            if (!isset($this->alreadyHydratedObjects[$relationAlias][$key])) {
                $this->alreadyHydratedObjects[$relationAlias][$key] = $relatedObject->isPrimaryKeyNull() ? [] : $relatedObject->toArray();
            }

            if ($relationPopulator->joinsToMainModel()) {
                $arrayToAugment = &$this->alreadyHydratedObjects[$this->class][$mainKey];
            } else {
                $arrayToAugment = &$hydrationChain[$relationPopulator->getLeftPhpName()];
            }

            $relationName = $relationPopulator->getRelationName();
            if (!$relationPopulator->populatesListOnTarget()) {
                $arrayToAugment[$relationName] = &$this->alreadyHydratedObjects[$relationAlias][$key];
            } elseif (
                !isset($arrayToAugment[$relationName]) ||
                !in_array(
                    $this->alreadyHydratedObjects[$relationAlias][$key],
                    $arrayToAugment[$relationName],
                    true,
                )
            ) {
                $arrayToAugment[$relationName][] = &$this->alreadyHydratedObjects[$relationAlias][$key];
            }

            $hydrationChain[$relationPopulator->getRightPhpName()] = &$this->alreadyHydratedObjects[$relationAlias][$key];
        }

        // columns added using withColumn()
        foreach ($this->getAsColumns() as $alias => $clause) {
            $this->alreadyHydratedObjects[$this->class][$mainKey][$alias] = $row[$col];
            $col++;
        }

        if ($mainObjectIsNew) {
            return $this->alreadyHydratedObjects[$this->class][$mainKey];
        }

        // we still need to return a reference to something to avoid a warning
        return $this->emptyVariable;
    }
}
