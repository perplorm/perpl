<?php

declare(strict_types = 1);

namespace Propel\Generator\Platform;

use PDO;
use Propel\Common\Config\Exception\InvalidConfigurationException;
use Propel\Generator\Config\AbstractGeneratorConfig;
use Propel\Generator\Exception\EngineException;
use Propel\Generator\Model\Column;
use Propel\Generator\Model\Database;
use Propel\Generator\Model\Datatype\ColumnType;
use Propel\Generator\Model\Diff\ColumnDiff;
use Propel\Generator\Model\Diff\DatabaseDiff;
use Propel\Generator\Model\ForeignKey;
use Propel\Generator\Model\IdMethod;
use Propel\Generator\Model\Index;
use Propel\Generator\Model\Table;
use Propel\Generator\Model\Unique;
use Propel\Generator\Platform\Util\MysqlUuidMigrationBuilder;
use function addslashes;
use function array_flip;
use function array_map;
use function array_push;
use function array_search;
use function array_unshift;
use function implode;
use function in_array;
use function is_array;
use function is_numeric;
use function str_replace;
use function stripos;
use function strpos;
use function strtolower;
use function strtoupper;
use function strtr;
use function substr;
use function var_export;

/**
 * MySql PlatformInterface implementation.
 */
class MysqlPlatform extends DefaultPlatform
{
    protected string $tableEngineKeyword = 'ENGINE';

    protected string $defaultTableEngine = 'InnoDB';

    protected string|null $serverVersion = null;

    protected bool $useUuidNativeType = false;

    protected bool $ignoreSizeOnIntegerTypes = true;

    protected bool $hasNativeEnumType = true;

