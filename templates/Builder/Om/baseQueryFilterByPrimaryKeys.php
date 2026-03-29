<?php
    $pkSize = count($columnPhpNames);
?>

    /**
     * Filter the query by a list of primary keys
     *
     * @param array<<?= $pkType ?>> $keys The list of primary key values to use for the query
     *
     * @return static
     */
    public function filterByPrimaryKeys(array $keys)
    {
<?php if ($pkSize === 1): ?>
        return $this->filterBy<?= $columnPhpNames[0] ?>($keys, Criteria::IN);
<?php else: ?>
        if (!$keys) {
            return $this->addAnd('1<>1');
        }

        foreach ($keys as $key) {
            $this
                ->_or()
                ->combineFilters()
                ->filterByPrimaryKey($key)
                ->endCombineFilters();
        }

        return $this;
<?php endif; ?>
    }
