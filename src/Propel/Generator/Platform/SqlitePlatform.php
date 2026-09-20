<?php

declare(strict_types = 1);

namespace Propel\Generator\Platform;

use PDO;
use Propel\Generator\Config\AbstractGeneratorConfig;
use Propel\Generator\Model\Column;
use Propel\Generator\Model\ColumnDefaultValue;
use Propel\Generator\Model\Database;
use Propel\Generator\Model\Datatype\ColumnType;
use Propel\Generator\Model\Diff\ColumnDiff;
use Propel\Generator\Model\Diff\TableDiff;
use Propel\Generator\Model\ForeignKey;
use Propel\Generator\Model\IdMethod;
use Propel\Generator\Model\Table;
use Propel\Generator\Model\Unique;
use Propel\Runtime\Connection\PdoConnection;
use RuntimeException;
use SQLite3;
use function array_keys;
use function class_exists;
use function filter_var;
use function implode;
use function in_array;
use function str_replace;
use function str_starts_with;
use function strtr;
use function trim;
use function uniqid;
use function version_compare;
use const FILTER_VALIDATE_BOOLEAN;

/**
 * SQLite PlatformInterface implementation.
 */
class SqlitePlatform extends DefaultPlatform
{
    protected bool $foreignKeySupport;

    /**
     * If we should alter the table through creating a temporarily created table,
     * moving all items to the new one and finally rename the temp table.
     */
    protected bool $tableAlteringWorkaround = true;

    /**
     * @return void
     */
    #[\Override]
    protected function initialize(): void
    {
        parent::initialize();

        $version = $this->getVersion();

        $this->foreignKeySupport = version_compare($version, '3.6.19') >= 0;
    }

    /**
     * @param \Propel\Generator\Model\Datatype\ColumnType $type
     *
     * @return string|null
     */
    #[\Override]
    protected function resolveSqlType(ColumnType $type): string|null
    {
        return match ($type) {
            ColumnType::NUMERIC => 'DECIMAL',
            ColumnType::LONGVARCHAR,
            ColumnType::ARRAY,
            => 'MEDIUMTEXT',
            ColumnType::DATE,
            ColumnType::DATETIME,
            => 'DATETIME',
            ColumnType::VARBINARY => 'MEDIUMBLOB',
            ColumnType::LONGVARBINARY => 'LONGBLOB',
            ColumnType::CLOB => 'LONGTEXT',
            ColumnType::BINARY,
            ColumnType::BLOB,
            ColumnType::OBJECT,
            ColumnType::UUID,
            ColumnType::UUID_BINARY,
             => 'BLOB',
            default => parent::resolveSqlType($type)
        };
    }

    /**
     * @phpstan-return non-empty-string
     *
     * @return string
     */
    #[\Override]
    public function getSchemaDelimiter(): string
    {
        return '§';
    }

