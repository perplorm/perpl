<?php

declare(strict_types = 1);

namespace Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\UpdateColumn;

use PDO;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Map\ColumnMap;
use function trigger_error;
use const E_USER_NOTICE;

/**
 * Update column expression with a value.
 */
class UpdateColumn extends AbstractUpdateColumn
{
    /**
     * Build update column expression with a value.
     *
     * @param \Propel\Runtime\ActiveQuery\Criteria $sourceQuery
     * @param \Propel\Runtime\Map\ColumnMap|string $columnIdentifierOrMap
     * @param mixed $value
     * @param int|null $pdoType
     *
     * @return self
     */
    public static function build(Criteria $sourceQuery, $columnIdentifierOrMap, $value, ?int $pdoType = null)
    {
        if ($columnIdentifierOrMap instanceof ColumnMap) {
            $columnMap = $columnIdentifierOrMap;
            $tableAlias = $sourceQuery->getTableNameInQuery() ?? $columnMap->getTableName();
        } else {
            $resolvedColumn = $sourceQuery->resolveColumn($columnIdentifierOrMap);
            $tableAlias = $resolvedColumn->getTableAlias();
            if (!$resolvedColumn->hasColumnMap()) {
                if ($pdoType === null) {
                    $message = "Could not resolve column '$columnIdentifierOrMap', assuming PDO type is string. Consider setting PDO type yourself.";
                    trigger_error($message, E_USER_NOTICE);
                    $pdoType = PDO::PARAM_STR;
                }

                return new self($value, $sourceQuery, $tableAlias, $resolvedColumn->getColumnName(), $pdoType);
            }
            $columnMap = $resolvedColumn->getColumnMap();
        }

        return new self($value, $sourceQuery, $tableAlias, $columnMap->getName(), $pdoType ?? $columnMap->getPdoType(), $columnMap);
    }

    /**
     * Replaces question mark symbols with positional parameter placeholders (i.e. ':p2' for the second update parameter)
     *
     * @param int $positionIndex
     *
     * @return string
     */
    #[\Override]
    public function buildExpression(int &$positionIndex): string
    {
        return ':p' . $positionIndex++;
    }
}
