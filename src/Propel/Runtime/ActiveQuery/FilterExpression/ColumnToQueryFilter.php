<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Propel\Runtime\ActiveQuery\FilterExpression;

use Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\LocalColumnExpression;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Map\RelationMap;
use function reset;
use function trim;

/**
 * Creates filters in the form "column <operator> (SELECT ...)
 */
class ColumnToQueryFilter extends AbstractInnerQueryFilter
{
    /**
     * @see AbstractInnerQueryCriterion::initRelation()
     *
     * @param \Propel\Runtime\ActiveQuery\ModelCriteria $outerQuery
     * @param \Propel\Runtime\Map\RelationMap $relation
     *
     * @return void
     */
    #[\Override]
    protected function initForRelation(ModelCriteria $outerQuery, RelationMap $relation): void
    {
        $outerColumns = $relation->getLeftColumns();
        if ($outerColumns) {
            $this->queryColumn = new LocalColumnExpression($this->query, $this->query->getTableNameInQuery(), reset($outerColumns));
        }

        $innerColumns = $relation->getRightColumns();
        if ($innerColumns && $this->innerQuery instanceof ModelCriteria) {
            $columnName = reset($innerColumns)->getFullyQualifiedName();
            $this->innerQuery->select($columnName);
        }
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
        return $operatorDeclaration ?? trim(Criteria::IN);
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
        return $this->innerQuery;
    }
}
