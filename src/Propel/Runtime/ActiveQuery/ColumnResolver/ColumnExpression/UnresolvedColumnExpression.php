<?php

declare(strict_types = 1);

namespace Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression;

use Propel\Runtime\ActiveQuery\ColumnResolver\ColumnResolver;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * A column that could not (yet) be found in query.
 */
class UnresolvedColumnExpression extends AbstractColumnExpression
{
 /**
  * A column that could not (yet) be found in query.
  *
  * @param \Propel\Runtime\ActiveQuery\Criteria $sourceQuery
  * @param string|null $tableAlias
  * @param string $columnName
  */
    public function __construct(Criteria $sourceQuery, ?string $tableAlias, string $columnName)
    {
        parent::__construct($sourceQuery, $tableAlias, $columnName);
    }

    /**
     * @param \Propel\Runtime\ActiveQuery\Criteria $sourceQuery
     * @param string $columnLiteral
     *
     * @return self
     */
    public static function fromString(Criteria $sourceQuery, string $columnLiteral)
    {
        [$tableAlias, $columnName] = ColumnResolver::splitColumnLiteralParts($columnLiteral);

        return new self($sourceQuery, $tableAlias, $columnName);
    }

    /**
     * @param bool $hasAccessToOutputColumns
     *
     * @return \Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression|null
     */
    public function resolveAgain(bool $hasAccessToOutputColumns = false): ?AbstractColumnExpression
    {
        $columnIdentifier = $this->getColumnExpressionInQuery();
        $resolvedAgain = $this->sourceQuery->resolveColumn($columnIdentifier, $hasAccessToOutputColumns);

        return $resolvedAgain instanceof static ? null : $resolvedAgain;
    }
}
