<?php

declare(strict_types = 1);

namespace Propel\Runtime\ActiveQuery\QueryExecutor;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\SqlBuilder\UpdateQuerySqlBuilder;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;

class UpdateQueryExecutor extends AbstractQueryExecutor
{
    /**
     * @param \Propel\Runtime\ActiveQuery\Criteria $criteria
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con
     *
     * @return int
     */
    public static function execute(Criteria $criteria, ?ConnectionInterface $con = null): int
    {
        $executor = new self($criteria, $con);

        return $executor->runUpdate();
    }

    /**
     * Method used to update rows in the DB. Rows are selected based
     * on selectCriteria and updated using values in updateValues.
     * <p>
     * Use this method for performing an update of the kind:
     * <p>
     * WHERE some_column = some value AND could_have_another_column =
     * another value AND so on.
     *
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return int The number of rows affected by last update statement.
     *             For most uses there is only one update statement executed, so this number will
     *             correspond to the number of rows affected by the call to this method.
     *             Note that the return value does require that this information is returned
     *             (supported) by the Propel db driver.
     */
    protected function runUpdate(): int
    {
        $updateValues = $this->criteria->getUpdateValues()->getUpdateValues();
        if (!$updateValues) {
            return 0;
        }

        $tableName = null;
        foreach ($updateValues as $updateValue) {
            $tableAlias = $updateValue->getTableAlias();
            if ($tableName && $tableAlias && $tableName !== $tableAlias) {
                throw new PropelException("Cannot update multiple tables in the same statement. Tables found: $tableAlias, $tableName");
            }
            $tableName = $tableAlias ?? $tableName;
        }
        $tableName = $tableName ?? $this->criteria->getTableNameInQuery();
        $filters = $this->criteria->getFilterCollector()->getColumnFilters();
        $builder = new UpdateQuerySqlBuilder($this->criteria);
        $preparedStatementDto = $builder->build($tableName, $filters, $updateValues);
        /** @var \Propel\Runtime\Connection\StatementInterface $stmt */
        $stmt = $this->executeStatement($preparedStatementDto);

        return $stmt->rowCount();
    }
}
