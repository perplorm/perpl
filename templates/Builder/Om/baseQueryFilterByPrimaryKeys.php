
    /**
     * Filter the query by a list of primary keys
     *
     * @param array $keys The list of primary key values to use for the query
     *
     * @return static
     */
    public function filterByPrimaryKeys(array $keys)
    {
<?php if (count($pkColumnNames) === 1): ?>
        $resolvedColumn = $this->resolveLocalColumnByName('<?= $pkColumnNames[0] ?>');
        $this->addUsingOperator($resolvedColumn, $keys, Criteria::IN);
<?php else: ?>
        if (!$keys) {
            return $this->addAnd('1<>1');
        }

<?php foreach ($pkColumnNames as $index => $columnName): ?>
        $resolvedColumn<?= $index ?> = $this->resolveLocalColumnByName('<?= $columnName?>');
<?php endforeach; ?>

        foreach ($keys as $key) {
            $this
                ->_or()
                ->combineFilters()
<?php foreach ($pkColumnNames as $index => $columnName): ?>
                ->addUsingOperator($resolvedColumn<?= $index ?>, $key[<?= $index ?>], Criteria::EQUAL)
<?php endforeach; ?>
                ->endCombineFilters();
        }
<?php endif; ?>

        return $this;
    }
