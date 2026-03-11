<?php
    /**
     * @var string $queryClassName
     * @var array<array{fkModelName: string, fkQueryClassNameFQ: string, relationColumnIds: array{fkColumnConstant: string, localColumnPhpName: string}}> $relationIdentifiers
     */
?>
    /**
     * This is a method for emulating ON DELETE CASCADE for DBs that don't support this
     * feature (like MySQL or SQLite).
     *
     * This method is not very speedy because it must perform a query first to get
     * the implicated records and then perform the deletes by calling those Query classes.
     *
     * This method should be used within a transaction if possible.
     *
     * @param \Propel\Runtime\Connection\ConnectionInterface $con
     *
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    protected function doOnDeleteCascade(ConnectionInterface $con): int
    {
        // initialize var to track total num of affected rows
        $affectedRows = 0;

        // first find the objects that are implicated by the $this
        $objects = <?= $queryClassName ?>::create(null, $this)->find($con);
        foreach ($objects as $obj) {
<?php foreach ($relationIdentifiers as $data): ?>

            // delete related <?= $data['fkModelName'] ?> objects
            $affectedRows += <?= $data['fkQueryClassNameFQ'] ?>::create()
 <?php foreach($data['relationColumnIds'] as $columnIds): ?>
                ->add(<?= $columnIds['fkColumnConstant'] ?>, $obj->get<?= $columnIds['localColumnPhpName'] ?>())
<?php endforeach; ?>               
<?php endforeach; ?>
                ->delete($con);
        }

        return \$affectedRows;
    }
