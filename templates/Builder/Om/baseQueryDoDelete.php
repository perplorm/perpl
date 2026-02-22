
    /**
     * Deletes all rows from the <?= $tableName ?> table.
     *
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con the connection to use
     *
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(?ConnectionInterface $con = null): int
    {
        if (!$con) {
            $con = Propel::getServiceContainer()->getWriteConnection(<?= $tableMapClassName ?>::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0;
<?php if ($emulateDeleteCascade): ?>
            $affectedRows += $this->doOnDeleteCascade($con);
<?php endif; ?>
<?php if ($emulateDeleteSetNull): ?>
            $this->doOnDeleteSetNull($con);
<?php endif; ?>
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            <?= $tableMapClassName ?>::clearInstancePool();
            <?= $tableMapClassName ?>::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

    /**
     * Performs a DELETE on the database based on the current ModelCriteria
     *
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con the connection to use
     *
     * @return int The number of affected rows (if supported by underlying database driver). This includes CASCADE-related rows
     *                         if supported by native driver or if emulated using Propel.
     */
    public function delete(?ConnectionInterface $con = null): int
    {
        if (!$con) {
            $con = Propel::getServiceContainer()->getWriteConnection(<?= $tableMapClassName ?>::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(<?= $tableMapClassName ?>::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
<?php if ($emulateDeleteCascade): ?>
            $affectedRows = (clone $criteria)->doOnDeleteCascade($con);
<?php else: ?>
            $affectedRows = 0;
<?php endif; ?>
<?php if ($emulateDeleteSetNull): ?>
            (clone $criteria)->doOnDeleteSetNull($con);
<?php endif; ?>
            <?= $tableMapClassName ?>::removeInstanceFromPool($criteria);
            $affectedRows += ModelCriteria::delete($con);
            <?= $tableMapClassName ?>::clearRelatedInstancePool();

            return $affectedRows;
        });
    }
