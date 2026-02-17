<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

 declare(strict_types = 1);

namespace Propel\Runtime\ActiveQuery\ColumnResolver;

use Propel\Runtime\ActiveQuery\BaseModelCriteria;
use Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression;
use Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\LocalColumnExpression;
use Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\RemoteColumnExpression;
use Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\RemoteTypedColumnExpression;
use Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\UnresolvedColumnExpression;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Exception\UnknownColumnException;
use Propel\Runtime\ActiveQuery\Exception\UnknownModelException;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveQuery\ModelJoin;
use Propel\Runtime\Exception\PropelException;
use function array_pop;
use function count;
use function explode;
use function implode;
use function sprintf;
use function strpos;

class ColumnResolver
{
    /**
     * @var string
     */
    public const COLUMN_LITERAL_PATTERN = '/[\w\\\]+\.\w+/';

    /**
     * @var \Propel\Runtime\ActiveQuery\BaseModelCriteria
     */
    protected $query;

    /**
     * @param \Propel\Runtime\ActiveQuery\BaseModelCriteria $query
     */
    public function __construct(BaseModelCriteria $query)
    {
        $this->query = $query;
    }

    /**
     * Split up table and column identifiers in a column literal.
     *
     * - `table.colum` becomes ['table', 'column']
     * - `column` becomse [null, 'column']
     *
     * @param string $columnLiteral
     *
     * @return array|array{0: string|null, 1: string}
     */
    public static function splitColumnLiteralParts(string $columnLiteral): array
    {
        $parts = explode('.', $columnLiteral);
        if (count($parts) > 2) {
            // column with schema, i.e. schema.table.column
            $name = array_pop($parts);
            $parts = [implode('.', $parts), $name];
        }

        return count($parts) > 1 ? $parts : [null, $parts[0]];
    }

    /**
     * @param string $columnIdentifier String representing the column name in a pseudo SQL clause, e.g. 'Book.Title'
     * @param bool $hasAccessToOutputColumns Set true if column is accessed from output of the query, i.e. in HAVING or when query is a subquery.
     * @param bool $failSilently
     *
     * @throws \Propel\Runtime\ActiveQuery\Exception\UnknownColumnException
     * @throws \Propel\Runtime\ActiveQuery\Exception\UnknownModelException
     *
     * @return \Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression
     */
    public function resolveColumn(string $columnIdentifier, bool $hasAccessToOutputColumns = false, bool $failSilently = true): AbstractColumnExpression
    {
        $sourceQuery = $this->query;

        if ($hasAccessToOutputColumns && $sourceQuery->getColumnForAs($columnIdentifier)) {
            return new RemoteColumnExpression($sourceQuery, null, $columnIdentifier);
        }

        if (strpos($columnIdentifier, '.') === false) {
            $prefix = (string)$sourceQuery->getModelAliasOrName();
        } else {
            // $prefix could be either class name or table name
            [$prefix, $columnIdentifier] = static::splitColumnLiteralParts($columnIdentifier);
        }

        [$tableAlias, $tableMap] = $this->resolveTableIdentifierInQuery($prefix, $sourceQuery);
        $isColumnFound = (bool)$tableAlias;

        if ($tableAlias && !$tableMap) {
            // local column (join without model)
            return new RemoteColumnExpression($sourceQuery, $tableAlias, $columnIdentifier);
        }

        if (!$isColumnFound && $sourceQuery->hasSelectQuery($prefix)) {
            return $this->getColumnFromSubQuery($sourceQuery, $sourceQuery->getSelectQuery($prefix), $prefix, $columnIdentifier, $failSilently);
        }

        if (!$isColumnFound && $sourceQuery instanceof ModelCriteria && $sourceQuery->getPrimaryCriteria()) {
            $resolvedColumn = $this->getQueryFromOuterQuery($sourceQuery, $prefix, $columnIdentifier);
            if ($resolvedColumn) {
                return $resolvedColumn;
            }
        }

        if (!$tableMap) {
            if ($failSilently) {
                return new UnresolvedColumnExpression($sourceQuery, $tableAlias ?? $prefix, $columnIdentifier);
            }

            throw new UnknownModelException(sprintf('Unknown model, alias or table "%s"', $prefix));
        }

        $columnMap = $tableMap->findColumnByName($columnIdentifier);

        if ($columnMap !== null) {
            $columnIdentifier = $columnMap->getName();

            return new LocalColumnExpression($sourceQuery, $tableAlias, $columnMap);
        } elseif ($sourceQuery->getColumnForAs($columnIdentifier)) {
            // local column
           // throw new LogicException('AS columns should not be resolved like this...');
            echo 'inv';
        }

        if (!$failSilently) {
            throw new UnknownColumnException(sprintf('Unknown column "%s" on model, alias or table "%s"', $columnIdentifier, $prefix));
        }

        return new UnresolvedColumnExpression($sourceQuery, $tableAlias ?? $prefix, $columnIdentifier);
    }

