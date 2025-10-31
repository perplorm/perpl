
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
     * @return <?= $queryClass ?><static> The inner query object of the EXISTS statement
     */
    public function use<?= $relationName ?>ExistsQuery(?string $modelAlias = null, ?string $queryClass = null, string $typeOfExists = '<?= $existsType ?>')
    {
        /** @var <?= $queryClass ?><static> $q */
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
     * @return <?= $queryClass ?><static> The inner query object of the NOT EXISTS statement
     */
    public function use<?= $relationName ?>NotExistsQuery(?string $modelAlias = null, ?string $queryClass = null)
    {
        /** @var <?= $queryClass ?><static> $q*/
        $q = $this->useExistsQuery('<?= $relationName ?>', $modelAlias, $queryClass, '<?= $notExistsType ?>');

        return $q;
    }
