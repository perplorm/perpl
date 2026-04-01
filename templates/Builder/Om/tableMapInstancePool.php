
    /**
     * Adds an object to the instance pool.
     *
     * Propel keeps cached copies of objects in an instance pool when they are retrieved
     * from the database. In some cases you may need to explicitly add objects
     * to the cache in order to ensure that the same objects are always returned by find*()
     * and findPk*() calls.
     *
     * @param <?= $modelClassNameFq ?> $obj
     * @param string|null $key Optional internal key if it was already generated 
     *
     * @return void
     */
    public static function addInstanceToPool($obj, string|null $key = null): void
    {
        if (!Propel::isInstancePoolingEnabled()) {
            return;
        }

        $key ??= <?= sprintf($poolKeyFromObjectStatementFormat, '$obj') ?>;

        self::$instances[$key] = $obj;
    }

    /**
     * Removes an object from the instance pool.
     *
     * Propel keeps cached copies of objects in an instance pool when they are retrieved
     * from the database. In some cases -- especially when you override doDelete
     * methods in your stub classes -- you may need to explicitly remove objects
     * from the cache in order to prevent returning objects that no longer exist.
     *
     * Passing in a Criteria clears the whole cache and is deprecated.
     *
     * @param <?= $modelClassNameFq ?>|\Propel\Runtime\ActiveQuery\Criteria|<?= $pkType ?>|null $value A <?= $modelClassName ?> object or a primary key value.
     *
     * @return void
     */
    public static function removeInstanceFromPool($value): void
    {
        if (!Propel::isInstancePoolingEnabled() || $value === null) {
            return;
        }

        if ($value instanceof Criteria) {
            self::$instances = [];

            return;
        }

        $key = $value instanceof <?= $modelClassName ?> 
            ? <?= sprintf($poolKeyFromObjectStatementFormat, '$value') ?> 
            : <?= sprintf($poolKeyFromRowStatementFormat, '$value') ?>; 

        unset(self::$instances[$key]);
    }
