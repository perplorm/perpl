<?php

declare(strict_types = 1);

namespace Propel\Runtime\ActiveQuery\QueryExecutor;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\SqlBuilder\InsertQuerySqlBuilder;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Map\TableMap;
use Propel\Runtime\Propel;
use Throwable;
use function reset;

class InsertQueryExecutor extends AbstractQueryExecutor
{
    /**
     * @var \Propel\Runtime\Map\TableMap|null
     */
    protected $tableMap;

    /**
     * @param \Propel\Runtime\ActiveQuery\Criteria $criteria
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con
     */
    protected function __construct(Criteria $criteria, ?ConnectionInterface $con = null)
    {
        parent::__construct($criteria, $con);

        $this->tableMap = $this->criteria->getTableMap() ?? $this->findTableMapFromValues();
    }

    /**
     * Legacy way to find TableMap from update column
     *
     * @return \Propel\Runtime\Map\TableMap|null
     */
    protected function findTableMapFromValues(): ?TableMap
    {
        $columnName = $this->criteria->getUpdateValues()->getColumnExpressionsInQuery()[0];
        $updateColumn = $this->criteria->getUpdateValues()->getUpdateColumn($columnName);
        $tableAlias = $updateColumn ? $updateColumn->getTableAlias() : null;

        if (!$tableAlias) {
            return null;
        }

        $dbMap = Propel::getServiceContainer()->getDatabaseMap($this->criteria->getDbName());

        return $dbMap->getTable($tableAlias);
    }

    /**
     * @see InsertQuerySqlBuilder::runInsert()
     *
     * @param \Propel\Runtime\ActiveQuery\Criteria $criteria
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con
     *
     * @return string|int|null
     */
    public static function execute(Criteria $criteria, ?ConnectionInterface $con = null)
    {
        $executor = new self($criteria, $con);

        return $executor->runInsert();
    }

    /**
     * Method to perform inserts based on values and keys in a
     * Criteria.
     * <p>
     * If the primary key is auto incremented the data in Criteria
     * will be inserted and the auto increment value will be returned.
     * <p>
     * If the primary key is included in Criteria then that value will
     * be used to insert the row.
     * <p>
     * If no primary key is included in Criteria then we will try to
     * figure out the primary key from the database map and insert the
     * row with the next available id using util.db.IDBroker.
     * <p>
     * If no primary key is defined for the table the values will be
     * inserted as specified in Criteria and null will be returned.
     *
     * @return string|int|null The primary key for the new row if the primary key is auto-generated. Otherwise will return null.
     */
    protected function runInsert()
    {
        $this->setIdFromSequence();
        $preparedStatementDto = InsertQuerySqlBuilder::createInsertSql($this->criteria);
        $this->executeStatement($preparedStatementDto);

        return $this->retrieveLastInsertedId();
    }

    /**
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return void
     */
    protected function setIdFromSequence(): void
    {
        $pkFullName = $this->getFirstPkFullyQualifiedName();
        if (!$pkFullName || !$this->tableMap->isUseIdGenerator() || !$this->adapter->isGetIdBeforeInsert()) {
            return;
        }

        if ($this->criteria->getUpdateValue($pkFullName) !== null) {
            return;
        }

        $keyInfo = $this->tableMap->getPrimaryKeyMethodInfo();
        $id = null;
        try {
            $id = $this->adapter->getId($this->con, $keyInfo);
        } catch (Throwable $e) {
            throw new PropelException('Unable to get sequence id.', 0, $e);
        }
        $this->criteria->setUpdateValue($pkFullName, $id);
    }

    /**
     * @return string|null
     */
    protected function getFirstPkFullyQualifiedName(): ?string
    {
        if (!$this->tableMap) {
            return null;
        }
        $pks = $this->tableMap->getPrimaryKeys();
        $firstPk = reset($pks);

        return $firstPk ? $firstPk->getFullyQualifiedName() : null;
    }

    /**
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return string|int|null
     */
    protected function retrieveLastInsertedId()
    {
        if ($this->tableMap === null || !$this->tableMap->isUseIdGenerator() || !$this->adapter->isGetIdAfterInsert()) {
            return null;
        }
        $keyInfo = $this->tableMap->getPrimaryKeyMethodInfo();
        try {
            return $this->adapter->getId($this->con, $keyInfo);
        } catch (Throwable $e) {
            throw new PropelException('Unable to get autoincrement id.', 0, $e);
        }
    }
}
