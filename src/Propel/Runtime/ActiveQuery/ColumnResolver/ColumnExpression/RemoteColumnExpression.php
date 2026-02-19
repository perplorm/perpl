<?php

declare(strict_types = 1);

namespace Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression;

use Propel\Runtime\ActiveQuery\ColumnResolver\ColumnResolver;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * A column that comes from a subquery or parent query and has no type information.
 */
class RemoteColumnExpression extends AbstractColumnExpression
{
    /**
     * A column that comes from a subquery or parent query and has no type information.
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
}
