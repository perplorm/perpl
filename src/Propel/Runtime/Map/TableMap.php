<?php

declare(strict_types = 1);

namespace Propel\Runtime\Map;

use Propel\Generator\Model\Datatype\ColumnType;
use Propel\Generator\Model\IdMethod;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Exception\LogicException;
use Propel\Runtime\Map\Exception\ColumnNotFoundException;
use Propel\Runtime\Map\Exception\RelationNotFoundException;
use function array_find;
use function array_key_exists;
use function array_keys;
use function assert;
use function implode;
use function sprintf;
use function substr;
use function trigger_deprecation;

/**
 * TableMap is used to model a table in a database.
 *
 * @method static array populateObject(array $row, int $offset = 0, string $indexType = \Propel\Runtime\Map\TableMap::TYPE_NUM)
 * @method static string getOMClass(array $row, int $column, bool $withPrefix = true)
 * @method static string|null getPrimaryKeyHashFromRow(array $row, int $offset = 0, string $indexType = \Propel\Runtime\Map\TableMap::TYPE_NUM): ?string;getPrimaryKeyHashFromRow(array $row, int $offset = 0, string $indexType = TableMap::TYPE_NUM)
 * @method static void addSelectColumns(\Propel\Runtime\ActiveQuery\Criteria $criteria, ?string $alias = null)
 * @method static array<\Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\LocalColumnExpression> buildLocalTableColumnExpressions(\Propel\Runtime\ActiveQuery\Criteria $criteria, ?string $alias = null)
 * @method static void clearInstancePool()
 * @method static void clearRelatedInstancePool()
 * @method static self getTableMap()
 */
class TableMap
{
    /**
     * phpname type
     * e.g. 'AuthorId'
     *
     * @var string
     */
    public const TYPE_PHPNAME = 'phpName';

    /**
     * camelCase type
     * e.g. 'authorId'
     *
     * @var string
     */
    public const TYPE_CAMELNAME = 'camelName';

    /**
     * column (tableMap) name type
     * e.g. 'book.AUTHOR_ID'
     *
     * @var string
     */
    public const TYPE_COLNAME = 'colName';

    /**
     * column fieldname type
     * e.g. 'author_id'
     *
     * @var string
     */
    public const TYPE_FIELDNAME = 'fieldName';

    /**
     * num type
     * simply the numerical array index, e.g. 4
     *
     * @var string
     */
    public const TYPE_NUM = 'num';

    /**
     * @var class-string
     */
    public const DEFAULT_OBJECT_COLLECTION = ObjectCollection::class;

    /**
     * @var array<\Propel\Runtime\Map\ColumnMap>
     */
    protected array $columns = [];

    /**
     * Columns in the table, using table phpName as key
     *
     * @var array<\Propel\Runtime\Map\ColumnMap>
     */
    protected array $columnsByPhpName = [];

    /**
     * @var array<string>
     */
    protected $normalizedColumnNameMap = [];

    protected DatabaseMap|null $dbMap = null;

    protected string|null $tableName = null;

    protected string|null $phpName = null;

    /**
     * @var class-string<\Propel\Runtime\ActiveRecord\ActiveRecordInterface>
     */
    protected string|null $modeClassname = null;

    protected string|null $package = null;

    protected IdMethod $idMethod;

    protected bool $isSingleTableInheritance = false;

    /**
     * Whether the table is a Many to Many table
     */
    protected bool $isCrossRef = false;

    /**
     * @var array<\Propel\Runtime\Map\ColumnMap>
     */
    protected array $primaryKeys = [];

    /**
     * @var array<\Propel\Runtime\Map\ColumnMap>
     */
    protected array $foreignKeys = [];

    /**
     * @var array<\Propel\Runtime\Map\RelationMap>
     */
    protected array $relations = [];

    /**
     *  Relations are lazy loaded. This property tells if the relations are loaded or not
     */
    protected bool $relationsBuilt = false;

    protected string|null $idSequenceName = null;

