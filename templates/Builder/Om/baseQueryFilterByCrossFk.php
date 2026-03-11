
    /**
     * Filter the query by a related <?= $targetTableClassName ?> object
     * using the <?= $crossTableName ?> table as cross reference
     *
     * @param <?= $targetTableClassNameFq ?> <?= $varName ?> the related object to use as filter
     * @param string|null $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL and Criteria::IN for queries
     *
     * @return $this
     */
    public function filterBy<?= $crossRelationName ?>(<?= $targetTableClassName ?> <?= $varName ?>, ?string $comparison = null)
    {
        $this
            ->use<?= $relationName ?>Query()
            ->filterBy<?= $crossRelationName ?>(<?= $varName ?>, $comparison)
            ->endUse();

        return $this;
    }
