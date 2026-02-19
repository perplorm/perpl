
    /**
     * Filter the query by primary key
     *
     * @param mixed $key Primary key to use for the query
     *
     * @return $this
     */
    public function filterByPrimaryKey($key)
    {
<?php if (count($columnNames) === 1): ?>
        $resolvedColumn = $this->resolveLocalColumnByName('<?= $columnNames[0]?>');
        $this->addUsingOperator($resolvedColumn, $key, Criteria::EQUAL);
<?php else: foreach ($columnNames as $index => $columnName):?>
        $resolvedColumn<?= $index ?> = $this->resolveLocalColumnByName('<?= $columnName ?>');
        $this->addUsingOperator($resolvedColumn<?= $index ?>, $key[<?= $index ?>], Criteria::EQUAL);
<?php endforeach; endif; ?>

        return $this;
    }
