
    /**
     * Find object by primary key using raw SQL to go fast.
     * Bypass doSelect() and the object formatter by using generated code.
     *
     * @param mixed $key Primary key to use for the query
     * @param \Propel\Runtime\Connection\ConnectionInterface $con A connection object
     *
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return <?= $objectClassNameFq ?>|null A model object, or null if the key is not found
     */
    protected function findPkSimple($key, ConnectionInterface $con): ?<?= $objectClassName ?> 
    {
        $sql = '<?= $query ?>';
        $stmt = $con->prepare($sql);
        if (is_bool($stmt)) {
            throw new PropelException('Failed to initialize statement');
        }<?= $bindValueStatements ?>

        try {
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);

            throw new PropelException(sprintf('Unable to execute SELECT statement [%s]', $sql), 0, $e);
        }
<?php if ($isBulkLoad): ?>
        while (true) {
            $row = $stmt->fetch(PDO::FETCH_NUM);
            if (!$row) {
                break;
            }
<?php else: ?>
        $obj = null;
        $row = $stmt->fetch(PDO::FETCH_NUM);
        if ($row) {
<?php endif; 
    if ($classNameLiteral === '$cls'): 
?>
            <?= $classNameLiteral ?> = <?= $tableMapClassName ?>::getOMClass($row, 0, false);
            /** @var <?= $objectClassNameFq ?> $obj */
<?php endif; ?>
            $obj = new <?= $classNameLiteral ?>();
            $obj->hydrate($row);
<?php if ($isBulkLoad): ?>
            $pk = $obj->getPrimaryKey();
<?php endif; ?>
            $poolKey = <?= $buildPoolKeyStatement ?>;
            <?= $tableMapClassName ?>::addInstanceToPool($obj, $poolKey);
        }
        $stmt->closeCursor();

<?php if (!$isBulkLoad): ?>
        return $obj;
<?php else: ?>
        $poolKey = <?= $buildPoolKeyStatementFromKey ?>;
        /** @var <?= $objectClassNameFq ?> $model */
        $model = <?= $tableMapClassName ?>::getInstanceFromPool($poolKey);

        return $model;
<?php endif; ?>
    }