    /**
     * @param \Propel\Generator\Model\Datatype\ColumnType $type
     *
     * @return \Propel\Generator\Model\Datatype\ColumnType
     */
    #[\Override]
    protected function resolveColumnTypeAlias(ColumnType $type): ColumnType
    {
        if ($type === ColumnType::UUID && !$this->useUuidNativeType) {
            return ColumnType::UUID_BINARY;
        }

        return parent::resolveColumnTypeAlias($type);
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
            ColumnType::LONGVARCHAR => 'TEXT',
            ColumnType::BINARY => 'BINARY',
            ColumnType::VARBINARY,
            ColumnType::OBJECT,
            => 'MEDIUMBLOB',
            ColumnType::LONGVARBINARY => 'LONGBLOB',
            ColumnType::CLOB => 'LONGTEXT',
            ColumnType::ARRAY => 'TEXT',
            ColumnType::REAL => 'DOUBLE',
            ColumnType::UUID_BINARY => 'BINARY',
            ColumnType::UUID => 'UUID',
            default => parent::resolveSqlType($type)
        };
    }

    /**
     * @param \Propel\Generator\Model\Datatype\ColumnType $type
     *
     * @return int|null
     */
    #[\Override]
    protected function resolveTypeSize(ColumnType $type): int|null
    {
        return match ($type) {
            ColumnType::BOOLEAN => 1,
            ColumnType::UUID_BINARY => 16,
            default => parent::resolveTypeSize($type)
        };
    }

    /**
     * @param \Propel\Generator\Config\AbstractGeneratorConfig $generatorConfig
     *
     * @throws \Propel\Common\Config\Exception\InvalidConfigurationException
     *
     * @return void
     */
    #[\Override]
    public function setGeneratorConfig(AbstractGeneratorConfig $generatorConfig): void
    {
        parent::setGeneratorConfig($generatorConfig);

        $configProp = 'database.adapters.mysql';
        $mysqlConfig = $generatorConfig->getConfigProperty($configProp, true);
        if (!is_array($mysqlConfig)) {
            throw new InvalidConfigurationException("Config property `$configProp` is supposed to be an array, but is " . var_export($mysqlConfig, true));
        }

        $defaultTableEngine = $mysqlConfig['tableType'];
        if ($defaultTableEngine) {
            $this->defaultTableEngine = $defaultTableEngine;
        }

        $tableEngineKeyword = $mysqlConfig['tableEngineKeyword'];
        if ($tableEngineKeyword) {
            $this->tableEngineKeyword = $tableEngineKeyword;
        }

        $uuidColumnType = $mysqlConfig['uuidColumnType'];
        if ($uuidColumnType) {
            $enable = strtolower($uuidColumnType) === 'native';
            $this->setUuidNativeType($enable);
        }

        $this->ignoreSizeOnIntegerTypes = $mysqlConfig['ignoreSizeOnIntegerTypes'];
    }

    /**
     * @param bool $enable
     *
     * @return void
     */
    public function setUuidNativeType(bool $enable): void
    {
        $this->useUuidNativeType = $enable;
    }

    /**
     * @param string $tableEngineKeyword
     *
     * @return void
     */
    public function setTableEngineKeyword(string $tableEngineKeyword): void
    {
        $this->tableEngineKeyword = $tableEngineKeyword;
    }

    /**
     * @return string
     */
    public function getTableEngineKeyword(): string
    {
        return $this->tableEngineKeyword;
    }

    /**
     * @param string $defaultTableEngine
     *
     * @return void
     */
    public function setDefaultTableEngine(string $defaultTableEngine): void
    {
        $this->defaultTableEngine = $defaultTableEngine;
    }

    /**
     * @return string
     */
    public function getDefaultTableEngine(): string
    {
        return $this->defaultTableEngine;
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
            => 'AUTO_INCREMENT',
            IdMethod::NO_ID_METHOD,
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
        return 64;
    }

    /**
     * @return bool
     */
    #[\Override]
    public function supportsNativeDeleteTrigger(): bool
    {
        return strtolower($this->getDefaultTableEngine()) === 'innodb';
    }

    /**
     * @return bool
     */
    #[\Override]
    public function supportsIndexSize(): bool
    {
        return true;
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return bool
     */
    public function supportsForeignKeys(Table $table): bool
    {
        $vendorSpecific = $table->getVendorInfoForType('mysql');
        if ($vendorSpecific->hasParameter('Type')) {
            $mysqlTableType = $vendorSpecific->getParameter('Type');
        } elseif ($vendorSpecific->hasParameter('Engine')) {
            $mysqlTableType = $vendorSpecific->getParameter('Engine');
        } else {
            $mysqlTableType = $this->getDefaultTableEngine();
        }

        return strtolower($mysqlTableType) === 'innodb';
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
            $ret .= $this->buildCommentBlockDdl($table->getName());
            $ret .= $this->buildDropTableDdl($table);
            $ret .= $this->buildAddTableDdl($table);
        }
        if ($ret) {
            $ret = $this->buildBeginDdl() . $ret . $this->buildEndDdl();
        }

        return $ret;
    }

    /**
     * @return string
     */
    #[\Override]
    public function buildBeginDdl(): string
    {
        return "
# This is a fix for InnoDB in MySQL >= 4.1.x
# It \"suspends judgement\" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;
";
    }

    /**
     * @return string
     */
    #[\Override]
    public function buildEndDdl(): string
    {
        return "
# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
";
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

        $keys = $table->getPrimaryKey();

        //MySQL throws an 'Incorrect table definition; there can be only one auto column and it must be defined as a key'
        //if the primary key consists of multiple columns and if the first is not the autoIncrement one. So
        //this push the autoIncrement column to the first position if its not already.
        $autoIncrementColumn = $table->getAutoIncrementPrimaryKey();
        if ($autoIncrementColumn && $keys[0] != $autoIncrementColumn) {
            $idx = array_search($autoIncrementColumn, $keys);
            if ($idx !== false) {
                unset($keys[$idx]);
                array_unshift($keys, $autoIncrementColumn);
            }
        }

        return 'PRIMARY KEY (' . $this->buildColumnListDdl($keys) . ')';
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    #[\Override]
    public function buildAddTableDdl(Table $table): string
    {
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

        foreach ($table->getIndices() as $index) {
            $lines[] = $this->buildIndexDdl($index);
        }

        if ($this->supportsForeignKeys($table)) {
            foreach ($table->getForeignKeys() as $foreignKey) {
                if ($foreignKey->isSkipSql() || $foreignKey->isPolymorphic()) {
                    continue;
                }
                $lines[] = str_replace("\n    ", "\n        ", $this->buildForeignKeyDdl($foreignKey));
            }
        }

        $vendorSpecific = $table->getVendorInfoForType('mysql');
        if ($vendorSpecific->hasParameter('Type')) {
            $mysqlTableType = $vendorSpecific->getParameter('Type');
        } elseif ($vendorSpecific->hasParameter('Engine')) {
            $mysqlTableType = $vendorSpecific->getParameter('Engine');
        } else {
            $mysqlTableType = $this->getDefaultTableEngine();
        }

        $tableOptions = $this->getTableOptions($table);

        if ($table->getDescription()) {
            $tableOptions[] = 'COMMENT=' . $this->quote($table->getDescription());
        }

        $quotedTableName = $this->quoteIdentifier($table->getName());
        $tableDefinition = implode(",\n    ", $lines);
        $tableEngineKeyword = $this->getTableEngineKeyword();
        $tableOptionsSuffix = $tableOptions ? ' ' . implode(' ', $tableOptions) : '';

        return "
CREATE TABLE {$quotedTableName}
(
    {$tableDefinition}
) {$tableEngineKeyword}={$mysqlTableType}{$tableOptionsSuffix};
";
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return array<string>
     */
    protected function getTableOptions(Table $table): array
    {
        $vi = $table->getVendorInfoForType('mysql');
        $tableOptions = [];
        // List of supported table options
        // see http://dev.mysql.com/doc/refman/5.5/en/create-table.html
        $supportedOptions = [
            'AutoIncrement' => 'AUTO_INCREMENT',
            'AvgRowLength' => 'AVG_ROW_LENGTH',
            'Charset' => 'CHARACTER SET',
            'Checksum' => 'CHECKSUM',
            'Collate' => 'COLLATE',
            'Connection' => 'CONNECTION',
            'DataDirectory' => 'DATA DIRECTORY',
            'Delay_key_write' => 'DELAY_KEY_WRITE',
            'DelayKeyWrite' => 'DELAY_KEY_WRITE',
            'IndexDirectory' => 'INDEX DIRECTORY',
            'InsertMethod' => 'INSERT_METHOD',
            'KeyBlockSize' => 'KEY_BLOCK_SIZE',
            'MaxRows' => 'MAX_ROWS',
            'MinRows' => 'MIN_ROWS',
            'Pack_Keys' => 'PACK_KEYS',
            'PackKeys' => 'PACK_KEYS',
            'RowFormat' => 'ROW_FORMAT',
            'Union' => 'UNION',
        ];

        $noQuotedValue = array_flip([
            'InsertMethod',
            'Pack_Keys',
            'PackKeys',
            'RowFormat',
        ]);

        foreach ($supportedOptions as $name => $sqlName) {
            $parameterValue = null;

            if ($vi->hasParameter($name)) {
                $parameterValue = $vi->getParameter($name);
            } elseif ($vi->hasParameter($sqlName)) {
                $parameterValue = $vi->getParameter($sqlName);
            }

            // if we have a param value, then parse it out
            if ($parameterValue !== null) {
                // if the value is numeric or is parameter is in $noQuotedValue, then there is no need for quotes
                if (!is_numeric($parameterValue) && !isset($noQuotedValue[$name])) {
                    $parameterValue = $this->quote($parameterValue);
                }

                $tableOptions[] = "{$sqlName}={$parameterValue}";
            }
        }

        return $tableOptions;
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    #[\Override]
    public function buildDropTableDdl(Table $table): string
    {
        $tableName = $this->quoteIdentifier($table->getName());

        return "\nDROP TABLE IF EXISTS $tableName;\n";
    }

    /**
     * @param \Propel\Generator\Model\Column $col
     *
     * @throws \Propel\Generator\Exception\EngineException
     *
     * @return string
     */
    #[\Override]
    public function buildColumnDdl(Column $col): string
    {
        $typeMapping = $col->getTypeMapping();
        $sqlType = $col->resolveSqlTypeName();
        $notNullString = $this->getNullString($col->isNotNull());
        $defaultSetting = $this->buildColumnDefaultValueDdl($col);

        // Special handling of TIMESTAMP/DATETIME types ...
        // See: http://propel.phpdb.org/trac/ticket/538
        if ($sqlType === 'DATETIME') {
            $def = $typeMapping->getDefaultValue();
            if ($def && $def->isExpression()) {
                // DATETIME values can only have constant expressions
                $sqlType = 'TIMESTAMP';
            }
        } elseif ($sqlType === 'DATE') {
            $def = $typeMapping->getDefaultValue();
            if ($def && $def->isExpression()) {
                throw new EngineException('DATE columns cannot have default *expressions* in MySQL.');
            }
        } elseif ($sqlType === 'BLOB') {
            if ($typeMapping->getDefaultValue()) {
                throw new EngineException('BLOB columns cannot have DEFAULT values in MySQL.');
            }
        }

        $ddl = [$this->quoteIdentifier($col->getName())];
        $ddl[] = $this->getSqlTypeExpression($col);

        $colinfo = $col->getVendorInfoForType($this->getDatabaseType());
        if ($colinfo->hasParameter('Unsigned')) {
            $unsigned = $colinfo->getParameter('Unsigned');
            switch (strtoupper($unsigned)) {
                case 'FALSE':
                    break;
                case 'TRUE':
                    $ddl[] = 'UNSIGNED';

                    break;
                default:
                    throw new EngineException('Unexpected value "' . $unsigned . '" for MySQL vendor column parameter "Unsigned", expecting "true" or "false".');
            }
        }

        if ($colinfo->hasParameter('Charset')) {
            $ddl[] = 'CHARACTER SET ' . $this->quote($colinfo->getParameter('Charset'));
        }

        $collation = $colinfo->getParameter('Collation') ?? $colinfo->getParameter('Collate');
        if ($collation) {
            $ddl[] = 'COLLATE ' . $this->quote($collation);
        }

        if ($sqlType === 'TIMESTAMP') {
            if ($notNullString === '') {
                $notNullString = 'NULL';
            }
            if ($defaultSetting === '' && $notNullString === 'NOT NULL') {
                $defaultSetting = 'DEFAULT CURRENT_TIMESTAMP';
            }
            $ddl[] = $notNullString;
            if ($defaultSetting) {
                $ddl[] = $defaultSetting;
            }
        } else {
            if ($defaultSetting) {
                $ddl[] = $defaultSetting;
            }
            if ($notNullString) {
                $ddl[] = $notNullString;
            }
        }

        $autoIncrement = $col->buildAutoIncrementString();
        if ($autoIncrement) {
            $ddl[] = $autoIncrement;
        }

        if ($col->getDescription()) {
            $ddl[] = 'COMMENT ' . $this->quote($col->getDescription());
        }

        return implode(' ', $ddl);
    }

    /**
     * Returns the SQL type as a string.
     *
     * @see TypeMapping::getSqlType()
     *
     * @param \Propel\Generator\Model\Column $column
     *
     * @return string
     */
    public function getSqlTypeExpression(Column $column): string
    {
        $sqlType = $column->resolveSqlTypeName();
        $hasSize = $this->hasSize($sqlType) && $column->isDefaultSqlType($this);

        return (!$hasSize) ? $sqlType : $sqlType . $column->getSizeDefinition();
    }

    /**
     * @param \Propel\Generator\Model\Column $fromColumn
     * @param \Propel\Generator\Model\Column $toColumn
     *
     * @return string
     */
    protected function getChangeColumnToUuidBinaryType(Column $fromColumn, Column $toColumn): string
    {
        return MysqlUuidMigrationBuilder::create($this)->buildMigration($fromColumn, $toColumn, true);
    }

    /**
     * @param \Propel\Generator\Model\Column $fromColumn
     * @param \Propel\Generator\Model\Column $toColumn
     *
     * @return string
     */
    protected function getChangeColumnFromUuidBinaryType(Column $fromColumn, Column $toColumn): string
    {
        return MysqlUuidMigrationBuilder::create($this)->buildMigration($fromColumn, $toColumn, false);
    }

    /**
     * Creates a comma-separated list of column names for the index.
     * For MySQL unique indexes there is the option of specifying size, so we cannot simply use
     * the getColumnsList() method.
     *
     * @param \Propel\Generator\Model\Index $index
     *
     * @return string
     */
    protected function buildIndexColumnListDdl(Index $index): string
    {
        $list = [];
        foreach ($index->getColumns() as $col) {
            $size = $index->hasColumnSize($col) ? '(' . $index->getColumnSize($col) . ')' : '';
            $list[] = $this->quoteIdentifier($col) . $size;
        }

        return implode(', ', $list);
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    #[\Override]
    public function buildDropPrimaryKeyDdl(Table $table): string
    {
        if (!$table->hasPrimaryKey()) {
            return '';
        }

        $tableName = $this->quoteIdentifier($table->getName());

        return "\nALTER TABLE $tableName DROP PRIMARY KEY;\n";
    }

    /**
     * @param \Propel\Generator\Model\Index $index
     *
     * @return string
     */
    #[\Override]
    public function buildAddIndexDdl(Index $index): string
    {
        $indexType = $this->getIndexType($index);
        $indexName = $this->quoteIdentifier($index->getName());
        $tableName = $this->quoteIdentifier($index->getTable()->getName());
        $columnList = $this->buildIndexColumnListDdl($index);

        return "\nCREATE {$indexType}INDEX $indexName ON $tableName ($columnList);\n";
    }

    /**
     * @param \Propel\Generator\Model\Index $index
     *
     * @return string
     */
    #[\Override]
    public function buildDropIndexDdl(Index $index): string
    {
        $indexName = $this->quoteIdentifier($index->getName());
        $tableName = $this->quoteIdentifier($index->getTable()->getName());

        return "\nDROP INDEX $indexName ON $tableName;\n";
    }

    /**
     * @param \Propel\Generator\Model\Index $index
     *
     * @return string
     */
    #[\Override]
    public function buildIndexDdl(Index $index): string
    {
        $indexType = $this->getIndexType($index);
        $indexName = $this->quoteIdentifier($index->getName());
        $columnList = $this->buildIndexColumnListDdl($index);

        return "{$indexType}INDEX $indexName ($columnList)";
    }

    /**
     * @param \Propel\Generator\Model\Index $index
     *
     * @return string
     */
    protected function getIndexType(Index $index): string
    {
        $type = '';
        $vendorInfo = $index->getVendorInfoForType($this->getDatabaseType());
        if ($vendorInfo->getParameter('Index_type')) {
            $type = $vendorInfo->getParameter('Index_type') . ' ';
        } elseif ($index->isUnique()) {
            $type = 'UNIQUE ';
        }

        return $type;
    }

    /**
     * @param \Propel\Generator\Model\Unique $unique
     *
     * @return string
     */
    #[\Override]
    public function buildUniqueDdl(Unique $unique): string
    {
        $uniqueName = $this->quoteIdentifier($unique->getName());
        $columnList = $this->buildIndexColumnListDdl($unique);

        return "UNIQUE INDEX $uniqueName ($columnList)";
    }

    /**
     * @param \Propel\Generator\Model\ForeignKey $fk
     *
     * @return string
     */
    #[\Override]
    public function buildAddForeignKeyDdl(ForeignKey $fk): string
    {
        if ($this->supportsForeignKeys($fk->getTable())) {
            return parent::buildAddForeignKeyDdl($fk);
        }

        return '';
    }

    /**
     * @param \Propel\Generator\Model\ForeignKey $fk
     *
     * @return string
     */
    #[\Override]
    public function buildForeignKeyDdl(ForeignKey $fk): string
    {
        return $this->supportsForeignKeys($fk->getTable())
            ? parent::buildForeignKeyDdl($fk)
            : '';
    }

    /**
     * @param \Propel\Generator\Model\ForeignKey $fk
     *
     * @return string
     */
    #[\Override]
    public function buildDropForeignKeyDdl(ForeignKey $fk): string
    {
        if (!$this->supportsForeignKeys($fk->getTable()) || $fk->isSkipSql() || $fk->isPolymorphic()) {
            return '';
        }
        $tableName = $this->quoteIdentifier($fk->getTable()->getName());
        $fkName = $this->quoteIdentifier($fk->getName());

        return "\nALTER TABLE $tableName DROP FOREIGN KEY $fkName;\n";
    }

    /**
     * @param \Propel\Generator\Model\Diff\DatabaseDiff $databaseDiff
     *
     * @return string
     */
    #[\Override]
    public function buildModifyDatabaseDdl(DatabaseDiff $databaseDiff): string
    {
        $ret = '';

        foreach ($databaseDiff->getRemovedTables() as $table) {
            $ret .= $this->buildDropTableDdl($table);
        }

        foreach ($databaseDiff->getRenamedTables() as $fromTableName => $toTableName) {
            $ret .= $this->buildRenameTableDdl($fromTableName, $toTableName);
        }

        foreach ($databaseDiff->getModifiedTables() as $tableDiff) {
            $ret .= $this->buildModifyTableDdl($tableDiff);
        }

        foreach ($databaseDiff->getAddedTables() as $table) {
            $ret .= $this->buildAddTableDdl($table);
        }

        if ($ret) {
            $ret = $this->buildBeginDdl() . $ret . $this->buildEndDdl();
        }

        return $ret;
    }

    /**
     * @param string $currentTableName
     * @param string $newTableName
     *
     * @return string
     */
    #[\Override]
    public function buildRenameTableDdl(string $currentTableName, string $newTableName): string
    {
        $currentTableName = $this->quoteIdentifier($currentTableName);
        $newTableName = $this->quoteIdentifier($newTableName);

        return "\nRENAME TABLE $currentTableName TO $newTableName;\n";
    }

    /**
     * @param \Propel\Generator\Model\Column $column
     *
     * @return string
     */
    #[\Override]
    public function buildRemoveColumnDdl(Column $column): string
    {
        $tableName = $this->quoteIdentifier($column->getTable()->getName());
        $columnName = $this->quoteIdentifier($column->getName());

        return "\nALTER TABLE $tableName DROP $columnName;\n";
    }

    /**
     * Builds the DDL SQL to rename a column
     *
     * @param \Propel\Generator\Model\Column $fromColumn
     * @param \Propel\Generator\Model\Column $toColumn
     *
     * @return string
     */
    #[\Override]
    public function buildRenameColumnDdl(Column $fromColumn, Column $toColumn): string
    {
        return $this->buildChangeColumnDdl($fromColumn, $toColumn);
    }

    /**
     * Builds the DDL SQL to modify a column
     *
     * @param \Propel\Generator\Model\Diff\ColumnDiff $columnDiff
     *
     * @return string
     */
    #[\Override]
    public function buildModifyColumnDdl(ColumnDiff $columnDiff): string
    {
        $fromColumn = $columnDiff->getFromColumn();
        $toColumn = $columnDiff->getToColumn();

        if ($fromColumn->isTextType() && $toColumn->isUuidBinaryType()) {
            return $this->getChangeColumnToUuidBinaryType($fromColumn, $toColumn);
        }

        // binary column from database does not know it is a UUID column
        $fromBinaryColumn = in_array($fromColumn->getColumnType(), [ColumnType::BINARY, ColumnType::UUID_BINARY], true);
        if ($fromBinaryColumn && $toColumn->isTextType() && $toColumn->isContent('UUID')) {
            return $this->getChangeColumnFromUuidBinaryType($fromColumn, $toColumn);
        }

        return $this->buildChangeColumnDdl($fromColumn, $toColumn);
    }

    /**
     * Builds the DDL SQL to change a column
     *
     * @param \Propel\Generator\Model\Column $fromColumn
     * @param \Propel\Generator\Model\Column $toColumn
     *
     * @return string
     */
    public function buildChangeColumnDdl(Column $fromColumn, Column $toColumn): string
    {
        $tableName = $this->quoteIdentifier($fromColumn->getTable()->getName());
        $columnName = $this->quoteIdentifier($fromColumn->getName());
        $columnDefinition = $this->buildColumnDdl($toColumn);

        return "\nALTER TABLE $tableName CHANGE $columnName $columnDefinition;\n";
    }

    /**
     * Builds the DDL SQL to modify a list of columns
     *
     * @param array<\Propel\Generator\Model\Diff\ColumnDiff> $columnDiffs
     *
     * @return string
     */
    #[\Override]
    public function buildModifyColumnsDdl(array $columnDiffs): string
    {
        $modifyColumnStatements = array_map([$this, 'buildModifyColumnDdl'], $columnDiffs);

        return implode('', $modifyColumnStatements);
    }

    /**
     * Builds the DDL SQL to add a column
     *
     * @param \Propel\Generator\Model\Column $column
     *
     * @return string
     */
    #[\Override]
    public function buildAddColumnDdl(Column $column): string
    {
        $tableColumns = $column->getTable()->getColumns();
        $index = $column->getPosition(); // 1-based position
        $insertPositionDDL = $index > 1 ? 'AFTER ' . $this->quoteIdentifier($tableColumns[$index - 2]->getName()) : 'FIRST';

        $tableName = $this->quoteIdentifier($column->getTableName());
        $columnDdl = $this->buildColumnDdl($column);

        return "\nALTER TABLE $tableName ADD $columnDdl $insertPositionDDL;\n";
    }

    /**
     * Builds the DDL SQL to add a list of columns
     *
     * @param array<\Propel\Generator\Model\Column> $columns
     *
     * @return string
     */
    #[\Override]
    public function buildAddColumnsDdl(array $columns): string
    {
        return $this->mapConcat([$this, 'buildAddColumnDdl'], $columns);
    }

    /**
     * @see Platform::supportsSchemas()
     *
     * @return bool
     */
    #[\Override]
    public function supportsSchemas(): bool
    {
        return true;
    }

    /**
     * @param string $sqlType
     *
     * @return bool
     */
    #[\Override]
    public function hasSize(string $sqlType): bool
    {
        $unSizedTypes = [
            'MEDIUMTEXT',
            'LONGTEXT',
            'BLOB',
            'MEDIUMBLOB',
            'LONGBLOB',
        ];

        if ($this->ignoreSizeOnIntegerTypes) {
            array_push(
                $unSizedTypes,
                'BIGINT',
                'INTEGER',
                'SMALLINT',
                'TINYINT',
            );
        }

        return !in_array($sqlType, $unSizedTypes, true);
    }

    /**
     * @return array<int>
     */
    #[\Override]
    public function getDefaultTypeSizes(): array
    {
        return [
            'char' => 1,
            'tinyint' => 4,
            'smallint' => 6,
            'int' => 11,
            'bigint' => 20,
            'decimal' => 10,
        ];
    }

    /**
     * Escape the string for RDBMS.
     *
     * @param string $text
     *
     * @return string
     */
    #[\Override]
    public function disconnectedEscapeText(string $text): string
    {
        return addslashes($text);
    }

    /**
     * {@inheritDoc}
     *
     * MySQL documentation says that identifiers cannot contain '.'. Thus it
     * should be safe to split the string by '.' and quote each part individually
     * to allow for a <schema>.<table> or <table>.<column> syntax.
     *
     * @param string $text the identifier
     *
     * @return string the quoted identifier
     */
    #[\Override]
    public function doQuoting(string $text): string
    {
        return '`' . strtr($text, ['.' => '`.`']) . '`';
    }

    /**
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
        // FIXME - This is a temporary hack to get around apparent bugs w/ PDO+MYSQL
        // See http://pecl.php.net/bugs/bug.php?id=9919
        if ($column->getPdoType() === PDO::PARAM_BOOL) {
            return "\n{$tab}\$stmt->bindValue($identifier, (int){$columnValueAccessor}, PDO::PARAM_INT);";
        }

        return parent::getColumnBindingPHP($column, $identifier, $columnValueAccessor, $tab);
    }

    /**
     * Get the default On Delete behavior for foreign keys when not explicity set.
     *
     * @return string
     */
    #[\Override]
    public function getDefaultForeignKeyOnDeleteBehavior(): string
    {
        $majorVersion = $this->getMajorServerVersionNumber();

        return ($majorVersion && $majorVersion >= 8 && !$this->isMariaDB()) ? ForeignKey::NOACTION : ForeignKey::RESTRICT;
    }

    /**
     * Get the default On Update behavior for foreign keys when not explicity set.
     *
     * @return string
     */
    #[\Override]
    public function getDefaultForeignKeyOnUpdateBehavior(): string
    {
        $majorVersion = $this->getMajorServerVersionNumber();

        return ($majorVersion && $majorVersion >= 8 && !$this->isMariaDB()) ? ForeignKey::NOACTION : ForeignKey::RESTRICT;
    }

    /**
     * Get the server version of the platform
     *
     * @return string|null
     */
    protected function getServerVersion(): ?string
    {
        if (!$this->serverVersion && $this->con) {
            $this->serverVersion = $this->con->getAttribute(PDO::ATTR_SERVER_VERSION);
        }

        return $this->serverVersion;
    }

    /**
     * Get the extracted major server version number
     *
     * @return int|null
     */
    protected function getMajorServerVersionNumber(): ?int
    {
        $serverVersion = $this->getServerVersion();
        if (!$serverVersion) {
            return null;
        }
        $dotPos = strpos($serverVersion, '.');
        if ($dotPos === false) {
            return null;
        }

        return (int)substr($serverVersion, 0, $dotPos - 1);
    }

    /**
     * Whether the platform is running on a MariaDB server
     *
     * @return bool
     */
    protected function isMariaDB(): bool
    {
        $serverVersion = $this->getServerVersion() ?? '';

        return (stripos($serverVersion, 'mariadb') !== false);
    }
}
