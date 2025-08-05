
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
     * @return <?= $queryClass ?><static> The inner query object of the IN statement
     */
    public function useIn<?= $relationName ?>Query(?string $modelAlias = null, ?string $queryClass = null, string $typeOfIn = Criteria::IN)
    {
        /** @var <?= $queryClass ?><static> $q */
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
     * @return <?= $queryClass ?><static> The inner query object of the NOT IN statement
     */
    public function useNotIn<?= $relationName ?>Query(?string $modelAlias = null, ?string $queryClass = null)
    {
        /** @var <?= $queryClass ?><static> $q */
        $q = $this->useInQuery('<?= $relationName ?>', $modelAlias, $queryClass, Criteria::NOT_IN);

        return $q;
    }