    /**
     * @return array<int>
     */
    #[\Override]
    public function getDefaultTypeSizes(): array
    {
        return [
            'char' => 1,
            'character' => 1,
            'integer' => 32,
            'bigint' => 64,
            'smallint' => 16,
            'double precision' => 54,
        ];
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function setGeneratorConfig(AbstractGeneratorConfig $generatorConfig): void
    {
        parent::setGeneratorConfig($generatorConfig);

        $foreignKeySupport = $generatorConfig->getConfigProperty('database.adapter.sqlite.foreignKey');
        if ($foreignKeySupport !== null) {
            $this->foreignKeySupport = filter_var($foreignKeySupport, FILTER_VALIDATE_BOOLEAN);
        }
        $tableAlteringWorkaround = $generatorConfig->getConfigProperty('database.adapter.sqlite.tableAlteringWorkaround');
        if ($tableAlteringWorkaround !== null) {
            $this->tableAlteringWorkaround = filter_var($tableAlteringWorkaround, FILTER_VALIDATE_BOOLEAN);
        }
    }

    /**
     * Builds the DDL SQL to remove a list of columns
     *
     * @param array<\Propel\Generator\Model\Column> $columns
     *
     * @return string
     */
    #[\Override]
    public function buildAddColumnsDdl(array $columns): string
    {
        $ret = '';
        foreach ($columns as $column) {
            $tableName = $this->quoteIdentifier($column->getTableName());
            $columnDll = $this->buildColumnDdl($column);
            $ret .= "
ALTER TABLE $tableName ADD $columnDll;
";
        }

        return $ret;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function buildModifyTableDdl(TableDiff $tableDiff): string
    {
        $changedNotEditableThroughDirectDDL = $this->tableAlteringWorkaround && (
            $tableDiff->hasModifiedFks()
            || $tableDiff->hasModifiedIndices()
            || $tableDiff->hasModifiedColumns()
            || $tableDiff->hasRenamedColumns()

            || $tableDiff->hasRemovedFks()
            || $tableDiff->hasRemovedIndices()
            || $tableDiff->hasRemovedColumns()

            || $tableDiff->hasAddedIndices()
            || $tableDiff->hasAddedFks()
            || $tableDiff->hasAddedPkColumns()
        );

        if ($this->tableAlteringWorkaround && !$changedNotEditableThroughDirectDDL && $tableDiff->hasAddedColumns()) {
            $addedCols = $tableDiff->getAddedColumns();
            foreach ($addedCols as $column) {
                $sqlChangeNotSupported =
                    //The column may not have a PRIMARY KEY or UNIQUE constraint.
                    $column->isPrimaryKey() || $column->isUnique()

                    //The column may not have a default value of CURRENT_TIME, CURRENT_DATE, CURRENT_TIMESTAMP,
                    //or an expression in parentheses.
                    || ($column->getDefaultValue() && (
                        in_array(
                            $column->getDefaultValue()->getValue(),
                            ['CURRENT_TIME', 'CURRENT_DATE', 'CURRENT_TIMESTAMP'],
                            true,
                        )
                        || str_starts_with(trim((string)$column->getDefaultValue()->getValue()), '(')
                    ))

                    //If a NOT NULL constraint is specified, then the column must have a default value other than NULL.
                    || ($column->isNotNull() && $column->getDefaultValue()->getValue() === 'NULL');

                if ($sqlChangeNotSupported) {
                    $changedNotEditableThroughDirectDDL = true;

                    break;
                }
            }
        }

        if ($changedNotEditableThroughDirectDDL) {
            return $this->buildMigrationTableDdl($tableDiff);
        }

        return parent::buildModifyTableDdl($tableDiff);
    }

    /**
     * Creates a temporarily created table with the new schema,
     * moves all items into it and drops the origin as well as renames the temp table to the origin then.
     *
     * @param \Propel\Generator\Model\Diff\TableDiff $tableDiff
     *
     * @return string
     */
    public function buildMigrationTableDdl(TableDiff $tableDiff): string
    {
        $originTable = clone $tableDiff->getFromTable();
        $newTable = clone $tableDiff->getToTable();

        $originTableName = $originTable->getName();
        $tempTableName = $newTable->getCommonName() . '__temp__' . uniqid();

        $originTableFields = $this->buildColumnListDdl($originTable->getColumns());

        $fieldMap = [];
        //start with modified columns
        foreach ($tableDiff->getModifiedColumns() as $diff) {
            $fieldMap[$diff->getFromColumn()->getName()] = $diff->getToColumn()->getName();
        }

        foreach ($tableDiff->getRenamedColumns() as $col) {
            [$from, $to] = $col;
            $fieldMap[$from->getName()] = $to->getName();
        }

        foreach ($newTable->getColumns() as $col) {
            if ($originTable->hasColumn($col)) {
                if (!isset($fieldMap[$col->getName()])) {
                    $fieldMap[$col->getName()] = $col->getName();
                }
            }
        }

        $createTable = $this->buildAddTableDdl($newTable);
        $createTable .= $this->buildAddIndicesDdl($newTable);

        $tempTableName = $this->quoteIdentifier($tempTableName);
        $sourceTableName = $this->quoteIdentifier($originTableName);
        $mappedFieldNames = implode(', ', $fieldMap);
        $originalFieldNames = implode(', ', array_keys($fieldMap));

        return "
    CREATE TEMPORARY TABLE $tempTableName AS SELECT $originTableFields FROM $sourceTableName;
    DROP TABLE $sourceTableName;
    $createTable
    INSERT INTO $sourceTableName ($mappedFieldNames) SELECT $originalFieldNames FROM $tempTableName;
    DROP TABLE $tempTableName;\n";
    }

    /**
     * @return string
     */
    #[\Override]
    public function buildBeginDdl(): string
    {
        return '
PRAGMA foreign_keys = OFF;
';
    }

    /**
     * @return string
     */
    #[\Override]
    public function buildEndDdl(): string
    {
        return '
PRAGMA foreign_keys = ON;
';
    }

    /**
     * @param \Propel\Generator\Model\Database $database
     *
     * @return string
     */
    #[\Override]
    public function buildAddTablesDdl(Database $database): string
    {
        $ret = '';
        foreach ($database->getTablesForSql() as $table) {
            $this->normalizeTable($table);
        }
        foreach ($database->getTablesForSql() as $table) {
            $ret .= $this->buildCommentBlockDdl($table->getName());
            $ret .= $this->buildDropTableDdl($table);
            $ret .= $this->buildAddTableDdl($table);
            $ret .= $this->buildAddIndicesDdl($table);
        }

        return $ret;
    }

    /**
     * Unfortunately, SQLite does not support composite pks where one is AUTOINCREMENT,
     * so we have to flag both as NOT NULL and create in either way a UNIQUE constraint over pks since
     * those UNIQUE is otherwise automatically created by the sqlite engine.
     *
     * @param \Propel\Generator\Model\Table $table
     *
     * @return void
     */
    #[\Override]
    public function normalizeTable(Table $table): void
    {
        if ($table->getPrimaryKey()) {
            //search if there is already a UNIQUE constraint over the primary keys
            $pkUniqueExist = false;
            foreach ($table->getUnices() as $unique) {
                $coversAllPrimaryKeys = true;
                foreach ($unique->getColumns() as $columnName) {
                    if (!$table->getColumn($columnName)->isPrimaryKey()) {
                        $coversAllPrimaryKeys = false;

                        break;
                    }
                }
                if ($coversAllPrimaryKeys) {
                    //there's already a unique constraint with the composite pk
                    $pkUniqueExist = true;

                    break;
                }
            }

            //there is none, let's create it
            if (!$pkUniqueExist) {
                $unique = new Unique();
                foreach ($table->getPrimaryKey() as $pk) {
                    $unique->addColumn($pk);
                }
                $table->addUnique($unique);
            }

            if ($table->hasAutoIncrementPrimaryKey()) {
                foreach ($table->getPrimaryKey() as $pk) {
                    $pk->setNotNull(true);
                    //in SQLite the column with the AUTOINCREMENT MUST be a primary key, too.
                    if (!$pk->isAutoIncrement()) {
                        //for all other sub keys we remove it, since we create a UNIQUE constraint over all primary keys.
                        $pk->setPrimaryKey(false);
                    }
                }
            }
        }

        parent::normalizeTable($table);
    }

    /**
     * Returns the SQL for the primary key of a Table object
     *
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    #[\Override]
    public function buildPrimaryKeyDdl(Table $table): string
    {
        if (!$table->hasPrimaryKey() || $table->hasAutoIncrementPrimaryKey()) {
            return '';
        }
        $columnDdl = $this->buildColumnListDdl($table->getPrimaryKey());

        return "PRIMARY KEY ($columnDdl)";
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function buildRemoveColumnDdl(Column $column): string
    {
        //not supported
        return '';
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function buildRenameColumnDdl(Column $fromColumn, Column $toColumn): string
    {
        //not supported
        return '';
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function buildModifyColumnDdl(ColumnDiff $columnDiff): string
    {
        //not supported
        return '';
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function buildModifyColumnsDdl($columnDiffs): string
    {
        //not supported
        return '';
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function buildDropPrimaryKeyDdl(Table $table): string
    {
        //not supported
        return '';
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function buildAddPrimaryKeyDdl(Table $table): string
    {
        //not supported
        return '';
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function buildAddForeignKeyDdl(ForeignKey $fk): string
    {
        //not supported
        return '';
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function buildDropForeignKeyDdl(ForeignKey $fk): string
    {
        //not supported
        return '';
    }

    /**
     * @return \Propel\Generator\Model\IdMethod
     */
    #[\Override]
    public function getNativeIdMethod(): IdMethod
    {
        return IdMethod::AUTO_INCREMENT;
    }

    /**
     * Build column DDL fragment for id method (i.e. 'AUTO_INCREMENT' for native id method in MySQL)
     *
     * @link http://www.sqlite.org/autoinc.html
     *
     * @param \Propel\Generator\Model\IdMethod $idMethod
     * @param \Propel\Generator\Model\Column $column
     *
     * @return string|null Null means id method is not supported (might trigger Exception),
     *                     empty string means column DDL is not affected by id method.
     */
    #[\Override]
    protected function resolveAutoIncrementColumnDdl(IdMethod $idMethod, Column $column): string|null
    {
        return match ($idMethod) {
            IdMethod::AUTO_INCREMENT,
            => 'PRIMARY KEY AUTOINCREMENT',
            IdMethod::SEQUENCE,
            IdMethod::NO_ID_METHOD
            => '',
            default => null,
        };
    }

    /**
     * @return int
     */
    #[\Override]
    public function getMaxColumnNameLength(): int
    {
        return 1024;
    }

    /**
     * @param \Propel\Generator\Model\Column $col
     *
     * @return string
     */
    #[\Override]
    public function buildColumnDdl(Column $col): string
    {
        if ($col->isAutoIncrement()) {
            $col->setUpTypeMapping(ColumnType::INTEGER);
        }

        if (
            $col->getDefaultValue()
            && $col->getDefaultValue()->isExpression()
            && in_array($col->getDefaultValue()->getValue(), ['CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'])
        ) {
            //sqlite use CURRENT_TIMESTAMP different than mysql/pgsql etc
            //we set it to the more common behavior
            $col->setDefaultValue(
                new ColumnDefaultValue("(datetime(CURRENT_TIMESTAMP, 'localtime'))", ColumnDefaultValue::TYPE_EXPR),
            );
        }

        return parent::buildColumnDdl($col);
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    #[\Override]
    public function buildAddTableDdl(Table $table): string
    {
        $table = clone $table;
        $tableDescription = $table->hasDescription() ? $this->buildCommentLineDdl($table->getDescription()) : '';

        $lines = [];

        foreach ($table->getColumns() as $column) {
            $lines[] = $this->buildColumnDdl($column);
        }

        $pk = $this->buildPrimaryKeyDdl($table);
        if ($pk) {
            $lines[] = $pk;
        }

        foreach ($table->getUnices() as $unique) {
            $lines[] = $this->buildUniqueDdl($unique);
        }

        if ($this->foreignKeySupport) {
            foreach ($table->getForeignKeys() as $foreignKey) {
                if ($foreignKey->isSkipSql() || $foreignKey->isPolymorphic()) {
                    continue;
                }
                $fkDdl = $this->buildForeignKeyDdl($foreignKey);
                $lines[] = str_replace("\n    ", "\n        ", $fkDdl);
            }
        }

        $tableName = $this->quoteIdentifier($table->getName());
        $columnDefinitions = implode(",\n    ", $lines);

        return "
{$tableDescription}CREATE TABLE $tableName
(
    $columnDefinitions
);\n";
    }

    /**
     * @param \Propel\Generator\Model\ForeignKey $fk
     *
     * @return string
     */
    #[\Override]
    public function buildForeignKeyDdl(ForeignKey $fk): string
    {
        if ($fk->isSkipSql() || !$this->foreignKeySupport || $fk->isPolymorphic()) {
            return '';
        }

        $localColumns = $this->buildColumnListDdl($fk->getLocalColumnObjects());
        $foreignTable = $this->quoteIdentifier($fk->getForeignTableName());
        $foreignColumns = $this->buildColumnListDdl($fk->getForeignColumnObjects());

        $onUpdate = $fk->hasOnUpdate() ? "\n    ON UPDATE " . $fk->getOnUpdate() : '';
        $onDelete = $fk->hasOnDelete() ? "\n    ON DELETE " . $fk->getOnDelete() : '';

        return "FOREIGN KEY ($localColumns) REFERENCES $foreignTable ($foreignColumns){$onUpdate}{$onDelete}";
    }

    /**
     * @param string $sqlType
     *
     * @return bool
     */
    #[\Override]
    public function hasSize(string $sqlType): bool
    {
        return !in_array($sqlType, [
            'MEDIUMTEXT',
            'LONGTEXT',
            'BLOB',
            'MEDIUMBLOB',
            'LONGBLOB',
        ], true);
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function doQuoting(string $text): string
    {
        return '[' . strtr($text, ['.' => '].[']) . ']';
    }

    /**
     * @return bool
     */
    #[\Override]
    public function supportsSchemas(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    #[\Override]
    public function supportsNativeDeleteTrigger(): bool
    {
        return true;
    }

    /**
     * @throws \RuntimeException
     *
     * @return string
     */
    protected function getVersion(): string
    {
        if (class_exists(SQLite3::class)) {
            return SQLite3::version()['versionString'];
        }

        //if php_sqlite3 extension is not installed, we need to query the database
        $connection = new PdoConnection('sqlite::memory:');
        $pdoStatement = $connection->query('SELECT sqlite_version()');

        if ($pdoStatement === false) {
            throw new RuntimeException('PdoConnection::query() did not return a result set as a statement object.');
        }

        return (string)$pdoStatement->fetch(PDO::FETCH_NUM)[0];
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
        return parent::getTimestampFormatter(true);
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
        return parent::getTimeFormatter(true);
    }
}
