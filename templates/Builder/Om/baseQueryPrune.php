
    /**
     * Exclude object from result
     *
     * @param <?= $modelClassNameFq ?>|null <?= $varName ?> Object to remove from the list of results
     *
     * @return $this
     */
    public function prune(?<?= $modelClassName ?> <?= $varName ?> = null)
    {
        if (!<?= $varName ?>) {
            return $this;
        }

<?php if (count($columnNameAndGetterId) === 0):
        [$columnName, $getterId] = $columnNameAndGetterId[0];
?>
        $resolvedColumn = $this->resolveLocalColumnByName('<?= $columnName ?>');
        $this->addUsingOperator($resolvedColumn, <?= $varName ?>->get<?= $getterId ?>(), Criteria::NOT_EQUAL);";
<?php else: ?>
        $this
            ->combineFilters(Criteria::LOGICAL_OR)
<?php foreach ($columnNameAndGetterId as [$columnName, $getterId]): ?>
            ->addUsingOperator($this->resolveLocalColumnByName('<?= $columnName ?>'), <?= $varName ?>->get<?= $getterId ?>(), Criteria::NOT_EQUAL)
<?php endforeach; ?>
            ->endCombineFilters();
<?php endif; ?>

        return $this;
    }
