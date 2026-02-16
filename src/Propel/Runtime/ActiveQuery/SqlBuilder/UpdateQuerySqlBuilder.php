<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Propel\Runtime\ActiveQuery\SqlBuilder;

use Propel\Runtime\ActiveQuery\Criteria;

/**
 * This class produces the base object class (e.g. BaseMyTable) which contains
 * all the custom-built accessor and setter methods.
 */
class UpdateQuerySqlBuilder extends AbstractSqlQueryBuilder
{
    /**
     * @param \Propel\Runtime\ActiveQuery\Criteria $criteria
     */
    public function __construct(Criteria $criteria)
    {
        parent::__construct($criteria);
    }

    /**
     * @param string $realTableName
     * @param array<\Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface> $columnFilters
     * @param array<\Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\UpdateColumn\AbstractUpdateColumn> $updateValues
     *
     * @return \Propel\Runtime\ActiveQuery\SqlBuilder\PreparedStatementDto
     */
    public function build(string $realTableName, array $columnFilters, array $updateValues): PreparedStatementDto
    {
        [$realTableName, $aliasedTableName] = $this->getTableNameWithAlias($realTableName);

        $updateSql = ['UPDATE'];
        $queryComment = $this->criteria->getComment();
        if ($queryComment) {
            $updateSql[] = '/* ' . $queryComment . ' */';
        }
        $updateSql[] = $this->quoteIdentifierTable($aliasedTableName);
        $updateSql[] = 'SET';
        $updateSql[] = $this->buildAssignmentList($realTableName, $updateValues);

        $params = $this->buildParamsFromUpdateValues($updateValues);
        $whereClause = $this->buildWhereClause($columnFilters, $params);
        if ($whereClause) {
            $updateSql[] = 'WHERE';
            $updateSql[] = $whereClause;
        }
        $updateSql = implode(' ', $updateSql);

        return new PreparedStatementDto($updateSql, $params);
    }

    /**
     * @param string $tableName
     * @param array<\Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\UpdateColumn\AbstractUpdateColumn> $updateColumns
     *
     * @return string
     */
    protected function buildAssignmentList(string $tableName, array $updateColumns): string
    {
        $positionIndex = 1;
        $assignmentClauses = [];
        foreach ($updateColumns as $updateColumn) {
            $assignmentClauses[] = $updateColumn->buildAssignmentClause($positionIndex);
        }

        return implode(', ', $assignmentClauses);
    }

    /**
     * @param array<\Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface> $columnFilters
     * @param array<mixed>|null $params
     *
     * @return string|null
     */
    protected function buildWhereClause(array $columnFilters, ?array &$params): ?string
    {
        if (!$columnFilters) {
            return null;
        }

        $whereClause = [];
        foreach ($columnFilters as $filter) {
            $whereClause[] = $this->buildStatementFromCriterion($filter, $params);
        }

        return implode(' AND ', $whereClause);
    }
}