    protected bool $identifierQuoting = false;

    /**
     * @param string|null $name
     * @param \Propel\Runtime\Map\DatabaseMap|null $dbMap
     */
    public function __construct(?string $name = null, ?DatabaseMap $dbMap = null)
    {
        if ($name !== null) {
            $this->setName($name);
        }

        if ($dbMap !== null) {
            $this->setDatabaseMap($dbMap);
        }

        $this->idMethod = IdMethod::NO_ID_METHOD;

        $this->initialize();
    }

    /**
     * Initialize the TableMap to build columns, relations, etc
     * This method should be overridden by descendants
     *
     * @return void
     */
    public function initialize(): void
    {
    }

    /**
     * @param \Propel\Runtime\Map\DatabaseMap $dbMap
     *
     * @return void
     */
    public function setDatabaseMap(DatabaseMap $dbMap): void
    {
        $this->dbMap = $dbMap;
    }

    /**
     * @return \Propel\Runtime\Map\DatabaseMap
     */
    public function getDatabaseMap(): DatabaseMap
    {
        assert($this->dbMap !== null);

        return $this->dbMap;
    }

    /**
     * @param string|null $name
     *
     * @return void
     */
    public function setName(?string $name): void
    {
        $this->tableName = $name;
    }

    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->tableName;
    }

    /**
     * @param string $phpName
     *
     * @return void
     */
    public function setPhpName(string $phpName): void
    {
        $this->phpName = $phpName;
    }

    /**
     * @return string|null
     */
    public function getPhpName(): ?string
    {
        return $this->phpName;
    }

    /**
     * @throws \Propel\Runtime\Exception\LogicException
     *
     * @return string
     */
    public function getPhpNameOrFail(): string
    {
        $phpName = $this->getPhpName();

        if ($phpName === null) {
            throw new LogicException('PHP name is not defined.');
        }

        return $phpName;
    }

    /**
     * Set the model ClassName of the Table. Could be useful for calling
     * tableMap and Object methods dynamically.
     *
     * @psalm-param class-string<\Propel\Runtime\ActiveRecord\ActiveRecordInterface> $classname
     *
     * @param string $classname
     *
     * @return void
     */
    public function setModelClassName(string $classname): void
    {
        $this->modeClassname = $classname;
    }

    /**
     * @psalm-return class-string<\Propel\Runtime\ActiveRecord\ActiveRecordInterface>
     *
     * @return string|null
     */
    public function getModelClassName(): ?string
    {
        return $this->modeClassname;
    }

    /**
     * @deprecated Use aptly named {@see static::getModelClassName()}
     *
     * @return string|null
     */
    public function getClassName(): ?string
    {
        return $this->modeClassname;
    }

    /**
     * Get the ClassName of the Propel Class belonging to this table.
     *
     * @throws \Propel\Runtime\Exception\LogicException
     *
     * @return string
     */
    public function getClassNameOrFail(): string
    {
        $className = $this->getModelClassName();

        if ($className === null) {
            throw new LogicException('Class name is not defined.');
        }

        return $className;
    }

    /**
     * @return class-string
     */
    public function getCollectionClassName(): string
    {
        return static::DEFAULT_OBJECT_COLLECTION;
    }

    /**
     * @param string $package
     *
     * @return void
     */
    public function setPackage(string $package): void
    {
        $this->package = $package;
    }

    /**
     * @return string|null
     */
    public function getPackage(): ?string
    {
        return $this->package;
    }

    /**
     * @param \Propel\Generator\Model\IdMethod $idMethod
     *
     * @throws \Propel\Runtime\Exception\LogicException
     *
     * @return void
     */
    public function setIdMethod(IdMethod $idMethod): void
    {
        if ($idMethod === IdMethod::NATIVE) {
            throw new LogicException('Cannot use meta-type IdMethod::Native for specific database table.');
        }

        $this->idMethod = $idMethod;
    }

    /**
     * @return \Propel\Generator\Model\IdMethod
     */
    public function getIdMethod(): IdMethod
    {
        return $this->idMethod;
    }

    /**
     * @deprecated Change id method via {@see static::setIdMethod()}
     *
     * @param bool $newIdsAreProvidedByDb
     *
     * @return void
     */
    public function setUseIdGenerator(bool $newIdsAreProvidedByDb): void
    {
        trigger_deprecation('Perpl', '2.10.3', 'Change id method via TableMap::setIdMethod()');
    }

    /**
     * Check if autogenerated id of new rows has to be retrieved from DB.
     *
     * @return bool
     */
    public function isUsingAutoIncrementedIds(): bool
    {
        return $this->idMethod !== IdMethod::NO_ID_METHOD;
    }

    /**
     * @deprecated Use aptly named {@see static::databaseGeneratesId()}
     *
     * @return bool
     */
    public function isUseIdGenerator(): bool
    {
        return $this->isUsingAutoIncrementedIds();
    }

    /**
     * Set whether to this table uses single table inheritance
     *
     * @param bool $bit
     *
     * @return void
     */
    public function setSingleTableInheritance(bool $bit): void
    {
        $this->isSingleTableInheritance = $bit;
    }

    /**
     * Whether this table uses single table inheritance
     *
     * @return bool
     */
    public function isSingleTableInheritance(): bool
    {
        return $this->isSingleTableInheritance;
    }

    /**
     * Sets the name of the sequence used to generate a key
     *
     * @param string|null $idSequenceName
     *
     * @return void
     */
    public function setPrimaryKeyMethodInfo(string|null $idSequenceName): void
    {
        $this->idSequenceName = $idSequenceName;
    }

    /**
     * Get the name of the sequence used to generate a primary key
     *
     * @return string|null
     */
    public function getIdSequenceName(): string|null
    {
        return $this->idSequenceName;
    }

    /**
     * @param string $columnName
     *
     * @return string
     */
    protected function getNormalizedColumnName(string $columnName): string
    {
        return $this->normalizedColumnNameMap[$columnName] ?? ColumnMap::normalizeName($columnName);
    }

    /**
     * Add a column to the table.
     *
     * @param string $name
     * @param string $phpName
     * @param \Propel\Generator\Model\Datatype\ColumnType $type
     * @param bool $isNotNull
     * @param int|null $size
     * @param scalar|null $defaultValue
     * @param bool $isPk
     * @param string|null $fkTable
     * @param string|null $fkColumn
     *
     * @return \Propel\Runtime\Map\ColumnMap
     */
    public function addColumn(
        string $name,
        string $phpName,
        ColumnType $type,
        bool $isNotNull = false,
        ?int $size = null,
        $defaultValue = null,
        bool $isPk = false,
        ?string $fkTable = null,
        ?string $fkColumn = null
    ): ColumnMap {
        $col = new ColumnMap($name, $this, $phpName, $type);
        $col->setSize($size);
        $col->setNotNull($isNotNull);
        $col->setDefaultValue($defaultValue);

        if ($isPk) {
            $col->setPrimaryKey(true);
            $this->primaryKeys[$name] = $col;
        }

        if ($fkTable && $fkColumn) {
            $col->setForeignKey($fkTable, $fkColumn);
            $this->foreignKeys[$name] = $col;
        }

        $this->columns[$this->getNormalizedColumnName($name)] = $col;
        $this->columnsByPhpName[$phpName] = $col;

        return $col;
    }

    /**
     * Add a pre-created column to this table. It will replace any
     * existing column.
     *
     * @param \Propel\Runtime\Map\ColumnMap $columnMap
     *
     * @return void
     */
    public function addConfiguredColumn(ColumnMap $columnMap): void
    {
        $this->columns[$columnMap->getName()] = $columnMap;
    }

    /**
     * Does this table contain the specified column?
     *
     * @param \Propel\Runtime\Map\ColumnMap|string $name name of the column or ColumnMap instance
     * @param bool $normalize Normalize the column name (if column name not like FIRST_NAME)
     *
     * @return bool True if the table contains the column.
     */
    public function hasColumn(ColumnMap|string $name, bool $normalize = true): bool
    {
        if ($name instanceof ColumnMap) {
            $name = $name->getName();
        } elseif ($normalize) {
            $name = $this->getNormalizedColumnName($name);
        }

        return isset($this->columns[$name]);
    }

    /**
     * @param string $name
     * @param bool $normalize Normalize the column name (if column name not like FIRST_NAME)
     *
     * @throws \Propel\Runtime\Map\Exception\ColumnNotFoundException If the column is undefined
     *
     * @return \Propel\Runtime\Map\ColumnMap A ColumnMap.
     */
    public function getColumn(string $name, bool $normalize = true): ColumnMap
    {
        if ($normalize) {
            $name = $this->getNormalizedColumnName($name);
        }
        if (!$this->hasColumn($name, false)) {
            throw new ColumnNotFoundException(sprintf('Cannot fetch ColumnMap for undefined column: %s in table %s.', $name, $this->getName()));
        }

        return $this->columns[$name];
    }

    /**
     * @param string $phpName
     *
     * @return bool
     */
    public function hasColumnByPhpName(string $phpName): bool
    {
        return isset($this->columnsByPhpName[$phpName]);
    }

    /**
     * @param string $phpName
     *
     * @throws \Propel\Runtime\Map\Exception\ColumnNotFoundException If the column is undefined
     *
     * @return \Propel\Runtime\Map\ColumnMap
     */
    public function getColumnByPhpName(string $phpName): ColumnMap
    {
        if (!isset($this->columnsByPhpName[$phpName])) {
            throw new ColumnNotFoundException("Cannot fetch ColumnMap for undefined column phpName: $phpName");
        }

        return $this->columnsByPhpName[$phpName];
    }

    /**
     * @param string $name
     *
     * @return \Propel\Runtime\Map\ColumnMap|null
     */
    public function findColumnByName(string $name): ?ColumnMap
    {
        if (isset($this->columnsByPhpName[$name])) {
            return $this->getColumnByPhpName($name);
        }
        if ($this->hasColumn($name, false)) {
            return $this->getColumn($name, false);
        }
        if ($this->hasColumn($name, true)) {
            return $this->getColumn($name, true);
        }

        return null;
    }

    /**
     * Get all columns in this table.
     *
     * @return array<\Propel\Runtime\Map\ColumnMap>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * @param string $columnName
     * @param string $phpName
     * @param \Propel\Generator\Model\Datatype\ColumnType $type
     * @param bool $isNotNull
     * @param int|null $size
     * @param string|null $defaultValue
     *
     * @return \Propel\Runtime\Map\ColumnMap Newly added PrimaryKey column.
     */
    public function addPrimaryKey(
        string $columnName,
        string $phpName,
        ColumnType $type,
        bool $isNotNull = false,
        ?int $size = null,
        ?string $defaultValue = null
    ): ColumnMap {
        return $this->addColumn($columnName, $phpName, $type, $isNotNull, $size, $defaultValue, true, null, null);
    }

    /**
     * @param string $columnName
     * @param string $phpName
     * @param \Propel\Generator\Model\Datatype\ColumnType $type
     * @param string $fkTable
     * @param string $fkColumn
     * @param bool $isNotNull
     * @param int|null $size
     * @param scalar|null $defaultValue
     *
     * @return \Propel\Runtime\Map\ColumnMap Newly added ForeignKey column.
     */
    public function addForeignKey(
        string $columnName,
        string $phpName,
        ColumnType $type,
        string $fkTable,
        string $fkColumn,
        bool $isNotNull = false,
        ?int $size = null,
        $defaultValue = null
    ): ColumnMap {
        return $this->addColumn($columnName, $phpName, $type, $isNotNull, $size, $defaultValue, false, $fkTable, $fkColumn);
    }

    /**
     * @param string $columnName
     * @param string $columnPhpName
     * @param \Propel\Generator\Model\Datatype\ColumnType $propelType
     * @param string $fkTableName
     * @param string $fkColumnName
     * @param bool $isNotNull
     * @param int|null $size
     * @param string|null $defaultValue
     *
     * @return \Propel\Runtime\Map\ColumnMap
     */
    public function addForeignPrimaryKey(
        string $columnName,
        string $columnPhpName,
        ColumnType $propelType,
        string $fkTableName,
        string $fkColumnName,
        bool $isNotNull = false,
        ?int $size = null,
        ?string $defaultValue = null
    ): ColumnMap {
        return $this->addColumn($columnName, $columnPhpName, $propelType, $isNotNull, $size, $defaultValue, true, $fkTableName, $fkColumnName);
    }

    /**
     * @return bool
     */
    public function isCrossRef(): bool
    {
        return $this->isCrossRef;
    }

    /**
     * @param bool $isCrossRef
     *
     * @return void
     */
    public function setIsCrossRef(bool $isCrossRef): void
    {
        $this->isCrossRef = $isCrossRef;
    }

    /**
     * @return array<\Propel\Runtime\Map\ColumnMap>
     */
    public function getPrimaryKeys(): array
    {
        return $this->primaryKeys;
    }

    /**
     * @return array<\Propel\Runtime\Map\ColumnMap>
     */
    public function getForeignKeys(): array
    {
        return $this->foreignKeys;
    }

    /**
     * Build relations
     * Relations are lazy loaded for performance reasons
     * This method should be overridden by descendants
     *
     * @return void
     */
    public function buildRelations(): void
    {
    }

    /**
     * @param string $relationName
     * @param string $foreignTablePhpName
     * @param int $type The relation type (either RelationMap::MANY_TO_ONE, RelationMap::ONE_TO_MANY, or RelationMAp::ONE_TO_ONE)
     * @param array $joinConditionMapping Arrays in array defining a normalize join condition [[':foreign_id', ':id', '='], [':foreign_type', 'value', '=']]
     * @param string|null $onDelete SQL behavior upon deletion ('SET NULL', 'CASCADE', ...)
     * @param string|null $onUpdate SQL behavior upon update ('SET NULL', 'CASCADE', ...)
     * @param string|null $pluralName Optional plural name for *_TO_MANY relationships
     * @param bool $polymorphic Optional plural name for *_TO_MANY relationships
     *
     * @throws \Propel\Runtime\Exception\LogicException
     *
     * @return \Propel\Runtime\Map\RelationMap
     */
    public function addRelation(
        string $relationName,
        string $foreignTablePhpName,
        int $type,
        array $joinConditionMapping = [],
        ?string $onDelete = null,
        ?string $onUpdate = null,
        ?string $pluralName = null,
        bool $polymorphic = false
    ): RelationMap {
        // determine tables
        if ($type === RelationMap::MANY_TO_ONE) {
            $localTable = $this;
            $foreignTable = $this->dbMap->getTableByPhpName($foreignTablePhpName);
        } else {
            $localTable = $this->dbMap->getTableByPhpName($foreignTablePhpName);
            $foreignTable = $this;
        }

        // note: using phpName for the second table allows the use of DatabaseMap::getTableByPhpName()
        // and this method autoloads the TableMap if the table isn't loaded yet
        $relation = new RelationMap($relationName, $localTable, $foreignTable);
        $relation->setType($type);
        $relation->setOnUpdate($onUpdate);
        $relation->setOnDelete($onDelete);
        $relation->setPolymorphic($polymorphic);

        if ($pluralName !== null) {
            $relation->setPluralName($pluralName);
        }

        // set columns
        foreach ($joinConditionMapping as $map) {
            [$local, $foreign] = $map;
            if ($local[0] !== ':') {
                throw new LogicException('first postion in join condition mapping has to be column (start with ":")');
            }
            $relation->addColumnMapping(
                $relation->getLocalTable()->getColumn(substr($local, 1)),
                $this->getColumnOrValue($foreign, $relation->getForeignTable()),
            );
        }
        $this->relations[$relationName] = $relation;

        return $relation;
    }

    /**
     * @param string $value values with starting ':' mean a column name, otherwise a regular value.
     * @param \Propel\Runtime\Map\TableMap $table
     *
     * @return \Propel\Runtime\Map\ColumnMap|string
     */
    protected function getColumnOrValue(string $value, TableMap $table)
    {
        if (substr($value, 0, 1) === ':') {
            return $table->getColumn(substr($value, 1));
        } else {
            return $value;
        }
    }

    /**
     * Gets a RelationMap of the table by relation name
     * This method will build the relations if they are not built yet
     *
     * @param string $relationName
     *
     * @return bool
     */
    public function hasRelation(string $relationName): bool
    {
        return array_key_exists($relationName, $this->getRelations());
    }

    /**
     * Gets a RelationMap of the table by relation name
     * This method will build the relations if they are not built yet
     *
     * @param string $relationName
     *
     * @throws \Propel\Runtime\Map\Exception\RelationNotFoundException When called on an inexistent relation
     *
     * @return \Propel\Runtime\Map\RelationMap
     */
    public function getRelation(string $relationName): RelationMap
    {
        if (!array_key_exists($relationName, $this->getRelations())) {
            $availableRelationNames = array_keys($this->getRelations());
            $relationNamesCsv = implode(', ', $availableRelationNames) ?: '[none]';

            throw new RelationNotFoundException(sprintf("Unknown relation `$relationName` on table `{$this->tableName}`. Available relations: $relationNamesCsv"));
        }

        return $this->relations[$relationName];
    }

    /**
     * Gets the RelationMap objects of the table
     * This method will build the relations if they are not built yet
     *
     * @return array<\Propel\Runtime\Map\RelationMap> list of RelationMap objects
     */
    public function getRelations(): array
    {
        if (!$this->relationsBuilt) {
            $this->buildRelations();
            $this->relationsBuilt = true;
        }

        return $this->relations;
    }

    /**
     * Gets the list of behaviors registered for this table
     *
     * @return array
     */
    public function getBehaviors(): array
    {
        return [];
    }

    /**
     * @return bool
     */
    public function hasPrimaryStringColumn(): bool
    {
        return $this->getPrimaryStringColumn() !== null;
    }

    /**
     * @return \Propel\Runtime\Map\ColumnMap|null
     */
    public function getPrimaryStringColumn(): ?ColumnMap
    {
        return array_find($this->getColumns(), fn ($c) => $c->isPrimaryString());
    }

    /**
     * @param string $classname
     * @param string $type
     *
     * @return list<string>|list<int>
     */
    public static function getFieldnamesForClass(string $classname, string $type = self::TYPE_PHPNAME)
    {
        return ($classname::TABLE_MAP)::getFieldnames($type);
    }

    /**
     * @param string $classname
     * @param string $fieldname
     * @param string $fromType
     * @param string $toType
     *
     * @return string|int
     */
    public static function translateFieldnameForClass(string $classname, string $fieldname, string $fromType, string $toType)
    {
        return ($classname::TABLE_MAP)::translateFieldname($fieldname, $fromType, $toType);
    }

    /**
     * @return bool
     */
    public function isIdentifierQuotingEnabled(): bool
    {
        return $this->identifierQuoting;
    }

    /**
     * @param bool $identifierQuoting
     *
     * @return void
     */
    public function setIdentifierQuoting(bool $identifierQuoting): void
    {
        $this->identifierQuoting = $identifierQuoting;
    }

    /**
     * Check if an identifier matches any of the names of this TableMap
     *
     * @param string $identifier
     *
     * @return bool
     */
    public function isIdentifiedBy(string $identifier): bool
    {
        return $this->tableName === $identifier
            || $this->phpName === $identifier
            || $this->modeClassname === ($identifier[0] === '\\' ? $identifier : '\\' . $identifier);
    }
}
