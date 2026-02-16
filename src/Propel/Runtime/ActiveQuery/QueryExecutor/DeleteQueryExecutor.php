<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Propel\Runtime\ActiveQuery\QueryExecutor;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\SqlBuilder\DeleteQuerySqlBuilder;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;

class DeleteQueryExecutor extends AbstractQueryExecutor
{
    /**
     * @param \Propel\Runtime\ActiveQuery\Criteria $criteria
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con a connection object
     *
     * @return int The number of deleted rows
     */
    public static function execute(Criteria $criteria, ?ConnectionInterface $con = null): int
    {
        $executor = new self($criteria, $con);

        return $executor->runDelete();
    }

    /**
     * Issue a DELETE query based on the current ModelCriteria
     *
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return int The number of deleted rows
     */
    protected function runDelete(): int
    {
        if ($this->criteria->getJoins()) {
            throw new PropelException('Delete does not support join');
        }

        $tableFilters = $this->criteria->getColumnFilters();
        if (!$tableFilters) {
            throw new PropelException('Cannot delete from an empty Criteria');
        }

        $tableName = $this->criteria->getTableNameInQuery();
        if (!$tableName) {
            foreach ($tableFilters as $filter) {
                if (!$filter->getTableAlias()) {
                    continue;
                }
                $tableName = $filter->getTableAlias();

                break;
            }
        }

        $builder = new DeleteQuerySqlBuilder($this->criteria);
        $preparedStatementDto = $builder->build($tableName, $tableFilters);
        /** @var \Propel\Runtime\Connection\StatementInterface $stmt */
        $stmt = $this->executeStatement($preparedStatementDto);

        return $stmt->rowCount();
    }
}
