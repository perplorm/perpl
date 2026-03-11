
    /**
     * Use the <?= $relationName ?> relation <?= $foreignTablePhpName ?> object
     *
     * @see useQuery()
     *
     * @param string|null $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return <?= $queryClass ?><static> A secondary query class using the current class as primary query
     */
    public function use<?= $relationName ?>Query(?string $relationAlias = null, string $joinType = <?= $joinType ?>)
    {
        /** @var <?= $queryClass ?><static> $query */
        $query = $this->join<?= $relationName ?>($relationAlias, $joinType)
            ->useQuery($relationAlias ?: '<?= $relationName ?>', '<?= $queryClass ?>');

        return $query;        
    }
