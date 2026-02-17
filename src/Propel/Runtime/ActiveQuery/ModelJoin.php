<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Propel\Runtime\ActiveQuery;

use Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\LocalColumnExpression;
use Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilter;
use Propel\Runtime\ActiveQuery\FilterExpression\JoinCondition;
use Propel\Runtime\Exception\LogicException;
use Propel\Runtime\Map\RelationMap;
use Propel\Runtime\Map\TableMap;
use function get_class;

/**
 * A ModelJoin is a Join object tied to a RelationMap object
 */
class ModelJoin extends Join
{
    /**
     * @var \Propel\Runtime\Map\RelationMap
     */
    protected $relationMap;

    /**
     * @var \Propel\Runtime\Map\TableMap|null
     */
    protected $tableMap;

    /**
     * @var \Propel\Runtime\ActiveQuery\ModelJoin|null
     */
    protected $previousJoin;

    /**
     * @param \Propel\Runtime\Map\RelationMap $relationMap
     * @param string|null $leftTableAlias
     * @param string|null $rightTableAlias
     *
     * @return $this
     */
    public function setRelationMap(RelationMap $relationMap, ?string $leftTableAlias = null, ?string $rightTableAlias = null)
    {
        $leftCols = $relationMap->getLeftColumns();
        $rightCols = $relationMap->getRightColumns();
        $leftValues = $relationMap->getLocalValues();
        $nbColumns = $relationMap->countColumnMappings();

        for ($i = 0; $i < $nbColumns; $i++) {
            if ($leftValues[$i] !== null) {
                if ($relationMap->getType() === RelationMap::ONE_TO_MANY) {
                    //one-to-many
                    $this->addForeignValueCondition(
                        $rightCols[$i]->getTableName(),
                        $rightCols[$i]->getName(),
                        $rightTableAlias,
                        $leftValues[$i],
                        Criteria::EQUAL,
                    );
                } else {
                    //many-to-one
                    $this->addLocalValueCondition(
                        $leftCols[$i]->getTableName(),
                        $leftCols[$i]->getName(),
                        $leftTableAlias,
                        $leftValues[$i],
                        Criteria::EQUAL,
                    );
                }
            } else {
                $this->addExplicitCondition(
                    $leftCols[$i]->getTableName(),
                    $leftCols[$i]->getName(),
                    $leftTableAlias,
                    $rightCols[$i]->getTableName(),
                    $rightCols[$i]->getName(),
                    $rightTableAlias,
                    Criteria::EQUAL,
                );
            }
        }
        $this->relationMap = $relationMap;

        return $this;
    }

    /**
     * Inits the join as per default {@see static::setRelationMap()} and generates the join condition.
     *
     * Since the condition is built from the relation map, it is slightly more efficient. However, adding
     * join conditions later requires to run {@see Join::buildJoinCondition()} manually, as the query will
     * use the existing condition.
     *
     * @param \Propel\Runtime\ActiveQuery\Criteria $leftQuery
     * @param \Propel\Runtime\Map\RelationMap $relationMap
     * @param string|null $leftTableAlias
     * @param string|null $rightTableAlias
     *
     * @return void
     */
    public function setupJoinCondition(Criteria $leftQuery, RelationMap $relationMap, ?string $leftTableAlias = null, ?string $rightTableAlias = null): void
    {
        $this->setRelationMap($relationMap, $leftTableAlias, $rightTableAlias);
        $leftCols = $relationMap->getLeftColumns();
        $rightCols = $relationMap->getRightColumns();
        $leftValues = $relationMap->getLocalValues();
        $nbColumns = $relationMap->countColumnMappings();

        /** @var \Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface|null $joinCondition */
        $joinCondition = null;

        $leftTableAlias = $leftQuery->getTableNameInQuery();
        for ($i = 0; $i < $nbColumns; $i++) {
            $rightTableAlias = $rightTableAlias ?: $rightCols[$i]->getTableName();
            if ($leftValues[$i] !== null) {
                $column = $relationMap->getType() === RelationMap::ONE_TO_MANY
                    ? new LocalColumnExpression($leftQuery, $rightTableAlias, $rightCols[$i])
                    : new LocalColumnExpression($leftQuery, $leftTableAlias, $leftCols[$i]);
                $filter = new ColumnFilter($leftQuery, $column, self::EQUAL, $leftValues[$i]);
            } else {
                $leftColumn = new LocalColumnExpression($leftQuery, $leftTableAlias, $leftCols[$i]);
                $rightColumn = new LocalColumnExpression($leftQuery, $rightTableAlias, $rightCols[$i]);
                $filter = new JoinCondition($leftQuery, $leftColumn, self::EQUAL, $rightColumn);
            }

            if ($joinCondition === null) {
                $joinCondition = $filter;
            } else {
                $joinCondition->addAnd($filter);
            }
        }

        $this->joinCondition = $joinCondition;
    }

