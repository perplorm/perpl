
    /**
     * Use the <?= $relationName ?> relation <?= $foreignTablePhpName ?> object
     *
     * @param callable(<?= $queryClass ?><mixed>):<?= $queryClass ?><mixed> $callable A function working on the related query
     * @param string|null $relationAlias optional alias for the relation
     * @param string|null $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return $this
     */
    public function with<?= $relationName ?>Query(
        callable $callable,
        ?string $relationAlias = null,
        ?string $joinType = <?= $joinType ?> 
    ) {
        $relatedQuery = $this->use<?= $relationName ?>Query(
            $relationAlias,
            $joinType,
        );
        $callable($relatedQuery);
        $relatedQuery->endUse();

        return $this;
    }
