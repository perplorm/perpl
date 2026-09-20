<?php

declare(strict_types = 1);

namespace Propel\Generator\Platform;

use Propel\Generator\Model\Column;
use Propel\Generator\Model\Database;
use Propel\Generator\Model\Datatype\ColumnType;
use Propel\Generator\Model\ForeignKey;
use Propel\Generator\Model\IdMethod;
use Propel\Generator\Model\Table;
use Propel\Generator\Model\Unique;
use function in_array;
use function strtr;

/**
 * MS SQL PlatformInterface implementation.
 */
class MssqlPlatform extends DefaultPlatform
{
    /**
     * @var int
     */
    protected static $dropCount = 0;

    /**
     * @param \Propel\Generator\Model\Datatype\ColumnType $type
     *
     * @return string|null
     */
    #[\Override]
    protected function resolveSqlType(ColumnType $type): string|null
    {
        return match ($type) {
            ColumnType::INTEGER,
            ColumnType::BOOLEAN,
            => 'INT',
            ColumnType::DOUBLE => 'FLOAT',
            ColumnType::LONGVARCHAR,
            ColumnType::CLOB,
            ColumnType::ARRAY,
            => 'VARCHAR(MAX)',
            ColumnType::DATE,
            ColumnType::BU_DATE,
            => 'DATE',
            ColumnType::DATETIME,
            ColumnType::TIMESTAMP,
            ColumnType::BU_TIMESTAMP,
            => 'DATETIME2',
            ColumnType::TIME => 'TIME',
            ColumnType::BINARY => 'BINARY(7132)',
            ColumnType::VARBINARY,
            ColumnType::LONGVARBINARY,
            ColumnType::BLOB,
            ColumnType::OBJECT,
            => 'VARBINARY(MAX)',
            ColumnType::UUID => 'UNIQUEIDENTIFIER',
            ColumnType::UUID_BINARY => 'BINARY(16)',
            default => parent::resolveSqlType($type)
        };
    }

    /**
     * @return int
     */
    #[\Override]
    public function getMaxColumnNameLength(): int
    {
        return 128;
    }

    /**
     * @param bool $notNull
     *
     * @return string
     */
    #[\Override]
    public function getNullString(bool $notNull): string
    {
        return $notNull ? 'NOT NULL' : 'NULL';
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
     * @return bool
     */
    #[\Override]
    public function supportsInsertNullPk(): bool
    {
        return false;
    }

    /**
     * @return \Propel\Generator\Model\IdMethod
     */
    #[\Override]
    public function getNativeIdMethod(): IdMethod
    {
        return IdMethod::IDENTITY;
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
            IdMethod::IDENTITY,
            => 'IDENTITY(1,1)',
            IdMethod::NO_ID_METHOD,
            => '',
            default => null,
        };
    }

