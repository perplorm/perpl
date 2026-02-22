<?php
    /**
     * @var string $queryClassName
     * @var array<array{fkModelName: string, fkQueryClassNameFQ: string, relationColumnIds: array{fkColumnConstant: string, localColumnPhpName: string}}> $relationIdentifiers
     */
?>
    /**
     * This is a method for emulating ON DELETE SET NULL DBs that don't support this
     * feature (like MySQL or SQLite).
     *
     * This method is not very speedy because it must perform a query first to get
     * the implicated records and then perform the deletes by calling those query classes.
     *
     * This method should be used within a transaction if possible.
     *
     * @param \Propel\Runtime\Connection\ConnectionInterface $con
     *
     * @return void
     */
    protected function doOnDeleteSetNull(ConnectionInterface $con): void
    {
        // first find the objects that are implicated by the $this
        $objects = <?= $queryClassName ?>::create(null, $this)->find($con);
        foreach ($objects as $obj) {
<?php foreach ($relationIdentifiers as $data): ?>
            // set fkey col in related <?= $data['fkModelName'] ?> rows to NULL
            $query = new <?= $data['fkQueryClassNameFQ'] ?>();
            $updateValues = new Criteria();
 <?php foreach($data['relationColumnIds'] as $columnIds): ?>
            $query->add(<?= $columnIds['fkColumnConstant'] ?>, $obj->get<?= $columnIds['localColumnPhpName'] ?>());
            $updateValues->add(<?= $columnIds['fkColumnConstant'] ?>, null);\n";
<?php endforeach; ?>               
<?php endforeach; ?>
            $query->update($updateValues, $con);
        }
    }
