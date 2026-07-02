
    /**
     * Use the <?= $relationName ?> relation <?= $foreignTablePhpName ?> object
     *
     * @see \Propel\Runtime\ActiveQuery\ModelCriteria::useQuery()
     *
     * @param string|null $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return <?= $queryClassFq ?><static> A secondary query class using the current class as primary query
     */
    public function use<?= $relationName ?>Query(?string $relationAlias = null, string $joinType = <?= $joinType ?>)
    {
        /** @var <?= $queryClassFq ?><static> $query */
        $query = $this->join<?= $relationName ?>($relationAlias, $joinType)
            ->useQuery($relationAlias ?: '<?= $relationName ?>', <?= $queryClass ?>::class);

        return $query;
    }
    
    /**
     * Use the <?= $relationName ?> relation <?= $foreignTablePhpName ?> object
     *
     * @param callable(<?= $queryClassFq ?><mixed>):<?= $queryClassFq ?><mixed> $callable A function working on the related query
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

    /**
     * Use the <?= $relationDescription ?> for an EXISTS query.
     *
     * @phpstan-param \Propel\Runtime\ActiveQuery\FilterExpression\ExistsFilter::TYPE_* $typeOfExists
     *     
     * @see \Propel\Runtime\ActiveQuery\ModelCriteria::useExistsQuery()
     *
     * @param string|null $modelAlias sets an alias for the nested query
     * @param class-string<\Propel\Runtime\ActiveQuery\ModelCriteria>|null $queryClass Allows to use a custom query class for the exists query, like ExtendedBookQuery::class
     * @param string $typeOfExists Either ExistsQueryCriterion::TYPE_EXISTS or ExistsQueryCriterion::TYPE_NOT_EXISTS
     *
     * @return <?= $queryClassFq ?><static> The inner query object of the EXISTS statement
     */
    public function use<?= $relationName ?>ExistsQuery(?string $modelAlias = null, ?string $queryClass = null, string $typeOfExists = 'EXISTS')
    {
        /** @var <?= $queryClassFq ?><static> $q */
        $q = $this->useExistsQuery('<?= $relationName ?>', $modelAlias, $queryClass, $typeOfExists);

        return $q;
    }

    /**
     * Use the <?= $relationDescription ?> for a NOT EXISTS query.
     *
     * @see use<?= $relationName ?>ExistsQuery()
     *
     * @param string|null $modelAlias sets an alias for the nested query
     * @param class-string<\Propel\Runtime\ActiveQuery\ModelCriteria>|null $queryClass Allows to use a custom query class for the exists query, like ExtendedBookQuery::class
     *
     * @return <?= $queryClassFq ?><static> The inner query object of the NOT EXISTS statement
     */
    public function use<?= $relationName ?>NotExistsQuery(?string $modelAlias = null, ?string $queryClass = null)
    {
        /** @var <?= $queryClassFq ?><static> $q*/
        $q = $this->useExistsQuery('<?= $relationName ?>', $modelAlias, $queryClass, 'NOT EXISTS');

        return $q;
    }

    /**
     * Use the <?= $relationDescription ?> for an IN query.
     *
     * @phpstan-param \Propel\Runtime\ActiveQuery\Criteria::*IN $typeOfIn
     *
     * @see \Propel\Runtime\ActiveQuery\ModelCriteria::useInQuery()
     *
     * @param string|null $modelAlias sets an alias for the nested query
     * @param class-string<\Propel\Runtime\ActiveQuery\ModelCriteria>|null $queryClass Allows to use a custom query class for the exists query, like ExtendedBookQuery::class
     * @param string $typeOfIn Criteria::IN or Criteria::NOT_IN
     *
     * @return <?= $queryClassFq ?><static> The inner query object of the IN statement
     */
    public function useIn<?= $relationName ?>Query(?string $modelAlias = null, ?string $queryClass = null, string $typeOfIn = Criteria::IN)
    {
        /** @var <?= $queryClassFq ?><static> $q */
        $q = $this->useInQuery('<?= $relationName ?>', $modelAlias, $queryClass, $typeOfIn);

        return $q;
    }

    /**
     * Use the <?= $relationDescription ?> for a NOT IN query.
     *
     * @see use<?= $relationName ?>InQuery()
     *
     * @param string|null $modelAlias sets an alias for the nested query
     * @param class-string<\Propel\Runtime\ActiveQuery\ModelCriteria>|null $queryClass Allows to use a custom query class for the exists query, like ExtendedBookQuery::class
     *
     * @return <?= $queryClassFq ?><static> The inner query object of the NOT IN statement
     */
    public function useNotIn<?= $relationName ?>Query(?string $modelAlias = null, ?string $queryClass = null)
    {
        /** @var <?= $queryClassFq ?><static> $q */
        $q = $this->useInQuery('<?= $relationName ?>', $modelAlias, $queryClass, Criteria::NOT_IN);

        return $q;
    }
