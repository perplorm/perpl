<?php

declare(strict_types = 1);

namespace Propel\Generator\Platform;

use Propel\Generator\Exception\EngineException;
use Propel\Generator\Model\Column;
use Propel\Generator\Model\Database;
use Propel\Generator\Model\Datatype\ColumnType;
use Propel\Generator\Model\ForeignKey;
use Propel\Generator\Model\IdMethod;
use Propel\Generator\Model\Index;
use Propel\Generator\Model\Table;
use Propel\Generator\Model\TypeMapping;
use Propel\Generator\Model\Unique;
use function count;
use function implode;
use function in_array;
use function is_array;
use function min;
use function sprintf;
use function strlen;
use function substr;

/**
 * Oracle PlatformInterface implementation.
 */
class OraclePlatform extends DefaultPlatform
{
    /**
     * @param \Propel\Generator\Model\Datatype\ColumnType $type
     *
     * @return \Propel\Generator\Model\TypeMapping
     */
    #[\Override]
    protected function resolveColumnTypeMapping(ColumnType $type): TypeMapping
    {
        if ($type === ColumnType::CLOB || $type === ColumnType::CLOB_EMU) {
            return new TypeMapping(ColumnType::CLOB_EMU, 'CLOB'); // sic
        }

        $mapping = parent::resolveColumnTypeMapping($type);

        if (in_array($type, [ColumnType::BOOLEAN_EMU, ColumnType::TINYINT, ColumnType::SMALLINT, ColumnType::BIGINT])) {
            $mapping->setScale(0);
        }

        return $mapping;
    }

