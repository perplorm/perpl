<?php

declare(strict_types = 1);

namespace Propel\Generator\Platform;

use BadMethodCallException;
use Propel\Common\Util\SetColumnConverter;
use Propel\Generator\Config\AbstractGeneratorConfig;
use Propel\Generator\Exception\EngineException;
use Propel\Generator\Model\Column;
use Propel\Generator\Model\Database;
use Propel\Generator\Model\Datatype\ColumnType;
use Propel\Generator\Model\Diff\ColumnDiff;
use Propel\Generator\Model\Diff\DatabaseDiff;
use Propel\Generator\Model\Diff\TableDiff;
use Propel\Generator\Model\ForeignKey;
use Propel\Generator\Model\IdMethod;
use Propel\Generator\Model\Index;
use Propel\Generator\Model\MappingModel;
use Propel\Generator\Model\Table;
use Propel\Generator\Model\TypeMapping;
use Propel\Generator\Model\Unique;
use Propel\Generator\Platform\Util\AlterTableStatementMerger;
use Propel\Runtime\Connection\ConnectionInterface;
use ReflectionClass;
use function array_column;
use function array_filter;
use function array_find;
use function array_map;
use function array_search;
use function assert;
use function count;
use function filter_var;
use function implode;
use function in_array;
use function is_string;
use function preg_replace;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strpos;
use function strtolower;
use function strtr;
use function substr;
use function trigger_deprecation;
use const FILTER_VALIDATE_BOOL;

/**
 * Default implementation for the PlatformInterface interface.
 */
class DefaultPlatform implements PlatformInterface
{
    /**
     * @var string
     */
    protected const PK_DEFAULT_SUFFIX = '_pk';

    protected ConnectionInterface|null $con = null;

    protected bool $identifierQuoting = true;

    protected bool $defaultToNativeEnumeratedColumnTypes = false;

    protected bool $hasNativeEnumType = false;

    /**
     * @var array<string, \Propel\Generator\Model\TypeMapping>
     */
    protected array $columnTypeMappingCache = [];

    /**
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con Optional database connection to use in this platform.
     */
    public function __construct(?ConnectionInterface $con = null)
    {
        if ($con !== null) {
            $this->setConnection($con);
        }

        $this->initialize();
    }

    /**
     * @template T
     *
     * @param callable(T): string $fun
     * @param array<T> $array
     * @param string $separator
     *
     * @return string
     */
    final protected function mapConcat(callable $fun, array $array, string $separator = ''): string
    {
        $lines = array_map($fun, $array);

        return implode($separator, $lines);
    }

    /**
     * @param string $type
     *
     * @return string
     */
    public function getObjectBuilderClass(string $type): string
    {
        return '';
    }

    /**
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con Database connection to use in this platform.
     *
     * @return void
     */
    #[\Override]
    public function setConnection(?ConnectionInterface $con = null): void
    {
        $this->con = $con;
    }

    /**
     * @return \Propel\Runtime\Connection\ConnectionInterface|null
     */
    #[\Override]
    public function getConnection(): ?ConnectionInterface
    {
        return $this->con;
    }

    /**
     * @return bool
     */
    #[\Override]
    public function isIdentifierQuotingEnabled(): bool
    {
        return $this->identifierQuoting;
    }

    /**
     * @param bool $enabled
     *
     * @return void
     */
    #[\Override]
    public function setIdentifierQuoting(bool $enabled): void
    {
        $this->identifierQuoting = $enabled;
    }

    /**
     * Sets the GeneratorConfigInterface to use in the parsing.
     *
     * @param \Propel\Generator\Config\AbstractGeneratorConfig $generatorConfig
     *
     * @return void
     */
    #[\Override]
    public function setGeneratorConfig(AbstractGeneratorConfig $generatorConfig): void
    {
        $this->columnTypeMappingCache = [];
        $this->defaultToNativeEnumeratedColumnTypes = (bool)($generatorConfig->getConfigProperty('generator.defaultToNativeEnumeratedColumnTypes') ?? false);
    }

    /**
     * @return void
     */
    protected function initialize(): void
    {
    }

    /**
     * @param \Propel\Generator\Model\Datatype\ColumnType $type
     *
     * @return \Propel\Generator\Model\TypeMapping
     */
    #[\Override]
    final public function getColumnTypeMapping(ColumnType $type): TypeMapping
    {
        $key = $type->name;
        if (empty($this->columnTypeMappingCache[$key])) {
            $this->columnTypeMappingCache[$key] = $this->resolveColumnTypeMapping($type);
        }

        return clone $this->columnTypeMappingCache[$key];
    }

    /**
     * @param \Propel\Generator\Model\Datatype\ColumnType $type
     *
     * @return \Propel\Generator\Model\TypeMapping
     */
    protected function resolveColumnTypeMapping(ColumnType $type): TypeMapping
    {
        $resolvedType = $this->resolveColumnTypeAlias($type);
        $sqlType = $this->resolveSqlType($resolvedType);
        $size = $this->resolveTypeSize($resolvedType);

        return new TypeMapping($resolvedType, $sqlType, $size);
    }