    /**
     * @param \Propel\Runtime\ActiveQuery\ModelCriteria $sourceQuery
     * @param string $prefix
     * @param string $columnIdentifier
     *
     * @return \Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression|null
     */
    protected function getQueryFromOuterQuery(ModelCriteria $sourceQuery, string $prefix, string $columnIdentifier): ?AbstractColumnExpression
    {
        if (!$sourceQuery->getPrimaryCriteria()) {
            return null;
        }

        $tableAlias = null;
        $tableMap = null;
        $parentQuery = $sourceQuery;
        while (!$tableAlias && $parentQuery->getPrimaryCriteria()) {
            $parentQuery = $parentQuery->getPrimaryCriteria();
            [$tableAlias, $tableMap] = $this->resolveTableIdentifierInQuery($prefix, $parentQuery);
        }

        if (!$tableAlias) {
            return null;
        }

        if (!$tableMap) {
            return new RemoteColumnExpression($sourceQuery, $tableAlias, $columnIdentifier);
        }

        $columnMap = $tableMap->findColumnByName($columnIdentifier);
        if ($columnMap) {
            $columnIdentifier = $columnMap->getName();
        }

        return new LocalColumnExpression($sourceQuery, $tableAlias, $columnMap);
    }

    /**
     * @param string $tableIdentifier
     * @param \Propel\Runtime\ActiveQuery\BaseModelCriteria $query
     *
     * @return array{0: ?string, 1: \Propel\Runtime\Map\TableMap|null}
     */
    protected function resolveTableIdentifierInQuery(string $tableIdentifier, BaseModelCriteria $query): array
    {
        if ($query->isIdentifiedBy($tableIdentifier)) {
            $tableMap = $query->getTableMap();
            $tableAlias = $query->getTableNameInQuery();

            return [$tableAlias, $tableMap];
        }
        $join = $query->findJoinByIdentifier($tableIdentifier);
        if ($join) {
            $tableAlias = $join->getRightTableAliasOrName();
            $tableMap = ($join instanceof ModelJoin) ? $join->getTableMap() : null;

            return [$tableAlias, $tableMap];
        }

        return [null, null];
    }

    /**
     * Special case for subquery columns
     *
     * @param \Propel\Runtime\ActiveQuery\Criteria $sourceQuery
     * @param \Propel\Runtime\ActiveQuery\Criteria $subQuery
     * @param string $tableAlias
     * @param string $columnPhpName
     * @param bool $failSilently
     *
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return \Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression
     */
    protected function getColumnFromSubQuery(
        Criteria $sourceQuery,
        Criteria $subQuery,
        string $tableAlias,
        string $columnPhpName,
        bool $failSilently = true
    ): AbstractColumnExpression {
        if ($subQuery->getColumnForAs($columnPhpName) !== null) {
            return new RemoteColumnExpression($sourceQuery, $tableAlias, $columnPhpName);
        }

        $tableMap = $subQuery instanceof ModelCriteria ? $subQuery->getTableMap() : null;
        if ($tableMap && $tableMap->hasColumnByPhpName($columnPhpName)) {
            $columnMap = $tableMap->getColumnByPhpName($columnPhpName);

            return new RemoteTypedColumnExpression($sourceQuery, $tableAlias, $columnMap->getName(), $columnMap->getPdoType(), $columnMap);
        }

        if (!$failSilently) {
            throw new PropelException(sprintf('Unknown column "%s" in the subQuery with alias "%s".', $columnPhpName, $tableAlias));
        }

        return new UnresolvedColumnExpression($sourceQuery, $tableAlias, $columnPhpName);
    }
}
