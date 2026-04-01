    /**
     * @deprecated Not used. If you know the class, generate dot-path yourself.
     *
     * @var string
     */
    public const CLASS_NAME = '<?= $className ?>';

    /**
     * Default database name.
     *
     * @var string
     */
    public const DATABASE_NAME = '<?= $dbName ?>';

    /**
     * @var string
     */
    public const TABLE_NAME = '<?= $tableName ?>';

    /**
     * @var string
     */
    public const TABLE_PHP_NAME = '<?= $tablePhpName ?>';
    
    /**
     * <?= $isAbstract ? 'Default model class (abstract)' : 'Model class'?> 
     *
     * @var class-string<<?= $omClassNameFq ?>>
     */
    public const OM_CLASS = <?= $omClassName ?>::class;

    /**
     * @deprecated Not needed (generate from {@see self::OM_CLASS}.
     *
     * @var string
     */
    public const CLASS_DEFAULT = '<?= $stubClassPath ?>';

    /**
     * @var int
     */
    public const NUM_COLUMNS = <?= $nbColumns ?>;

    /**
     * @var int
     */
    public const NUM_LAZY_LOAD_COLUMNS = <?= $nbLazyLoadColumns ?>;

    /**
     * The number of columns to hydrate (NUM_COLUMNS - NUM_LAZY_LOAD_COLUMNS)
     *
     * @var int
     */
    public const NUM_HYDRATE_COLUMNS = <?= $nbHydrateColumns ?>;
<?php foreach ($columns as $col) : ?>

    /**
     * Identifies the [<?= $col->getName() ?>] column
     *
     * @var string
     */
    public const <?= $col->getConstantName() ?> = '<?= $tableName ?>.<?= $col->getName() ?>';
<?php endforeach; ?>

    /**
     * The default string format for model objects of the related table
     *
     * @var string
     */
    public const DEFAULT_STRING_FORMAT = '<?= $stringFormat ?>';

    /**
     * @var class-string<<?= $objectCollectionType ?>>
     */
    public const DEFAULT_OBJECT_COLLECTION = <?= $objectCollectionClassName ?>::class;
