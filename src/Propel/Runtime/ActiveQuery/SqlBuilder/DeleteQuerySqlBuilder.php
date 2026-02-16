<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Propel\Runtime\ActiveQuery\SqlBuilder;

use Propel\Runtime\ActiveQuery\Criteria;

class DeleteQuerySqlBuilder extends AbstractSqlQueryBuilder
{
    /**
     * Build a Sql DELETE statement which deletes all data
     *
     * @param \Propel\Runtime\ActiveQuery\Criteria $criteria
     * @param string $tableName
     *
     * @return \Propel\Runtime\ActiveQuery\SqlBuilder\PreparedStatementDto
     */
    public static function createDeleteAllSql(Criteria $criteria, string $tableName): PreparedStatementDto
    {
        $builder = new self($criteria);
        $sqlStatement = $builder->buildDeleteFromClause($tableName);

        return new PreparedStatementDto($sqlStatement);
    }

    /**
     * Create a Sql DELETE statement.
     *
     * @param string $tableName
     * @param array<\Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface> $columnFilters
     *
     * @return \Propel\Runtime\ActiveQuery\SqlBuilder\PreparedStatementDto
     */
    public function build(string $tableName, array $columnFilters): PreparedStatementDto
    {
        $deleteFrom = $this->buildDeleteFromClause($tableName);
        $whereDto = $this->buildWhereClause($columnFilters);
        $where = $whereDto->getSqlStatement();
        $deleteStatement = "$deleteFrom $where";
        $params = $whereDto->getParameters();

        return new PreparedStatementDto($deleteStatement, $params);
    }

    /**
     * @param string $tableName
     *
     * @return string
     */
    protected function buildDeleteFromClause(string $tableName): string
    {
        $sql = ['DELETE'];
        $queryComment = $this->criteria->getComment();
        if ($queryComment) {
            $sql[] = '/* ' . $queryComment . ' */';
        }

        $realTableName = $this->criteria->getTableForAlias($tableName);
        if (!$realTableName) {
            $tableName = $this->quoteIdentifierTable($tableName);
            $sql[] = "FROM $tableName";
        } else {
            $realTableName = $this->quoteIdentifierTable($realTableName);
            if ($this->adapter->supportsAliasesInDelete()) {
                $sql[] = $tableName;
            }
            $sql[] = "FROM $realTableName AS $tableName";
        }

        return implode(' ', $sql);
    }

    /**
     * Build WHERE clause from the given column names.
     *
     * @param array<\Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface> $columnFilters
     *
     * @return \Propel\Runtime\ActiveQuery\SqlBuilder\PreparedStatementDto
     */
    protected function buildWhereClause(array $columnFilters): PreparedStatementDto
    {
        $whereClause = [];
        $params = [];
        foreach ($columnFilters as $filter) {
            $whereClause[] = $this->buildStatementFromCriterion($filter, $params);
        }
        $whereStatement = 'WHERE ' . implode(' AND ', $whereClause);

        return new PreparedStatementDto($whereStatement, $params);
    }
}
