<?php

declare(strict_types = 1);

namespace Propel\Runtime\ActiveQuery\FilterExpression;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Map\RelationMap;

class ExistsFilter extends AbstractInnerQueryFilter
{
    /**
     * @var string
     */
    public const TYPE_EXISTS = 'EXISTS';

    /**
     * @var string
     */
    public const TYPE_NOT_EXISTS = 'NOT EXISTS';

    /**
     * Special filter for EXISTS with proper adjustments to inner query.
     *
     * @param \Propel\Runtime\ActiveQuery\ModelCriteria $outerQuery
     * @param \Propel\Runtime\Map\RelationMap $relation
     *
     * @return void
     */
    #[\Override]
    protected function initForRelation(ModelCriteria $outerQuery, RelationMap $relation): void
    {
        $joinCondition = $this->buildJoinCondition($outerQuery, $relation, true);
        $this->innerQuery->addAnd($joinCondition);
    }

    /**
     * @see AbstractNestedQueryCriterion::resolveOperator()
     *
     * @param string|null $operatorDeclaration
     *
     * @return string
     */
    #[\Override]
    protected function resolveOperator(?string $operatorDeclaration): string
    {
        return ($operatorDeclaration === static::TYPE_NOT_EXISTS) ? static::TYPE_NOT_EXISTS : static::TYPE_EXISTS;
    }

    /**
     * @see AbstractNestedQueryCriterion::processInnerQuery()
     * Allows to edit or replace the inner query before it is turned to SQL.
     *
     * @return \Propel\Runtime\ActiveQuery\Criteria
     */
    #[\Override]
    protected function processInnerQuery(): Criteria
    {
        return $this->innerQuery
            ->clearSelectColumns()
            ->addAsColumn('existsFlag', '1');
    }
}
