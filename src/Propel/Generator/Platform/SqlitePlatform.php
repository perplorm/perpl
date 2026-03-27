<?php

declare(strict_types = 1);

namespace Propel\Generator\Platform;

use PDO;
use Propel\Generator\Config\AbstractGeneratorConfig;
use Propel\Generator\Model\Column;
use Propel\Generator\Model\ColumnDefaultValue;
use Propel\Generator\Model\Database;
use Propel\Generator\Model\Diff\ColumnDiff;
use Propel\Generator\Model\Diff\TableDiff;
use Propel\Generator\Model\Domain;
use Propel\Generator\Model\ForeignKey;
use Propel\Generator\Model\PropelTypes;
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
use function sprintf;
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
    /**
     * If we should generate FOREIGN KEY statements.
     * This is since SQLite version 3.6.19 possible.
     *
     * @var bool|null
     */
    protected $foreignKeySupport;

    /**
     * If we should alter the table through creating a temporarily created table,
     * moving all items to the new one and finally rename the temp table.
     *
     * @var bool
     */
    protected $tableAlteringWorkaround = true;

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
     * Initializes db specific domain mapping.
     *
     * @return void
     */
    #[\Override]
    protected function initializeTypeMap(): void
    {
        parent::initializeTypeMap();

        $this->setSchemaDomainMapping(new Domain(PropelTypes::NUMERIC, 'DECIMAL'));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::LONGVARCHAR, 'MEDIUMTEXT'));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::DATE, 'DATETIME'));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::DATETIME, 'DATETIME'));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::BINARY, 'BLOB'));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::VARBINARY, 'MEDIUMBLOB'));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::LONGVARBINARY, 'LONGBLOB'));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::BLOB, 'BLOB'));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::CLOB, 'LONGTEXT'));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::OBJECT, 'BLOB'));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::PHP_ARRAY, 'MEDIUMTEXT'));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::UUID_BINARY, 'BLOB'));

        // no native UUID type, use UUID_BINARY
        $this->schemaDomainMap[PropelTypes::UUID] = $this->schemaDomainMap[PropelTypes::UUID_BINARY];

        $this->setSetTypesMapping(false);
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
    public function getAddColumnsDDL(array $columns): string
    {
        $ret = '';
        $pattern = "
ALTER TABLE %s ADD %s;
";
        foreach ($columns as $column) {
            $tableName = $column->getTable()->getName();
            $ret .= sprintf(
                $pattern,
                $this->quoteIdentifier($tableName),
                $this->getColumnDDL($column),
            );
        }

        return $ret;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getModifyTableDDL(TableDiff $tableDiff): string
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
            return $this->getMigrationTableDDL($tableDiff);
        }

        return parent::getModifyTableDDL($tableDiff);
    }

    /**
     * Creates a temporarily created table with the new schema,
     * moves all items into it and drops the origin as well as renames the temp table to the origin then.
     *
     * @param \Propel\Generator\Model\Diff\TableDiff $tableDiff
     *
     * @return string
     */
    public function getMigrationTableDDL(TableDiff $tableDiff): string
    {
        $pattern = "
CREATE TEMPORARY TABLE %s AS SELECT %s FROM %s;
DROP TABLE %s;
%s
INSERT INTO %s (%s) SELECT %s FROM %s;
DROP TABLE %s;
";

        $originTable = clone $tableDiff->getFromTable();
        $newTable = clone $tableDiff->getToTable();

        $originTableName = $originTable->getName();
        $tempTableName = $newTable->getCommonName() . '__temp__' . uniqid();

        $originTableFields = $this->getColumnListDDL($originTable->getColumns());

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

        $createTable = $this->getAddTableDDL($newTable);
        $createTable .= $this->getAddIndicesDDL($newTable);

        $sql = sprintf(
            $pattern,
            $this->quoteIdentifier($tempTableName), //CREATE TEMPORARY TABLE %s
            $originTableFields, //select %s
            $this->quoteIdentifier($originTableName), //from %s
            $this->quoteIdentifier($originTableName), //drop table %s
            $createTable, //[create table] %s
            $this->quoteIdentifier($originTableName), //insert into %s
            implode(', ', $fieldMap), //(%s)
            implode(', ', array_keys($fieldMap)), //select %s
            $this->quoteIdentifier($tempTableName), //from %s
            $this->quoteIdentifier($tempTableName), //drop table %s
        );

        return $sql;
    }

    /**
     * @return string
     */
    #[\Override]
    public function getBeginDDL(): string
    {
        return '
PRAGMA foreign_keys = OFF;
';
    }

    /**
     * @return string
     */
    #[\Override]
    public function getEndDDL(): string
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
    public function getAddTablesDDL(Database $database): string
    {
        $ret = '';
        foreach ($database->getTablesForSql() as $table) {
            $this->normalizeTable($table);
        }
        foreach ($database->getTablesForSql() as $table) {
            $ret .= $this->getCommentBlockDDL($table->getName());
            $ret .= $this->getDropTableDDL($table);
            $ret .= $this->getAddTableDDL($table);
            $ret .= $this->getAddIndicesDDL($table);
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
                    //no pk can be NULL, as usual
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
    public function getPrimaryKeyDDL(Table $table): string
    {
        if ($table->hasPrimaryKey() && !$table->hasAutoIncrementPrimaryKey()) {
            return 'PRIMARY KEY (' . $this->getColumnListDDL($table->getPrimaryKey()) . ')';
        }

        return '';
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getRemoveColumnDDL(Column $column): string
    {
        //not supported
        return '';
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getRenameColumnDDL(Column $fromColumn, Column $toColumn): string
    {
        //not supported
        return '';
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getModifyColumnDDL(ColumnDiff $columnDiff): string
    {
        //not supported
        return '';
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getModifyColumnsDDL($columnDiffs): string
    {
        //not supported
        return '';
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getDropPrimaryKeyDDL(Table $table): string
    {
        //not supported
        return '';
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getAddPrimaryKeyDDL(Table $table): string
    {
        //not supported
        return '';
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getAddForeignKeyDDL(ForeignKey $fk): string
    {
        //not supported
        return '';
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getDropForeignKeyDDL(ForeignKey $fk): string
    {
        //not supported
        return '';
    }

    /**
     * @link http://www.sqlite.org/autoinc.html
     *
     * @return string
     */
    #[\Override]
    public function getAutoIncrement(): string
    {
        return 'PRIMARY KEY AUTOINCREMENT';
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
    public function getColumnDDL(Column $col): string
    {
        if ($col->isAutoIncrement()) {
            $col->setType('INTEGER');
            $col->setDomainForType('INTEGER');
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

        return parent::getColumnDDL($col);
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    #[\Override]
    public function getAddTableDDL(Table $table): string
    {
        $table = clone $table;
        $tableDescription = $table->hasDescription() ? $this->getCommentLineDDL($table->getDescription()) : '';

        $lines = [];

        foreach ($table->getColumns() as $column) {
            $lines[] = $this->getColumnDDL($column);
        }

        $pk = $this->getPrimaryKeyDDL($table);
        if ($pk) {
            $lines[] = $pk;
        }

        foreach ($table->getUnices() as $unique) {
            $lines[] = $this->getUniqueDDL($unique);
        }

        if ($this->foreignKeySupport) {
            foreach ($table->getForeignKeys() as $foreignKey) {
                if ($foreignKey->isSkipSql() || $foreignKey->isPolymorphic()) {
                    continue;
                }
                $lines[] = str_replace("
    ", "
        ", $this->getForeignKeyDDL($foreignKey));
            }
        }

        $sep = ",
    ";

        $pattern = "
%sCREATE TABLE %s
(
    %s
);
";

        return sprintf(
            $pattern,
            $tableDescription,
            $this->quoteIdentifier($table->getName()),
            implode($sep, $lines),
        );
    }

    /**
     * @param \Propel\Generator\Model\ForeignKey $fk
     *
     * @return string
     */
    #[\Override]
    public function getForeignKeyDDL(ForeignKey $fk): string
    {
        if ($fk->isSkipSql() || !$this->foreignKeySupport || $fk->isPolymorphic()) {
            return '';
        }

        $pattern = 'FOREIGN KEY (%s) REFERENCES %s (%s)';

        $script = sprintf(
            $pattern,
            $this->getColumnListDDL($fk->getLocalColumnObjects()),
            $this->quoteIdentifier($fk->getForeignTableName()),
            $this->getColumnListDDL($fk->getForeignColumnObjects()),
        );

        if ($fk->hasOnUpdate()) {
            $script .= "
    ON UPDATE " . $fk->getOnUpdate();
        }
        if ($fk->hasOnDelete()) {
            $script .= "
    ON DELETE " . $fk->getOnDelete();
        }

        return $script;
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
