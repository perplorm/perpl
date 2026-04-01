<?php

declare(strict_types = 1);

namespace Propel\Generator\Platform;

use Propel\Generator\Model\Database;
use Propel\Generator\Model\Domain;
use Propel\Generator\Model\ForeignKey;
use Propel\Generator\Model\PropelTypes;
use Propel\Generator\Model\Table;
use Propel\Generator\Model\Unique;
use function in_array;
use function sprintf;
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
     * Initializes db specific domain mapping.
     *
     * @return void
     */
    #[\Override]
    protected function initialize(): void
    {
        parent::initialize();

        $this->setSchemaDomainMapping(new Domain(PropelTypes::INTEGER, 'INT'));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::BOOLEAN, 'INT'));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::DOUBLE, 'FLOAT'));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::LONGVARCHAR, 'VARCHAR(MAX)'));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::CLOB, 'VARCHAR(MAX)'));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::DATE, 'DATE'));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::DATETIME, 'DATETIME2'));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::BU_DATE, 'DATE'));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::TIME, 'TIME'));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::TIMESTAMP, 'DATETIME2'));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::BU_TIMESTAMP, 'DATETIME2'));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::BINARY, 'BINARY(7132)'));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::VARBINARY, 'VARBINARY(MAX)'));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::LONGVARBINARY, 'VARBINARY(MAX)'));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::BLOB, 'VARBINARY(MAX)'));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::OBJECT, 'VARBINARY(MAX)'));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::PHP_ARRAY, 'VARCHAR(MAX)'));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::UUID, 'UNIQUEIDENTIFIER'));
        $this->setSchemaDomainMapping(new Domain(PropelTypes::UUID_BINARY, 'BINARY(16)'));

        $this->setSetTypesMapping(false);
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
    public function getAddTablesDDL(Database $database): string
    {
        $ret = $this->getBeginDDL();
        foreach ($database->getTablesForSql() as $table) {
            $this->normalizeTable($table);
        }
        foreach ($database->getTablesForSql() as $table) {
            $ret .= $this->getCommentBlockDDL($table->getName());
            $ret .= $this->getDropTableDDL($table);
            $ret .= $this->getAddTableDDL($table);
            $ret .= $this->getAddIndicesDDL($table);
        }
        foreach ($database->getTablesForSql() as $table) {
            $ret .= $this->getAddForeignKeysDDL($table);
        }
        $ret .= $this->getEndDDL();

        return $ret;
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    #[\Override]
    public function getDropTableDDL(Table $table): string
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
    public function getPrimaryKeyDDL(Table $table): string
    {
        if ($table->hasPrimaryKey()) {
            $pattern = 'CONSTRAINT %s PRIMARY KEY (%s)';

            return sprintf(
                $pattern,
                $this->quoteIdentifier($this->getPrimaryKeyName($table)),
                $this->getColumnListDDL($table->getPrimaryKey()),
            );
        }

        return '';
    }

    /**
     * @param \Propel\Generator\Model\ForeignKey $fk
     *
     * @return string
     */
    #[\Override]
    public function getAddForeignKeyDDL(ForeignKey $fk): string
    {
        if ($fk->isSkipSql() || $fk->isPolymorphic()) {
            return '';
        }

        $pattern = "
BEGIN
ALTER TABLE %s ADD %s
END
;
";

        return sprintf(
            $pattern,
            $this->quoteIdentifier($fk->getTable()->getName()),
            $this->getForeignKeyDDL($fk),
        );
    }

    /**
     * Builds the DDL SQL for a Unique constraint object. MS SQL Server CONTRAINT specific
     *
     * @param \Propel\Generator\Model\Unique $unique
     *
     * @return string
     */
    #[\Override]
    public function getUniqueDDL(Unique $unique): string
    {
        $pattern = 'CONSTRAINT %s UNIQUE NONCLUSTERED (%s) ON [PRIMARY]';

        return sprintf(
            $pattern,
            $this->quoteIdentifier($unique->getName()),
            $this->getColumnListDDL($unique->getColumnObjects()),
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
        if ($fk->isSkipSql() || $fk->isPolymorphic()) {
            return '';
        }

        $pattern = 'CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s)';
        $script = sprintf(
            $pattern,
            $this->quoteIdentifier($fk->getName()),
            $this->getColumnListDDL($fk->getLocalColumnObjects()),
            $this->quoteIdentifier($fk->getForeignTableName()),
            $this->getColumnListDDL($fk->getForeignColumnObjects()),
        );
        if ($fk->hasOnUpdate() && $fk->getOnUpdate() != ForeignKey::SETNULL) {
            $script .= ' ON UPDATE ' . $fk->getOnUpdate();
        }
        if ($fk->hasOnDelete() && $fk->getOnDelete() != ForeignKey::SETNULL) {
            $script .= ' ON DELETE ' . $fk->getOnDelete();
        }

        return $script;
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
