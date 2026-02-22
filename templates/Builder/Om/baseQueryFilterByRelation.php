
    /**
     * Filter the query by a related <?= $targetClassName ?> object
     *
<?php if ($isComposite): ?>
     * @param <?= $targetClassNameFq ?>|null <?= $varName ?> The related object to use as filter
<?php else: ?>
     * @param <?= $targetClassNameFq ?>|\Propel\Runtime\Collection\ObjectCollection<<?= $targetClassNameFq ?>> <?= $varName ?> The related object(s) to use as filter
<?php endif; ?>
     * @param string|null $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return static
     */
    public function filterBy<?= $relationName ?>(<?= $varName ?>, ?string $comparison = null)
    {
        if (<?= $varName ?> instanceof <?= $targetClassName ?>) {
<?php foreach ($columnNameAndValueStatement as [$columnName, $valueStatement]): ?>
                $this->addUsingOperator($this->resolveLocalColumnByName('<?= $columnName ?>'), <?= $valueStatement ?>, $comparison);
<?php endforeach; ?>
<?php if (!$isComposite): ?>
        } elseif (<?= $varName ?> instanceof ObjectCollection) {
            $this->addUsingOperator(
                $this->resolveLocalColumnByName('<?= $localColumnName ?>'),
                <?= $varName ?>->toKeyValue('<?= $keyColumn ?>', '<?= $foreignColumnName ?>'),
                $comparison ??= Criteria::IN,
            );
<?php endif; ?>
        } else {
            throw new PropelException('filterBy<?= $relationName ?>() only accepts arguments of type <?= $targetClassName ?><?= $isComposite ? ' or Collection' : '' ?>');
        }

        return $this;
    }
