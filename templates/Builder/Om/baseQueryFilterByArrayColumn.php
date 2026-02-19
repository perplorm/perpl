
    /**
     * Filter the query on the <?= $colName ?> column
     *
     * @param mixed <?= $variableName ?> The value to use as filter
     * @param string|null $comparison Operator to use for the column comparison, defaults to Criteria::CONTAINS_ALL
     *
     * @return $this
     */
    public function filterBy<?= $singularPhpName ?>(<?= $variableName ?> = null, ?string $comparison = null)
    {
        $resolvedColumn = $this->resolveLocalColumnByName('<?= $colName ?>');
        if ($comparison == Criteria::CONTAINS_NONE) {
            <?= $variableName ?> = "%| <?= $variableName ?> |%";
            $comparison = Criteria::NOT_LIKE;
            $this->addAnd($resolvedColumn, <?= $variableName ?>, $comparison);
            $this->addOr($resolvedColumn, null, Criteria::ISNULL);

            return $this;
        }

        if (($comparison === null || $comparison == Criteria::CONTAINS_ALL) && is_scalar(<?= $variableName ?>)) {
            <?= $variableName ?> = "%| <?= $variableName ?> |%";
            $comparison = Criteria::LIKE;
        }
        $this->addUsingOperator($resolvedColumn, <?= $variableName ?>, $comparison);

        return $this;
    }
