
    /**
     * Filter the query on the <?= $colName ?> column
     *
     * @param mixed|null <?= $variableName ?> The value to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::CONTAINS_ALL
     *
     * @return $this
     */
    public function filterBy<?= $singularPhpName ?>(<?= $variableName ?> = null, ?string $comparison = null)
    {
        $this->filterBy<?= $colPhpName ?>(<?= $variableName ?>, $comparison);

        return $this;
    }