    /**
     * @param \Propel\Generator\Model\Datatype\ColumnType $type
     *
     * @return \Propel\Generator\Model\Datatype\ColumnType
     */
    protected function resolveColumnTypeAlias(ColumnType $type): ColumnType
    {
        return match ($type) {
            ColumnType::ENUM => $this->resolveColumnTypeAlias($this->defaultToNativeEnumeratedColumnTypes ? ColumnType::ENUM_NATIVE : ColumnType::ENUM_BINARY),
            ColumnType::SET => $this->resolveColumnTypeAlias($this->defaultToNativeEnumeratedColumnTypes ? ColumnType::SET_NATIVE : ColumnType::SET_BINARY),
            ColumnType::BU_DATE => ColumnType::DATE,
            ColumnType::BU_TIMESTAMP => ColumnType::TIMESTAMP,
            ColumnType::SET_NATIVE => $this->hasNativeEnumType ? $type : ColumnType::SET_BINARY,
            ColumnType::ENUM_NATIVE => $this->hasNativeEnumType ? $type : ColumnType::ENUM_BINARY,

            default => $type
        };
    }

    /**
     * @param \Propel\Generator\Model\Datatype\ColumnType $type
     *
     * @return string|null
     */
    protected function resolveSqlType(ColumnType $type): string|null
    {
        return match ($type) {
            ColumnType::BOOLEAN,
            ColumnType::SET_BINARY
            => 'INTEGER',
            ColumnType::ENUM_BINARY
            => 'TINYINT',
            ColumnType::SET_NATIVE,
            ColumnType::ENUM_NATIVE
            => 'VARCHAR',
            default => null
        };
    }

    /**
     * @param \Propel\Generator\Model\Datatype\ColumnType $type
     *
     * @return int|null
     */
    protected function resolveTypeSize(ColumnType $type): int|null
    {
        return match ($type) {
            default => null
        };
    }

    /**
     * Returns the short name of the database type that this platform represents.
     * For example MysqlPlatform->getDatabaseType() returns 'mysql'.
     *
     * @return string
     */
    #[\Override]
    public function getDatabaseType(): string
    {
        $reflectionClass = new ReflectionClass($this);
        $platformShortName = $reflectionClass->getShortName();
        $pos = strpos($platformShortName, 'Platform') ?: null;

        return strtolower(substr($platformShortName, 0, $pos));
    }

    /**
     * Returns the max column length supported by the db.
     *
     * @return int
     */
    #[\Override]
    public function getMaxColumnNameLength(): int
    {
        return 64;
    }

    /**
     * @phpstan-return non-empty-string
     *
     * @return string
     */
    #[\Override]
    public function getSchemaDelimiter(): string
    {
        return '.';
    }

    /**
     * @return \Propel\Generator\Model\IdMethod
     */
    #[\Override]
    public function getNativeIdMethod(): IdMethod
    {
        return IdMethod::NO_ID_METHOD;
    }

    /**
     * @deprecated Use {@see static::getColumnTypeMapping()}
     *
     * @param \Propel\Generator\Model\Datatype\ColumnType $propelType
     *
     * @return \Propel\Generator\Model\TypeMapping
     */
    public function getDomainForType(ColumnType $propelType): TypeMapping
    {
        return $this->getColumnTypeMapping($propelType);
    }

    /**
     * Returns the NOT NULL string for the configured RDBMS.
     *
     * @param bool $notNull
     *
     * @return string
     */
    #[\Override]
    public function getNullString(bool $notNull): string
    {
        return $notNull ? 'NOT NULL' : '';
    }

    /**
     * Build column DDL fragment for id method (i.e. 'AUTO_INCREMENT' for native id method in MySQL)
     *
     * @param \Propel\Generator\Model\IdMethod $idMethod
     * @param \Propel\Generator\Model\Column $column
     *
     * @return string|null Null means id method is not supported (might trigger Exception),
     *                     empty string means column DDL is not affected by id method.
     */
    #[\Override]
    final public function buildAutoIncrementColumnDdl(IdMethod $idMethod, Column $column): ?string
    {
        if ($idMethod === IdMethod::NATIVE) {
            $idMethod = $this->getNativeIdMethod();
        }

        return $this->resolveAutoIncrementColumnDdl($idMethod, $column);
    }

    /**
     * @param \Propel\Generator\Model\IdMethod $idMethod
     * @param \Propel\Generator\Model\Column $column
     *
     * @return string|null
     */
    protected function resolveAutoIncrementColumnDdl(IdMethod $idMethod, Column $column): ?string
    {
        return '';
    }

    /**
     * @deprecated Use {@see Table::resolveDefaultIdSequenceName()}
     *
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string|null
     */
    public function getSequenceName(Table $table): ?string
    {
        return $table->resolveDefaultIdSequenceName();
    }

    /**
     * Build platform-specific name for id column sequence.
     *
     * Note: Typically called via {@see Table::resolveDefaultIdSequenceName()} to handle
     *       table-specific adjustments.
     *
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string|null
     */
    #[\Override]
    public function buildDefaultTableIdSequenceName(Table $table): ?string
    {
        return $this->limitIdentifierName($table->getName(), '_SEQ');
    }

    /**
     * @param string $identifier
     * @param string|null $suffix
     *
     * @return string
     */
    #[\Override]
    public function limitIdentifierName(string $identifier, string|null $suffix = null): string
    {
        $suffix ??= '';
        $defaultName = "{$identifier}{$suffix}";
        $maxIdentifierLength = $this->getMaxColumnNameLength();
        if (strlen($defaultName) <= $maxIdentifierLength) {
            return $defaultName;
        }

        /** @var array<string, string> $longNamesMap*/
        static $longNamesMap = [];
        if (!isset($longNamesMap[$defaultName])) {
            $counter = 1 + count($longNamesMap) + 1; // FIXME: Creates different sequence names depending on order/number of sequences (should be number of collisions)
            $suffix = "~$counter{$suffix}";
            $shortenedLength = $maxIdentifierLength - strlen($suffix);

            $longNamesMap[$defaultName] = substr($identifier, 0, $shortenedLength) . $suffix;
        }

        return $longNamesMap[$defaultName];
    }