    /**
     * @return \Propel\Runtime\Map\RelationMap|null
     */
    public function getRelationMap(): ?RelationMap
    {
        return $this->relationMap;
    }

    /**
     * Sets the right tableMap for this join
     *
     * @param \Propel\Runtime\Map\TableMap $tableMap The table map to use
     *
     * @return $this
     */
    public function setTableMap(TableMap $tableMap)
    {
        $this->tableMap = $tableMap;

        return $this;
    }

    /**
     * Gets the right tableMap for this join
     *
     * @return \Propel\Runtime\Map\TableMap|null The table map
     */
    public function getTableMap(): ?TableMap
    {
        if ($this->tableMap === null && $this->relationMap !== null) {
            $this->tableMap = $this->relationMap->getRightTable();
        }

        return $this->tableMap;
    }

    /**
     * Gets the right tableMap for this join
     *
     * @throws \Propel\Runtime\Exception\LogicException
     *
     * @return \Propel\Runtime\Map\TableMap The table map
     */
    public function getTableMapOrFail(): TableMap
    {
        $tableMap = $this->getTableMap();
        if ($tableMap == null) {
            throw new LogicException('TableMap is not defined');
        }

        return $tableMap;
    }

    /**
     * @param \Propel\Runtime\ActiveQuery\ModelJoin $join
     *
     * @return $this
     */
    public function setPreviousJoin(ModelJoin $join)
    {
        $this->previousJoin = $join;

        return $this;
    }

    /**
     * @return self|null
     */
    public function getPreviousJoin(): ?self
    {
        return $this->previousJoin;
    }

    /**
     * @return bool
     */
    public function isPrimary(): bool
    {
        return $this->previousJoin === null;
    }

    /**
     * @param string $relationAlias
     *
     * @return $this
     */
    public function setRelationAlias(string $relationAlias)
    {
        $this->setRightTableAlias($relationAlias);

        return $this;
    }

    /**
     * @return string|null
     */
    public function getRelationAlias(): ?string
    {
        return $this->getRightTableAlias();
    }

    /**
     * @return bool
     */
    public function hasRelationAlias(): bool
    {
        return $this->hasRightTableAlias();
    }

    /**
     * @return bool
     */
    #[\Override]
    public function isIdentifierQuotingEnabled(): bool
    {
        return $this->getTableMap()->isIdentifierQuotingEnabled();
    }

    /**
     * This method returns the last related, but already hydrated object up until this join
     * Starting from $startObject and continuously calling the getters to get
     * to the base object for the current join.
     *
     * This method only works if PreviousJoin has been defined,
     * which only happens when you provide dotted relations when calling join
     *
     * @param object $startObject the start object all joins originate from and which has already hydrated
     *
     * @return object The base Object of this join
     */
    public function getObjectToRelate(object $startObject): object
    {
        if ($this->isPrimary()) {
            return $startObject;
        }

        $previousJoin = $this->getPreviousJoin();
        $previousObject = $previousJoin->getObjectToRelate($startObject);
        $method = 'get' . $previousJoin->getRelationMap()->getName();

        return $previousObject->$method();
    }

    /**
     * @param \Propel\Runtime\ActiveQuery\Join $join
     *
     * @return bool
     */
    #[\Override]
    public function equals(Join $join): bool
    {
        /** @phpstan-var \Propel\Runtime\ActiveQuery\ModelJoin $join */
        return parent::equals($join)
            && $this->relationMap == $join->getRelationMap()
            && $this->previousJoin == $join->getPreviousJoin()
            && $this->rightTableAlias == $join->getRightTableAlias();
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return parent::toString()
            . ' tableMap: ' . ($this->tableMap ? get_class($this->tableMap) : 'null')
            . ' relationMap: ' . $this->relationMap->getName()
            . ' previousJoin: ' . ($this->previousJoin ? '(' . $this->previousJoin . ')' : 'null')
            . ' relationAlias: ' . $this->rightTableAlias;
    }

    /**
     * @param string $identifier
     *
     * @return bool
     */
    #[\Override]
    public function isIdentifiedBy(string $identifier): bool
    {
        return parent::isIdentifiedBy($identifier)
            || $this->getTableMapOrFail()->isIdentifiedBy($identifier);
    }
}
