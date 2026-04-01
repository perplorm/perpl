
    /**
     * Field name lookup.
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldNames[self::TYPE_PHPNAME][0] = 'Id'
     *
     * @var array<string, list<string>|list<int>>
     */
    protected static $fieldNames = [
        // phpcs:disable SlevomatCodingStandard.Whitespaces.DuplicateSpaces.DuplicateSpaces
        self::TYPE_PHPNAME =>   [<?= $fieldNamesPhpName ?>],
        self::TYPE_CAMELNAME => [<?= $fieldNamesCamelCaseName ?>],
        self::TYPE_COLNAME =>   [<?= $fieldNamesColname ?>],
        self::TYPE_FIELDNAME => [<?= $fieldNamesFieldName ?>],
        self::TYPE_NUM =>       [<?= $fieldNamesNum ?>],
        // phpcs:enable
    ];

    /**
     * Maps field names in different cases to field index. 
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     *
     * @var array<string, array<string, int>|list<int>>
     */
    protected static $fieldKeys = [
        // phpcs:disable SlevomatCodingStandard.Whitespaces.DuplicateSpaces.DuplicateSpaces
        self::TYPE_PHPNAME =>   [<?= $fieldKeysPhpName ?>],
        self::TYPE_CAMELNAME => [<?= $fieldKeysCamelCaseName ?>],
        self::TYPE_COLNAME =>   [<?= $fieldKeysColname ?>],
        self::TYPE_FIELDNAME => [<?= $fieldKeysFieldName ?>],
        self::TYPE_NUM =>       [<?= $fieldKeysNum ?>],
        // phpcs:enable
    ];