    /**
     * Returns the DDL SQL to add the tables of a database
     * together with index and foreign keys.
     * Since MSSQL always checks it the tables in foreign key definitions exist,
     * the foreign key DDLs are moved after all tables are created
     *
     * @param \Propel\Generator\Model\Database $database
     *
     * @return string
     */
    #[\Override]
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
        }
        foreach ($database->getTablesForSql() as $table) {
            $ret .= $this->buildAddForeignKeysDdl($table);
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
    public function buildDropTableDdl(Table $table): string
    {
        $ret = '';
        foreach ($table->getForeignKeys() as $fk) {
            $ret .= "
IF EXISTS (SELECT 1 FROM sysobjects WHERE type ='RI' AND name='" . $fk->getName() . "')
    ALTER TABLE " . $this->quoteIdentifier($table->getName()) . ' DROP CONSTRAINT ' . $this->quoteIdentifier($fk->getName()) . ";
";
        }

        self::$dropCount++;

        $ret .= "
IF EXISTS (SELECT 1 FROM sysobjects WHERE type = 'U' AND name = '" . $table->getName() . "')
BEGIN
    DECLARE @reftable_" . self::$dropCount . ' nvarchar(60), @constraintname_' . self::$dropCount . " nvarchar(60)
    DECLARE refcursor CURSOR FOR
    select reftables.name tablename, cons.name constraintname
        from sysobjects tables,
            sysobjects reftables,
            sysobjects cons,
            sysreferences ref
        where tables.id = ref.rkeyid
            and cons.id = ref.constid
            and reftables.id = ref.fkeyid
            and tables.name = '" . $table->getName() . "'
    OPEN refcursor
    FETCH NEXT from refcursor into @reftable_" . self::$dropCount . ', @constraintname_' . self::$dropCount . "
    while @@FETCH_STATUS = 0
    BEGIN
        exec ('alter table '+@reftable_" . self::$dropCount . "+' drop constraint '+@constraintname_" . self::$dropCount . ")
        FETCH NEXT from refcursor into @reftable_" . self::$dropCount . ', @constraintname_' . self::$dropCount . "
    END
    CLOSE refcursor
    DEALLOCATE refcursor
    DROP TABLE " . $this->quoteIdentifier($table->getName()) . "
END
";

        return $ret;
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
        $tableName = $this->quoteIdentifier($this->getPrimaryKeyName($table));
        $columnList = $this->buildColumnListDdl($table->getPrimaryKey());

        return "CONSTRAINT $tableName PRIMARY KEY ($columnList)";
    }

    /**
     * @param \Propel\Generator\Model\ForeignKey $fk
     *
     * @return string
     */
    #[\Override]
    public function buildAddForeignKeyDdl(ForeignKey $fk): string
    {
        if ($fk->isSkipSql() || $fk->isPolymorphic()) {
            return '';
        }
        $tableName = $this->quoteIdentifier($fk->getTable()->getName());
        $fkDdl = $this->buildForeignKeyDdl($fk);

        return "
BEGIN
ALTER TABLE $tableName ADD $fkDdl
END
;\n";
    }

    /**
     * Builds the DDL SQL for a Unique constraint object. MS SQL Server CONTRAINT specific
     *
     * @param \Propel\Generator\Model\Unique $unique
     *
     * @return string
     */
    #[\Override]
    public function buildUniqueDdl(Unique $unique): string
    {
        $indexName = $this->quoteIdentifier($unique->getName());
        $columnDdl = $this->buildColumnListDdl($unique->getColumnObjects());

        return "CONSTRAINT $indexName UNIQUE NONCLUSTERED ($columnDdl) ON [PRIMARY]";
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
        $fkName = $this->quoteIdentifier($fk->getName());
        $localColumnsList = $this->buildColumnListDdl($fk->getLocalColumnObjects());
        $foreignTableName = $this->quoteIdentifier($fk->getForeignTableName());
        $foreignColumnsList = $this->buildColumnListDdl($fk->getForeignColumnObjects());

        $onUpdate = $fk->hasOnUpdate() && $fk->getOnUpdate() != ForeignKey::SETNULL ? ' ON UPDATE ' . $fk->getOnUpdate() : '';
        $onDelete = $fk->hasOnDelete() && $fk->getOnDelete() != ForeignKey::SETNULL ? ' ON DELETE ' . $fk->getOnDelete() : '';

        return "CONSTRAINT $fkName FOREIGN KEY ($localColumnsList) REFERENCES $foreignTableName ($foreignColumnsList){$onUpdate}{$onDelete}";
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
        $nosize = ['INT', 'TEXT', 'GEOMETRY', 'VARCHAR(MAX)', 'VARBINARY(MAX)', 'SMALLINT', 'DATETIME', 'TINYINT', 'REAL', 'BIGINT'];

        return !(in_array($sqlType, $nosize, true));
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
     * @param bool $withMilliseconds
     *
     * @return string
     */
    #[\Override]
    public function getTimestampFormatter(bool $withMilliseconds = false): string
    {
        return parent::getTimestampFormatter($withMilliseconds);
    }
}
