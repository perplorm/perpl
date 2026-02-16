<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
