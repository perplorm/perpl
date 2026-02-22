
    /**
     * Returns a new <?= $stubQueryClassName ?> object.
     *
     * @param string|null $modelAlias The alias of a model in the query
     * @param \Propel\Runtime\ActiveQuery\Criteria|null $criteria Optional Criteria to build the query from
     *
     * @return <?= $stubQueryClassNameFq ?><null>
     */
    public static function create(?string $modelAlias = null, ?Criteria $criteria = null): Criteria
    {
        if ($criteria instanceof <?= $stubQueryClassName ?>) {
            return $criteria;
        }
        $query = new <?= $stubQueryClassName ?>();
        if ($modelAlias !== null) {
            $query->setModelAlias($modelAlias);
        }
        if ($criteria instanceof Criteria) {
            $query->mergeWith($criteria);
        }

        return $query;
    }
