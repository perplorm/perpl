<?php

declare(strict_types = 1);

namespace Propel\Runtime\ActiveQuery\FilterExpression;

use LogicException;
use Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression;
use Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\UnresolvedColumnExpression;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveQuery\ModelJoin;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Map\RelationMap;
use function trim;

/**
 * Abstract filter for nested filter expression that bind an inner query
 * with an operator like IN, EXISTS
 *
 * @phpstan-consistent-constructor
 */
abstract class AbstractInnerQueryFilter extends AbstractFilter
{
    /**
     * @var \Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression|null Left side of the operator, can be empty.
     */
    protected $queryColumn;

    /**
     * @var string The sql operator expression, i.e. "IN" or "NOT IN".
     */
    protected $sqlOperator;

    /**
     * @var \Propel\Runtime\ActiveQuery\Criteria
     */
    protected $innerQuery;

    /**
     * Resolves the operator as given by the user to the SQL operator statement.
     *
     * @param string $operatorDeclaration
     *
     * @return string
     */
    abstract protected function resolveOperator(string $operatorDeclaration): string;

    /**
     * Allows to edit or replace the inner query before it is turned to SQL.
     *
     * @return \Propel\Runtime\ActiveQuery\Criteria
     */
    abstract protected function processInnerQuery(): Criteria;

    /**
     * Entry point for child classes to add information about the relation to the query.
     *
     * @param \Propel\Runtime\ActiveQuery\ModelCriteria $outerQuery
     * @param \Propel\Runtime\Map\RelationMap $relation
     *
     * @return void
     */
    abstract protected function initForRelation(ModelCriteria $outerQuery, RelationMap $relation): void;

    /**
     * @param bool $useQuoteIfEnable
     *
     * @return string
     */
    #[\Override]
    public function getLocalColumnName(bool $useQuoteIfEnable): string
    {
        return $this->queryColumn ? $this->queryColumn->getColumnExpressionInQuery($useQuoteIfEnable) : '';
    }

    /**
     * @return string|null
     */
    #[\Override]
    public function getColumnName(): ?string
    {
        return $this->queryColumn ? $this->queryColumn->getColumnName() : null;
    }

    /**
     * @return string
     */
    #[\Override]
    public function getOperator(): string
    {
        return $this->sqlOperator;
    }

    /**
     * @return string|null
     */
    #[\Override]
    public function getTableAlias(): ?string
    {
        return $this->queryColumn ? $this->queryColumn->getTableAlias() : null;
    }

    /**
     * @see AbstractFilter::buildStatement()
     *
     * @return void
     */
    #[\Override]
    protected function resolveUnresolved(): void
    {
        if ($this->queryColumn instanceof UnresolvedColumnExpression) {
            $this->queryColumn = $this->query->resolveColumn($this->queryColumn->getColumnExpressionInQuery());
        }
    }

    /**
     * Allows to edit or replace the inner query before it is turned to SQL.
     *
     * @param mixed $outerQuery
     * @param \Propel\Runtime\Map\RelationMap $relationMap
     * @param string|null $operator
     * @param \Propel\Runtime\ActiveQuery\ModelCriteria $innerQuery
     *
     * @return self
     */
    public static function createForRelation($outerQuery, RelationMap $relationMap, ?string $operator, ModelCriteria $innerQuery): self
    {
        $filter = new static($outerQuery, null, $operator, $innerQuery);
        $filter->initForRelation($outerQuery, $relationMap);

        return $filter;
    }

    /**
     * @param \Propel\Runtime\ActiveQuery\Criteria $outerQuery
     * @param \Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression|null $queryColumn Left side of the operator, usually a column name or empty.
     * @param string|null $operator The operator, like IN, NOT IN, EXISTS, NOT EXISTS
     * @param \Propel\Runtime\ActiveQuery\Criteria $innerQuery
     */
    final public function __construct(
        $outerQuery,
        ?AbstractColumnExpression $queryColumn,
        ?string $operator,
        Criteria $innerQuery
    ) {
        parent::__construct($outerQuery, null);

        if ($operator) {
            $operator = trim($operator); // Criteria::IN is padded with spaces
        }

        $this->queryColumn = $queryColumn;
        $this->sqlOperator = $this->resolveOperator($operator);
        $this->innerQuery = $innerQuery;

        if ($this->innerQuery instanceof ModelCriteria && $outerQuery instanceof ModelCriteria) {
            $this->innerQuery->setPrimaryCriteria($outerQuery, null); // HACK - ColumnResolver uses primary criteria to remove aliases from topmost query
        }
    }

    /**
     * Collects a Prepared Statement representation of the Criterion onto the buffer
     *
     * @param array $paramCollector A list to which Prepared Statement parameters will be appended
     *
     * @return string
     */
    #[\Override]
    protected function buildFilterClause(array &$paramCollector): string
    {
        $leftHandOperator = '';
        if ($this->queryColumn) {
            $leftHandOperator = $this->queryColumn->getColumnExpressionInQuery(true) . ' ';
        }
        $innerQuery = $this->processInnerQuery()->createSelectSql($paramCollector);
        //$this->query->replaceNames($innerQuery); // fixup column names from outer query

        return "{$leftHandOperator}$this->sqlOperator ($innerQuery)";
    }

    /**
     * @param \Propel\Runtime\ActiveQuery\ModelCriteria $outerQuery
     * @param \Propel\Runtime\Map\RelationMap $relationMap where outer query is on the left side
     * @param bool $forInnerQuery
     *
     * @throws \Propel\Runtime\Exception\PropelException
     * @throws \LogicException
     *
     * @return \Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface
     */
    protected function buildJoinCondition($outerQuery, RelationMap $relationMap, bool $forInnerQuery = false): ColumnFilterInterface
    {
        if (!$forInnerQuery) {
            $leftQuery = $outerQuery;
            $rightQuery = $this->innerQuery;
        } else {
            $leftQuery = $this->innerQuery;
            $rightQuery = $outerQuery;
            $symmetricRelation = $relationMap->getSymmetricalRelation();
            if (!$symmetricRelation) {
                throw new PropelException("Cannot find symmetrical relation for relation '{$relationMap->getName()}' on {$outerQuery->getModelAliasOrName()}");
            }
            $relationMap = $symmetricRelation;
        }
        if (!$rightQuery instanceof ModelCriteria || !$leftQuery instanceof ModelCriteria) {
            throw new PropelException('Cannot build join on regular condition');
        }
        $join = new ModelJoin();
        $leftAlias = $leftQuery->getTableNameInQuery();
        $rightAlias = $rightQuery->getTableNameInQuery();
        $join->setupJoinCondition($leftQuery, $relationMap, $leftAlias, $rightAlias);
        $joinCondition = $join->getJoinCondition();
        if (!$joinCondition) {
            throw new LogicException("No join condition can be built for relation '{$relationMap->getName()}' on {$outerQuery->getModelAliasOrName()}");
        }

        return $joinCondition;
    }
}