    /**
     * Returns the DDL SQL to add the tables of a database
     * together with index and foreign keys
     *
     * @param \Propel\Generator\Model\Database $database
     *
     * @return string
     */
    public function buildAddTablesDdl(Database $database): string
    {
        $ret = $this->buildBeginDdl();
        foreach ($database->getTablesForSql() as $table) {
            $this->normalizeTable($table);
        }
        foreach ($database->getTablesForSql() as $table) {
            $ret .= $this->buildCommentBlockDdl($table->getName());
            $ret .= $this->buildDropTableDdl($table);
            $ret .= $this->buildAddTableDdl($table);
            $ret .= $this->buildAddIndicesDdl($table);
            $ret .= $this->buildAddForeignKeysDdl($table);
        }
        $ret .= $this->buildEndDdl();

        return $ret;
    }

    /**
     * Gets the requests to execute at the beginning of a DDL file
     *
     * @return string
     */
    public function buildBeginDdl(): string
    {
        return '';
    }

    /**
     * Gets the requests to execute at the end of a DDL file
     *
     * @return string
     */
    public function buildEndDdl(): string
    {
        return '';
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    public function buildDropTableDdl(Table $table): string
    {
        $tableName = $this->quoteIdentifier($table->getName());

        return "\nDROP TABLE IF EXISTS $tableName;\n";
    }

    /**
     * Builds the DDL SQL to add a table
     * without index and foreign keys
     *
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    #[\Override]
    public function buildAddTableDdl(Table $table): string
    {
        $tableDescription = $table->hasDescription()
            ? $this->buildCommentLineDdl($table->getDescription())
            : '';

        $lines = [];

        foreach ($table->getColumns() as $column) {
            $lines[] = $this->buildColumnDdl($column);
        }

        if ($table->hasPrimaryKey()) {
            $lines[] = $this->buildPrimaryKeyDdl($table);
        }

        foreach ($table->getUnices() as $unique) {
            $lines[] = $this->buildUniqueDdl($unique);
        }

        $tableName = $this->quoteIdentifier($table->getName());
        $columnDeclarations = implode(",\n    ", $lines);

        return "
{$tableDescription}CREATE TABLE $tableName
(
    $columnDeclarations
);\n";
    }

    /**
     * @param \Propel\Generator\Model\Column $col
     *
     * @return string
     */
    #[\Override]
    public function buildColumnDdl(Column $col): string
    {
        $ddl = array_filter([
            $this->quoteIdentifier($col->getName()),
            $this->buildColumnTypeDeclaration($col),
            $this->buildColumnDefaultValueDdl($col),
            $this->getNullString($col->isNotNull()),
            $col->buildAutoIncrementString(),
        ]);

        return implode(' ', $ddl);
    }

    /**
     * @param \Propel\Generator\Model\Column $column
     *
     * @return string
     */
    protected function buildColumnTypeDeclaration(Column $column): string
    {
        $sqlType = $column->resolveSqlTypeName();
        if ($this->hasSize($sqlType) && $column->isDefaultSqlType($this)) {
            $sqlType .= $column->getSizeDefinition();
        }

        return $sqlType;
    }

    /**
     * @param \Propel\Generator\Model\Column $column
     *
     * @return string
     */
    #[\Override]
    public function buildColumnDefaultValueDdl(Column $column): string
    {
        $defaultValueExpression = $this->buildDefaultValueExpression($column);

        return ($defaultValueExpression === null) ? '' : "DEFAULT $defaultValueExpression";
    }

    /**
     * @param \Propel\Generator\Model\Column $col
     *
     * @return string|null
     */
    protected function buildDefaultValueExpression(Column $col): ?string
    {
        $defaultValueObject = $col->getDefaultValue();
        if ($defaultValueObject === null) {
            return null;
        }
        $value = $defaultValueObject->getValue();

        if ($defaultValueObject->isExpression()) {
            return $value;
        }

        if ($col->isTextType()) {
            return $this->quote($value);
        }

        if (in_array($col->getColumnType(), [ColumnType::BOOLEAN, ColumnType::BOOLEAN_EMU])) {
            return $this->getBooleanString($value);
        }

        if (($col->isBinaryEnumType())) {
            return (string)array_search($value, $col->getValueSet());
        }

        if ($col->isBinarySetType()) {
            $items = SetColumnConverter::itemsCsvToArray($value);

            return (string)SetColumnConverter::convertToBitmask($items, $col->getValueSet());
        }

        if ($col->getColumnType() === ColumnType::SET_NATIVE) {
            return str_contains($value, ',')
                ? null // MySQL does not allow multiple values as default
                : $this->quote((string)$value);
        }

        if ($col->isPhpArrayType()) {
            return $this->getPhpArrayString($value);
        }

        return $value;
    }

    /**
     * Creates a delimiter-delimited string list of column names, quoted using quoteIdentifier().
     *
     * @example
     * <code>
     * echo $platform->buildColumnListDdl(array('foo', 'bar');
     * // '"foo","bar"'
     * </code>
     *
     * @param array<\Propel\Generator\Model\Column> $columns
     * @param string $delimiter The delimiter to use in separating the column names.
     *
     * @return string
     */
    #[\Override]
    public function buildColumnListDdl(array $columns, string $delimiter = ','): string
    {
        $list = [];
        foreach ($columns as $column) {
            $columnName = $column->getName();
            $list[] = $this->quoteIdentifier($columnName);
        }

        return implode($delimiter, $list);
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    public function getPrimaryKeyName(Table $table): string
    {
        return $table->getCommonName() . static::PK_DEFAULT_SUFFIX;
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    #[\Override]
    public function buildPrimaryKeyDdl(Table $table): string
    {
        if (!$table->hasPrimaryKey()) {
            return '';
        }
        $pkColumnNames = $this->buildColumnListDdl($table->getPrimaryKey());

        return 'PRIMARY KEY (' . $pkColumnNames . ')';
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    public function buildDropPrimaryKeyDdl(Table $table): string
    {
        if (!$table->hasPrimaryKey()) {
            return '';
        }
        $tableName = $this->quoteIdentifier($table->getName());
        $pkName = $this->quoteIdentifier($this->getPrimaryKeyName($table));

        return "\nALTER TABLE $tableName DROP CONSTRAINT $pkName;\n";
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    public function buildAddPrimaryKeyDdl(Table $table): string
    {
        if (!$table->hasPrimaryKey()) {
            return '';
        }
        $tableName = $this->quoteIdentifier($table->getName());
        $pkDdl = $this->buildPrimaryKeyDdl($table);

        return "\nALTER TABLE $tableName ADD $pkDdl;\n";
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    public function buildAddIndicesDdl(Table $table): string
    {
        return $this->mapConcat([$this, 'buildAddIndexDdl'], $table->getIndices());
    }

    /**
     * @param \Propel\Generator\Model\Index $index
     *
     * @return string
     */
    public function buildAddIndexDdl(Index $index): string
    {
        $unique = $index->isUnique() ? 'UNIQUE ' : '';
        $indexName = $this->quoteIdentifier($index->getName());
        $tableName = $this->quoteIdentifier($index->getTable()->getName());
        $columnList = $this->buildColumnListDdl($index->getColumnObjects());

        return "\nCREATE {$unique}INDEX $indexName ON $tableName ($columnList);\n";
    }

    /**
     * @param \Propel\Generator\Model\Index $index
     *
     * @return string
     */
    public function buildDropIndexDdl(Index $index): string
    {
        $indexName = $this->quoteIdentifier($index->getFQName());

        return "\nDROP INDEX $indexName;\n";
    }

    /**
     * @param \Propel\Generator\Model\Index $index
     *
     * @return string
     */
    public function buildIndexDdl(Index $index): string
    {
        $unique = $index->isUnique() ? 'UNIQUE ' : '';
        $indexName = $this->quoteIdentifier($index->getName());
        $columnList = $this->buildColumnListDdl($index->getColumnObjects());

        return "{$unique}INDEX $indexName ($columnList)";
    }

    /**
     * @param \Propel\Generator\Model\Unique $unique
     *
     * @return string
     */
    public function buildUniqueDdl(Unique $unique): string
    {
        $columnList = $this->buildColumnListDdl($unique->getColumnObjects());

        return "UNIQUE ($columnList)";
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    public function buildAddForeignKeysDdl(Table $table): string
    {
        return $this->mapConcat([$this, 'buildAddForeignKeyDdl'], $table->getForeignKeys());
    }

    /**
     * @param \Propel\Generator\Model\ForeignKey $fk
     *
     * @return string
     */
    public function buildAddForeignKeyDdl(ForeignKey $fk): string
    {
        if ($fk->isSkipSql() || $fk->isPolymorphic()) {
            return '';
        }
        $tableName = $this->quoteIdentifier($fk->getTable()->getName());
        $fkDdl = $this->buildForeignKeyDdl($fk);

        return "\nALTER TABLE $tableName ADD $fkDdl;\n";
    }

    /**
     * @param \Propel\Generator\Model\ForeignKey $fk
     *
     * @return string
     */
    public function buildDropForeignKeyDdl(ForeignKey $fk): string
    {
        if ($fk->isSkipSql() || $fk->isPolymorphic()) {
            return '';
        }
        $tableName = $this->quoteIdentifier($fk->getTable()->getName());
        $fkName = $this->quoteIdentifier($fk->getName());

        return "\nALTER TABLE $tableName DROP CONSTRAINT $fkName;\n";
    }

    /**
     * @param \Propel\Generator\Model\ForeignKey $fk
     *
     * @return string
     */
    public function buildForeignKeyDdl(ForeignKey $fk): string
    {
        if ($fk->isSkipSql() || $fk->isPolymorphic()) {
            return '';
        }

        $fkName = $this->quoteIdentifier($fk->getName());
        $localColumnList = $this->buildColumnListDdl($fk->getLocalColumnObjects());
        $foreignTableName = $this->quoteIdentifier($fk->getForeignTableName());
        $foreignColumnList = $this->buildColumnListDdl($fk->getForeignColumnObjects());
        $onUpdate = $fk->hasOnUpdate() ? "\n    ON UPDATE " . $fk->getOnUpdate() : '';
        $onDelete = $fk->hasOnDelete() ? "\n    ON DELETE " . $fk->getOnDelete() : '';

        return "CONSTRAINT $fkName
    FOREIGN KEY ($localColumnList)
    REFERENCES $foreignTableName ($foreignColumnList){$onUpdate}{$onDelete}";
    }

    /**
     * @param string $comment
     *
     * @return string
     */
    public function buildCommentLineDdl(string $comment): string
    {
        return "-- $comment\n";
    }

    /**
     * @param string $comment
     *
     * @return string
     */
    public function buildCommentBlockDdl(string $comment): string
    {
        return "
-- ---------------------------------------------------------------------
-- $comment
-- ---------------------------------------------------------------------
";
    }

    /**
     * @param \Propel\Generator\Model\Diff\DatabaseDiff $databaseDiff
     *
     * @return string
     */
    public function buildModifyDatabaseDdl(DatabaseDiff $databaseDiff): string
    {
        $ret = '';
        foreach ($databaseDiff->getRemovedTables() as $table) {
            $ret .= $this->buildDropTableDdl($table);
        }

        foreach ($databaseDiff->getRenamedTables() as $fromTableName => $toTableName) {
            $ret .= $this->buildRenameTableDdl($fromTableName, $toTableName);
        }

        foreach ($databaseDiff->getAddedTables() as $table) {
            $ret .= $this->buildAddTableDdl($table);
            $ret .= $this->buildAddIndicesDdl($table);
        }

        foreach ($databaseDiff->getModifiedTables() as $tableDiff) {
            $ret .= $this->buildModifyTableDdl($tableDiff);
        }

        foreach ($databaseDiff->getAddedTables() as $table) {
            $ret .= $this->buildAddForeignKeysDdl($table);
        }

        return $ret
            ? $this->buildBeginDdl() . $ret . $this->buildEndDdl()
            : '';
    }

    /**
     * @param string $currentTableName
     * @param string $newTableName
     *
     * @return string
     */
    public function buildRenameTableDdl(string $currentTableName, string $newTableName): string
    {
        $currentTableName = $this->quoteIdentifier($currentTableName);
        $newTableName = $this->quoteIdentifier($newTableName);

        return "\nALTER TABLE $currentTableName RENAME TO $newTableName;\n";
    }

    /**
     * @param \Propel\Generator\Model\Diff\TableDiff $tableDiff
     *
     * @return string
     */
    public function buildModifyTableDdl(TableDiff $tableDiff): string
    {
        $ret = '';

        $toTable = $tableDiff->getToTable();

        // drop indices, foreign keys
        $ret .= $this->mapConcat([$this, 'buildDropForeignKeyDdl'], $tableDiff->getRemovedFks());
        $fromFks = array_column($tableDiff->getModifiedFks(), 0);
        $ret .= $this->mapConcat([$this, 'buildDropForeignKeyDdl'], $fromFks);

        $ret .= $this->mapConcat([$this, 'buildDropIndexDdl'], $tableDiff->getRemovedIndices());
        $fromIndexes = array_column($tableDiff->getModifiedIndices(), 0);
        $ret .= $this->mapConcat([$this, 'buildDropIndexDdl'], $fromIndexes);

        $columnChangeString = '';

        // alter table structure
        if ($tableDiff->hasModifiedPk()) {
            $columnChangeString .= $this->buildDropPrimaryKeyDdl($tableDiff->getFromTable());
        }
        foreach ($tableDiff->getRenamedColumns() as $columnRenaming) {
            $columnChangeString .= $this->buildRenameColumnDdl(...$columnRenaming);
        }

        $modifiedColumns = $tableDiff->getModifiedColumns();

        if ($modifiedColumns) {
            $columnChangeString .= $this->buildModifyColumnsDdl($modifiedColumns);
        }

        $addedColumns = $tableDiff->getAddedColumns();

        if ($addedColumns) {
            $columnChangeString .= $this->buildAddColumnsDdl($addedColumns);
        }
        $columnChangeString .= $this->mapConcat([$this, 'buildRemoveColumnDdl'], $tableDiff->getRemovedColumns());

        // add new indices and foreign keys
        if ($tableDiff->hasModifiedPk()) {
            $columnChangeString .= $this->buildAddPrimaryKeyDdl($tableDiff->getToTable());
        }

        $ret .= AlterTableStatementMerger::merge($toTable, $columnChangeString);

        // create indices, foreign keys
        $toIndex = array_column($tableDiff->getModifiedIndices(), 1);
        $ret .= $this->mapConcat([$this, 'buildAddIndexDdl'], $toIndex);
        $ret .= $this->mapConcat([$this, 'buildAddIndexDdl'], $tableDiff->getAddedIndices());

        $toFks = array_column($tableDiff->getModifiedFks(), 1);
        $ret .= $this->mapConcat([$this, 'buildAddForeignKeyDdl'], $toFks);
        $ret .= $this->mapConcat([$this, 'buildAddForeignKeyDdl'], $tableDiff->getAddedFks());

        return $ret;
    }

    /**
     * @param \Propel\Generator\Model\Diff\TableDiff $tableDiff
     *
     * @return string
     */
    public function buildModifyTableColumnsDdl(TableDiff $tableDiff): string
    {
        $ret = '';

        $ret .= $this->mapConcat([$this, 'buildRemoveColumnDdl'], $tableDiff->getRemovedColumns());

        foreach ($tableDiff->getRenamedColumns() as $columnRenaming) {
            $ret .= $this->buildRenameColumnDdl(...$columnRenaming);
        }

        $modifiedColumns = $tableDiff->getModifiedColumns();
        if ($modifiedColumns) {
            $ret .= $this->buildModifyColumnsDdl($modifiedColumns);
        }

        $addedColumns = $tableDiff->getAddedColumns();
        if ($addedColumns) {
            $ret .= $this->buildAddColumnsDdl($addedColumns);
        }

        return $ret;
    }

    /**
     * @param \Propel\Generator\Model\Diff\TableDiff $tableDiff
     *
     * @return string
     */
    public function buildModifyTablePrimaryKeyDdl(TableDiff $tableDiff): string
    {
        return $tableDiff->hasModifiedPk()
            ? $this->buildDropPrimaryKeyDdl($tableDiff->getFromTable())
            . $this->buildAddPrimaryKeyDdl($tableDiff->getToTable())
            : '';
    }

    /**
     * @param \Propel\Generator\Model\Diff\TableDiff $tableDiff
     *
     * @return string
     */
    public function buildModifyTableIndicesDdl(TableDiff $tableDiff): string
    {
        $ret = '';

        $ret .= $this->mapConcat([$this, 'buildDropIndexDdl'], $tableDiff->getRemovedIndices());
        $ret .= $this->mapConcat([$this, 'buildAddIndexDdl'], $tableDiff->getAddedIndices());

        foreach ($tableDiff->getModifiedIndices() as $indexModification) {
            [$fromIndex, $toIndex] = $indexModification;
            $ret .= $this->buildDropIndexDdl($fromIndex);
            $ret .= $this->buildAddIndexDdl($toIndex);
        }

        return $ret;
    }

    /**
     * @param \Propel\Generator\Model\Diff\TableDiff $tableDiff
     *
     * @return string
     */
    public function buildModifyTableForeignKeysDdl(TableDiff $tableDiff): string
    {
        $ret = '';

        $ret .= $this->mapConcat([$this, 'buildDropForeignKeyDdl'], $tableDiff->getRemovedFks());
        $ret .= $this->mapConcat([$this, 'buildAddForeignKeyDdl'], $tableDiff->getAddedFks());

        foreach ($tableDiff->getModifiedFks() as $fkModification) {
            [$fromFk, $toFk] = $fkModification;
            $ret .= $this->buildDropForeignKeyDdl($fromFk);
            $ret .= $this->buildAddForeignKeyDdl($toFk);
        }

        return $ret;
    }

    /**
     * @param \Propel\Generator\Model\Column $column
     *
     * @return string
     */
    public function buildRemoveColumnDdl(Column $column): string
    {
        $tableName = $this->quoteIdentifier($column->getTableName());
        $columnName = $this->quoteIdentifier($column->getName());

        return "\nALTER TABLE $tableName DROP COLUMN $columnName;\n";
    }

    /**
     * @param \Propel\Generator\Model\Column $fromColumn
     * @param \Propel\Generator\Model\Column $toColumn
     *
     * @return string
     */
    public function buildRenameColumnDdl(Column $fromColumn, Column $toColumn): string
    {
        $tableName = $this->quoteIdentifier($fromColumn->getTableName());
        $currentColumnName = $this->quoteIdentifier($fromColumn->getName());
        $newColumnName = $this->quoteIdentifier($toColumn->getName());

        return "\nALTER TABLE $tableName RENAME COLUMN $currentColumnName TO $newColumnName;\n";
    }

    /**
     * @param \Propel\Generator\Model\Diff\ColumnDiff $columnDiff
     *
     * @return string
     */
    public function buildModifyColumnDdl(ColumnDiff $columnDiff): string
    {
        return $this->buildAlterColumnDdl($columnDiff->getToColumn(), 'MODIFY');
    }

    /**
     * @param \Propel\Generator\Model\Column $column
     *
     * @return string
     */
    public function buildAddColumnDdl(Column $column): string
    {
        return $this->buildAlterColumnDdl($column, 'ADD');
    }

    /**
     * @param \Propel\Generator\Model\Column $column
     * @param string $modifier
     *
     * @return string
     */
    protected function buildAlterColumnDdl(Column $column, string $modifier = 'ADD'): string
    {
        $tableName = $this->quoteIdentifier($column->getTableName());
        $columnDdl = $this->buildColumnDdl($column);

        return "\nALTER TABLE $tableName $modifier $columnDdl;\n";
    }

    /**
     * @param array<\Propel\Generator\Model\Diff\ColumnDiff> $columnDiffs
     *
     * @return string
     */
    public function buildModifyColumnsDdl(array $columnDiffs): string
    {
        $toColumns = array_filter(array_map(fn (ColumnDiff $d) => $d->getToColumn(), $columnDiffs));

        return $this->buildModifyMultipleColumnsDdl($toColumns, 'MODIFY');
    }

    /**
     * @param array<\Propel\Generator\Model\Column> $columns
     *
     * @return string
     */
    public function buildAddColumnsDdl(array $columns): string
    {
        return $this->buildModifyMultipleColumnsDdl($columns, 'ADD');
    }

    /**
     * @param array<\Propel\Generator\Model\Column> $columns
     * @param string $modifier
     *
     * @return string
     */
    protected function buildModifyMultipleColumnsDdl(array $columns, string $modifier = 'ADD'): string
    {
        $tableColumn = array_find($columns, fn (Column $c) => (bool)$c->getTable());
        assert($tableColumn !== null);
        $tableName = $this->quoteIdentifier($tableColumn->getTableName());
        $columnsDdl = $this->mapConcat([$this, 'buildColumnDdl'], $columns, ",\n    ");

        return "\nALTER TABLE $tableName $modifier\n(\n    $columnsDdl\n);\n";
    }

    /**
     * Returns if the RDBMS-specific SQL type has a size attribute.
     *
     * @param string $sqlType
     *
     * @return bool
     */
    #[\Override]
    public function hasSize(string $sqlType): bool
    {
        return true;
    }

    /**
     * Returns if the RDBMS-specific SQL type has a scale attribute.
     *
     * @param string $sqlType
     *
     * @return bool
     */
    #[\Override]
    public function hasScale(string $sqlType): bool
    {
        return true;
    }

    /**
     * Quote and escape needed characters in the string for underlying RDBMS.
     *
     * @param string $text
     *
     * @return string
     */
    #[\Override]
    public function quote(string $text): string
    {
        $con = $this->getConnection();

        return $con
            ? $con->quote($text)
            : "'" . $this->disconnectedEscapeText($text) . "'";
    }

    /**
     * Method to escape text when no connection has been set.
     *
     * The subclasses can implement this using string replacement functions
     * or native DB methods.
     *
     * @param string $text Text that needs to be escaped.
     *
     * @return string
     */
    protected function disconnectedEscapeText(string $text): string
    {
        return str_replace("'", "''", $text);
    }

    /**
     * Quotes identifiers used in database SQL if isIdentifierQuotingEnabled is true.
     * Calls doQuoting() when identifierQuoting is enabled.
     *
     * @param string $text
     *
     * @return string Quoted identifier.
     */
    #[\Override]
    public function quoteIdentifier(string $text): string
    {
        return $this->isIdentifierQuotingEnabled() ? $this->doQuoting($text) : $text;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function doQuoting(string $text): string
    {
        return '"' . strtr($text, ['.' => '"."']) . '"';
    }

    /**
     * Whether RDBMS supports native ON DELETE triggers (e.g. ON DELETE CASCADE).
     *
     * @return bool
     */
    #[\Override]
    public function supportsNativeDeleteTrigger(): bool
    {
        return false;
    }

    /**
     * Whether RDBMS supports INSERT null values in autoincremented primary keys
     *
     * @return bool
     */
    #[\Override]
    public function supportsInsertNullPk(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    #[\Override]
    public function supportsIndexSize(): bool
    {
        return false;
    }

    /**
     * Whether the underlying PDO driver for this platform returns BLOB columns as streams (instead of strings).
     *
     * @return bool
     */
    #[\Override]
    public function hasStreamBlobImpl(): bool
    {
        return false;
    }

    /**
     * @see Platform::supportsSchemas()
     *
     * @return bool
     */
    #[\Override]
    public function supportsSchemas(): bool
    {
        return false;
    }

    /**
     * @see Platform::supportsMigrations()
     *
     * @return bool
     */
    #[\Override]
    public function supportsMigrations(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    #[\Override]
    public function supportsVarcharWithoutSize(): bool
    {
        return false;
    }

    /**
     * Returns the boolean value.
     *
     * This value should match the boolean value that is set
     * when using Propel's PreparedStatement::setBoolean().
     *
     * This function is used to set default column values when building
     * SQL.
     *
     * @param string|int|bool $value A Boolean or string representation of Boolean ('y', 'true').
     *
     * @return string
     */
    #[\Override]
    public function getBooleanString($value): string
    {
        return filter_var($value, FILTER_VALIDATE_BOOL) || (is_string($value) && strtolower($value) === 'y') ? '1' : '0';
    }

    /**
     * @param string $stringValue
     *
     * @return string|null
     */
    public function getPhpArrayString(string $stringValue): ?string
    {
        $arrayStringContent = MappingModel::buildDefaultValueExpressionForArray($stringValue);

        return $arrayStringContent ? $this->quote($arrayStringContent) : null;
    }

    /**
     * Gets the preferred timestamp formatter for setting date/time values.
     *
     * @param bool $withMilliseconds
     *
     * @return string
     */
    #[\Override]
    public function getTimestampFormatter(bool $withMilliseconds = true): string
    {
        return $this->getDateFormatter() . ' ' . $this->getTimeFormatter($withMilliseconds);
    }

    /**
     * Gets the preferred time formatter for setting date/time values.
     *
     * @param bool $withMilliseconds
     *
     * @return string
     */
    #[\Override]
    public function getTimeFormatter(bool $withMilliseconds = true): string
    {
        return 'H:i:s' . ($withMilliseconds ? '.u' : '');
    }

    /**
     * Gets the preferred date formatter for setting date/time values.
     *
     * @return string
     */
    #[\Override]
    public function getDateFormatter(): string
    {
        return 'Y-m-d';
    }

    /**
     * Returns the appropriate formatter for a date/time column.
     *
     * @param \Propel\Generator\Model\Column $column
     *
     * @return string|null
     */
    #[\Override]
    public function getTemporalFormatter(Column $column): string|null
    {
        $withMilliseconds = (bool)$column->getTypeMapping()->getSize();

        return match ($column->getColumnType()) {
            ColumnType::DATE => $this->getDateFormatter(),
            ColumnType::TIME => $this->getTimeFormatter($withMilliseconds),
            ColumnType::TIMESTAMP,
            ColumnType::DATETIME => $this->getTimestampFormatter($withMilliseconds),
            default => null,
        };
    }

    /**
     * Get the default On Delete behavior for foreign keys when not explicitly set.
     *
     * @return string
     */
    #[\Override]
    public function getDefaultForeignKeyOnDeleteBehavior(): string
    {
        return ForeignKey::NONE;
    }

    /**
     * Get the default On Update behavior for foreign keys when not explicitly set.
     *
     * @return string
     */
    #[\Override]
    public function getDefaultForeignKeyOnUpdateBehavior(): string
    {
        return ForeignKey::NONE;
    }

    /**
     * Get the PHP snippet for binding a value to a column.
     * Warning: duplicates logic from AdapterInterface::bindValue().
     * Any code modification here must be ported there.
     *
     * @param \Propel\Generator\Model\Column $column
     * @param string $identifier
     * @param string $columnValueAccessor
     * @param string $tab
     *
     * @return string
     */
    #[\Override]
    public function getColumnBindingPHP(Column $column, string $identifier, string $columnValueAccessor, string $tab = '            '): string
    {
        $script = '';
        if ($column->isLobType()) {
            // we always need to make sure that the stream is rewound, otherwise nothing will
            // get written to database.
            $script .= "
if (is_resource($columnValueAccessor)) {
    rewind($columnValueAccessor);
}";
        }

        $pdoType = $column->getColumnType()->toPdoConstantName();
        $script .= "\n\$stmt->bindValue($identifier, $columnValueAccessor, $pdoType);";

        return preg_replace('/^(.+)/m', $tab . '$1', $script);
    }

    /**
     * Get the PHP snippet for getting a Pk from the database.
     *
     * Typical output:
     * <code>
     * $this->id = $con->lastInsertId();
     * </code>
     *
     * @param string $targetVariable
     * @param string $connectionVariable
     * @param string|null $sequenceName
     * @param string $indent
     * @param string|null $phpType
     *
     * @return string
     */
    public function buildLastInsertedIdStatement(
        string $targetVariable,
        string $connectionVariable = '$con',
        string|null $sequenceName = null,
        string $indent = '            ',
        string|null $phpType = null
    ): string {
        $typecast = $phpType ? "($phpType)" : '';
        $sequenceName = $sequenceName ? "'$sequenceName'" : '';

        return "\n{$indent}{$targetVariable} = {$typecast}{$connectionVariable}->lastInsertId($sequenceName);";
    }

    /**
     * Get the PHP snippet for getting a Pk from the database.
     *
     * Typical output:
     * <code>
     * $this->id = $con->lastInsertId();
     * </code>
     *
     * @param string $targetVariable
     * @param string $connectionVariableName
     * @param string|null $sequenceName
     * @param string $indent
     * @param string|null $phpType
     *
     * @throws \Propel\Generator\Exception\EngineException
     *
     * @return string
     */
    public function buildLoadNextSequenceValueStatement(
        string $targetVariable,
        string $connectionVariableName = '$con',
        string|null $sequenceName = null,
        string $indent = '            ',
        string|null $phpType = null
    ): string {
        throw new EngineException('Platform ' . static::class . ' does not support loading sequence values.');
    }

    /**
     * Returns an integer indexed array of default type sizes.
     *
     * @return array<int> type indexed array of integers
     */
    public function getDefaultTypeSizes(): array
    {
        return [];
    }

    /**
     * Returns the default size of a specific type.
     *
     * @param \Propel\Generator\Model\Datatype\ColumnType $type
     *
     * @return int
     */
    public function getDefaultTypeSize(ColumnType $type): int
    {
        $sizes = $this->getDefaultTypeSizes();

        return $sizes[strtolower($type->name)] ?? 0;
    }

    /**
     * Normalizes a table for the current platform. Very important for the TableComparator to not
     * generate useless diffs.
     * Useful for checking needed definitions/structures. E.g. Unique Indexes for ForeignKey columns,
     * which the most Platforms requires but which is not always explicitly defined in the table model.
     *
     * @param \Propel\Generator\Model\Table $table The table object which gets modified.
     *
     * @return void
     */
    #[\Override]
    public function normalizeTable(Table $table): void
    {
        if ($table->hasForeignKeys()) {
            foreach ($table->getForeignKeys() as $fk) {
                if (!$fk->getForeignTable() || $fk->getForeignTable()->isUnique($fk->getForeignColumnObjects())) {
                    continue;
                }
                $unique = new Unique();
                $unique->setColumns($fk->getForeignColumnObjects());
                $fk->getForeignTable()->addUnique($unique);
            }
        }

        if (!$this->supportsIndexSize() && $table->getIndices()) {
            // when the platform does not support index sizes we reset it
            foreach ($table->getIndices() as $index) {
                $index->resetColumnsSize();
            }
        }

        foreach ($table->getColumns() as $column) {
            $defaultSize = $this->getDefaultTypeSize($column->getColumnType());

            if ($column->getSize() && $defaultSize && $column->getScale() === null && (int)$column->getSize() === $defaultSize) {
                $column->setSize(null);
            }
        }
    }

    /**
     * @param \Propel\Generator\Model\Datatype\ColumnType $columnType
     * @param array<string> $valueSet
     *
     * @throws \Propel\Generator\Exception\EngineException
     *
     * @return string
     */
    #[\Override]
    public function buildNativeEnumeratedColumnSqlType(ColumnType $columnType, array $valueSet): string
    {
        if (!in_array($columnType, [ColumnType::ENUM_NATIVE, ColumnType::SET_NATIVE])) {
            throw new EngineException("Only native ENUM or SET type columns can be turned to sql type, but type is {$columnType->name}");
        }

        $typeLiteral = $columnType === ColumnType::ENUM_NATIVE ? 'ENUM' : 'SET';
        $valuesCsv = "'" . implode("','", $valueSet) . "'";

        return "$typeLiteral($valuesCsv)";
    }

    /**
     * @param string $name
     * @param array $arguments
     *
     * @throws \BadMethodCallException
     *
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        if (str_starts_with($name, 'get') && str_ends_with($name, 'DDL')) {
            $newName = 'build' . substr($name, 3, -3) . 'Ddl';
            trigger_deprecation('Perpl', '2.10.4', "Update to new function name: $name() is now $newName()");

            return $this->$newName(...$arguments);
        }

        throw new BadMethodCallException(sprintf('Undefined method %s::%s()', self::class, $name));
    }
}
