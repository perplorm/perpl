<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Propel\Runtime\ActiveQuery\FilterExpression;

use Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * Join condition like "table1.col = table2.col".
 */
class JoinCondition extends AbstractColumnFilter
{
    /**
     * Join condition like "table1.col = table2.col".
     *
     * @param \Propel\Runtime\ActiveQuery\Criteria $query
     * @param \Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression $leftHandColumn
     * @param string $operator
     * @param \Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression $rightHandColumn
     */
    public function __construct(Criteria $query, AbstractColumnExpression $leftHandColumn, string $operator, AbstractColumnExpression $rightHandColumn)
    {
        parent::__construct($query, $leftHandColumn, $operator, $rightHandColumn);
    }

    /**
     * @see AbstractFilter::buildStatement()
     *
     * @return void
     */
    #[\Override]
    protected function resolveUnresolved(): void
    {
        parent::resolveUnresolved();
        $this->resolveColumn($this->value);
    }

    /**
     * @param array $paramCollector A list to which Prepared Statement parameters will be appended
     *
     * @return string
     */
    #[\Override]
    protected function buildFilterClause(array &$paramCollector): string
    {
        $leftHandExpression = $this->queryColumn->getColumnExpressionInQuery(true);
        $rightHandExpression = $this->value->getColumnExpressionInQuery(true);

        return "$leftHandExpression{$this->operator}$rightHandExpression";
    }
}