    /**
     * @param \Propel\Generator\Model\Datatype\ColumnType $type
     *
     * @return \Propel\Generator\Model\Datatype\ColumnType
     */
    #[\Override]
    protected function resolveColumnTypeAlias(ColumnType $type): ColumnType
    {
        return match ($type) {
            ColumnType::BOOLEAN => ColumnType::BOOLEAN_EMU,
            default => parent::resolveColumnTypeAlias($type)
        };
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
            ColumnType::TINYINT,
            ColumnType::SMALLINT,
            ColumnType::INTEGER,
            ColumnType::BIGINT,
            ColumnType::REAL,
            ColumnType::DECIMAL,
            ColumnType::NUMERIC,
            ColumnType::BOOLEAN_EMU,
            => 'NUMBER',
            ColumnType::DOUBLE => 'FLOAT',
            ColumnType::VARCHAR,
            ColumnType::LONGVARCHAR,
            ColumnType::ARRAY,
            => 'NVARCHAR2',
            ColumnType::TIME,
            ColumnType::DATE,
            => 'DATE',
            ColumnType::DATETIME,
            ColumnType::TIMESTAMP,
            => 'TIMESTAMP',
            ColumnType::BINARY,
            ColumnType::LONGVARBINARY,
            ColumnType::OBJECT
            => 'LONG RAW',
            ColumnType::VARBINARY => 'BLOB',
            ColumnType::UUID => 'UUID',
            ColumnType::UUID_BINARY => 'RAW(16)',
            ColumnType::CLOB_EMU => 'CLOB',
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
            ColumnType::BOOLEAN_EMU => 1,
            ColumnType::TINYINT => 3,
            ColumnType::SMALLINT => 5,
            ColumnType::BIGINT => 20,
            ColumnType::ARRAY,
            ColumnType::LONGVARCHAR
            => 2000,
            default => parent::resolveTypeSize($type)
        };
    }

    /**
     * @return int
     */
    #[\Override]
    public function getMaxColumnNameLength(): int
    {
        return 30;
    }

    /**
     * @return \Propel\Generator\Model\IdMethod
     */
    #[\Override]
    public function getNativeIdMethod(): IdMethod
    {
        return IdMethod::SEQUENCE;
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
            IdMethod::NO_ID_METHOD,
            IdMethod::SEQUENCE,
            => '',
            IdMethod::IDENTITY, // not implemented
            => null,
            default => null,
        };
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
     * @return string
     */
    #[\Override]
    public function buildBeginDdl(): string
    {
        return "
ALTER SESSION SET NLS_DATE_FORMAT='YYYY-MM-DD';
ALTER SESSION SET NLS_TIMESTAMP_FORMAT='YYYY-MM-DD HH24:MI:SS';
";
    }

    /**
     * @param \Propel\Generator\Model\Database $database
     *
     * @return string
     */
    #[\Override]
    public function buildAddTablesDdl(Database $database): string
    {
        $ret = $this->buildBeginDdl();
        foreach ($database->getTablesForSql() as $table) {
            $ret .= $this->buildCommentBlockDdl($table->getName());
            $ret .= $this->buildDropTableDdl($table);
            $ret .= $this->buildAddTableDdl($table);
            $ret .= $this->buildAddIndicesDdl($table);
        }
        $ret2 = '';
        foreach ($database->getTablesForSql() as $table) {
            $ret2 .= $this->buildAddForeignKeysDdl($table);
        }
        if ($ret2) {
            $ret .= $this->buildCommentBlockDdl('Foreign Keys') . $ret2;
        }
        $ret .= $this->buildEndDdl();

        return $ret;
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    #[\Override]
    public function buildAddTableDdl(Table $table): string
    {
        $tableDescription = $table->hasDescription() ? $this->buildCommentLineDdl($table->getDescription()) : '';

        $lines = [];

        foreach ($table->getColumns() as $column) {
            $lines[] = $this->buildColumnDdl($column);
        }

        foreach ($table->getUnices() as $unique) {
            $lines[] = $this->buildUniqueDdl($unique);
        }

        $sep = ",\n    ";

        $pattern = "
%sCREATE TABLE %s
(
    %s
)%s;
";
        $ret = sprintf(
            $pattern,
            $tableDescription,
            $this->quoteIdentifier($table->getName()),
            implode($sep, $lines),
            $this->generateBlockStorage($table),
        );

        $ret .= $this->buildAddPrimaryKeyDdl($table);
        $ret .= $this->buildAddSequencesDdl($table);

        return $ret;
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    #[\Override]
    public function buildAddPrimaryKeyDdl(Table $table): string
    {
        return is_array($table->getPrimaryKey()) && count($table->getPrimaryKey())
            ? parent::buildAddPrimaryKeyDdl($table)
            : '';
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    public function buildAddSequencesDdl(Table $table): string
    {
        $sequenceName = $table->resolveDefaultIdSequenceName();
        if (!$sequenceName) {
            return '';
        }

        $sequenceName = $this->quoteIdentifier($sequenceName);

        return "
CREATE SEQUENCE $sequenceName
    INCREMENT BY 1 START WITH 1 NOMAXVALUE NOCYCLE NOCACHE ORDER;\n";
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
        $ret = "\nDROP TABLE $tableName CASCADE CONSTRAINTS;\n";

        $sequenceName = $table->resolveDefaultIdSequenceName();
        if ($sequenceName) {
            $sequenceName = $this->quoteIdentifier($sequenceName);
            $ret .= "\nDROP SEQUENCE $sequenceName;\n";
        }

        return $ret;
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    #[\Override]
    public function getPrimaryKeyName(Table $table): string
    {
        $tableName = $table->getName();
        // pk constraint name must be 30 chars at most
        $tableName = substr($tableName, 0, min(27, strlen($tableName)));

        return $tableName . '_pk';
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    #[\Override]
    public function buildPrimaryKeyDdl(Table $table): string
    {
        if ($table->hasPrimaryKey()) {
            $pattern = 'CONSTRAINT %s PRIMARY KEY (%s)%s';

            return sprintf(
                $pattern,
                $this->quoteIdentifier($this->getPrimaryKeyName($table)),
                $this->buildColumnListDdl($table->getPrimaryKey()),
                $this->generateBlockStorage($table, true),
            );
        }

        return '';
    }

    /**
     * @param \Propel\Generator\Model\Unique $unique
     *
     * @return string
     */
    #[\Override]
    public function buildUniqueDdl(Unique $unique): string
    {
        return sprintf(
            'CONSTRAINT %s UNIQUE (%s)',
            $this->quoteIdentifier($unique->getName()),
            $this->buildColumnListDdl($unique->getColumnObjects()),
        );
    }

    /**
     * @param \Propel\Generator\Model\ForeignKey $fk
     *
     * @return string
     */
    #[\Override]
    public function buildForeignKeyDdl(ForeignKey $fk): string
    {
        if ($fk->isSkipSql() || $fk->isPolymorphic()) {
            return '';
        }

        $pattern = "CONSTRAINT %s
    FOREIGN KEY (%s) REFERENCES %s (%s)";
        $script = sprintf(
            $pattern,
            $this->quoteIdentifier($fk->getName()),
            $this->buildColumnListDdl($fk->getLocalColumnObjects()),
            $this->quoteIdentifier($fk->getForeignTableName()),
            $this->buildColumnListDdl($fk->getForeignColumnObjects()),
        );
        if ($fk->hasOnDelete()) {
            $script .= "
    ON DELETE " . $fk->getOnDelete();
        }

        return $script;
    }

    /**
     * Whether the underlying PDO driver for this platform returns BLOB columns as streams (instead of strings).
     *
     * @return bool
     */
    #[\Override]
    public function hasStreamBlobImpl(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function doQuoting(string $text): string
    {
        return $text;
    }

    /**
     * @param bool $withMilliseconds
     *
     * @return string
     */
    #[\Override]
    public function getTimestampFormatter(bool $withMilliseconds = false): string
    {
        return parent::getTimestampFormatter($withMilliseconds);
    }

    /**
     * @note While Oracle supports schemas, they're user-based and
     *             are really only good for creating a database layout in
     *             one fell swoop.
     *
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
     * Generate oracle block storage
     *
     * @param \Propel\Generator\Model\Table|\Propel\Generator\Model\Index $object object with vendor parameters
     * @param bool $isPrimaryKey is a primary key vendor part
     *
     * @return string oracle vendor sql part
     */
    public function generateBlockStorage($object, bool $isPrimaryKey = false): string
    {
        $vendorSpecific = $object->getVendorInfoForType('oracle');
        if ($vendorSpecific->isEmpty()) {
            return '';
        }

        if ($isPrimaryKey) {
            $physicalParameters = "\nUSING INDEX\n";
            $prefix = 'PK';
        } else {
            $physicalParameters = "\n";
            $prefix = '';
        }

        if ($vendorSpecific->hasParameter($prefix . 'PCTFree')) {
            $physicalParameters .= 'PCTFREE ' . $vendorSpecific->getParameter($prefix . 'PCTFree') . "\n";
        }
        if ($vendorSpecific->hasParameter($prefix . 'InitTrans')) {
            $physicalParameters .= 'INITRANS ' . $vendorSpecific->getParameter($prefix . 'InitTrans') . "\n";
        }
        if ($vendorSpecific->hasParameter($prefix . 'MinExtents') || $vendorSpecific->hasParameter($prefix . 'MaxExtents') || $vendorSpecific->hasParameter($prefix . 'PCTIncrease')) {
            $physicalParameters .= "STORAGE\n(\n";
            if ($vendorSpecific->hasParameter($prefix . 'MinExtents')) {
                $physicalParameters .= '    MINEXTENTS ' . $vendorSpecific->getParameter($prefix . 'MinExtents') . "\n";
            }
            if ($vendorSpecific->hasParameter($prefix . 'MaxExtents')) {
                $physicalParameters .= '    MAXEXTENTS ' . $vendorSpecific->getParameter($prefix . 'MaxExtents') . "\n";
            }
            if ($vendorSpecific->hasParameter($prefix . 'PCTIncrease')) {
                $physicalParameters .= '    PCTINCREASE ' . $vendorSpecific->getParameter($prefix . 'PCTIncrease') . "\n";
            }
            $physicalParameters .= ")\n";
        }
        if ($vendorSpecific->hasParameter($prefix . 'Tablespace')) {
            $physicalParameters .= 'TABLESPACE ' . $vendorSpecific->getParameter($prefix . 'Tablespace');
        }

        return $physicalParameters;
    }

    /**
     * Builds the DDL SQL to add an Index.
     *
     * @param \Propel\Generator\Model\Index $index
     *
     * @return string
     */
    #[\Override]
    public function buildAddIndexDdl(Index $index): string
    {
        // don't create index form primary key
        if ($this->getPrimaryKeyName($index->getTable()) == $this->quoteIdentifier($index->getName())) {
            return '';
        }

        $pattern = "\nCREATE %sINDEX %s ON %s (%s)%s;\n";

        return sprintf(
            $pattern,
            $index->isUnique() ? 'UNIQUE ' : '',
            $this->quoteIdentifier($index->getName()),
            $this->quoteIdentifier($index->getTable()->getName()),
            $this->buildColumnListDdl($index->getColumnObjects()),
            $this->generateBlockStorage($index),
        );
    }

    /**
     * Get the PHP snippet for binding a value to a column.
     * Warning: duplicates logic from OracleAdapter::bindValue().
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
        if ($column->getColumnType() === ColumnType::CLOB_EMU) {
            return sprintf(
                "%s\$stmt->bindParam(%s, %s, %d, strlen(%s));\n",
                $tab,
                $identifier,
                $columnValueAccessor,
                $column->getColumnType()->toPdoConstantName(),
                $columnValueAccessor,
            );
        }

        return parent::getColumnBindingPHP($column, $identifier, $columnValueAccessor, $tab);
    }

    /**
     * Get the PHP snippet for getting a Pk from the database.
     * Warning: duplicates logic from OracleAdapter::getId().
     * Any code modification here must be ported there.
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
    #[\Override]
    public function buildLoadNextSequenceValueStatement(
        string $targetVariable,
        string $connectionVariableName = '$con',
        string|null $sequenceName = null,
        string $indent = '            ',
        string|null $phpType = null
    ): string {
        if (!$sequenceName) {
            throw new EngineException('Oracle needs a sequence name to fetch primary keys');
        }
        $typecast = $phpType ? "($phpType)" : '';

        return "
{$indent}\$dataFetcher = {$connectionVariableName}->query('SELECT {$sequenceName}.nextval FROM dual');
{$indent}$targetVariable = {$typecast}\$dataFetcher->fetchColumn();";
    }
}
