
    /**
     * Adds a JOIN clause to the query using the <?= $relationName ?> relation
     *
     * @param string|null $relationAlias Optional alias for the relation
     * @param string|null $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return $this
     */
    public function join<?= $relationName ?>(?string $relationAlias = null, ?string $joinType = <?= $joinType ?>)
    {
        $join = $this->createModelJoinForRelation('<?= $relationName ?>', $relationAlias, $joinType);
        $this->addJoinObject($join, $relationAlias);

        return $this;
    }
