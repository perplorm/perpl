<?php

declare(strict_types = 1);

namespace Propel\Generator\Platform;

use Propel\Generator\Config\AbstractGeneratorConfig;
use Propel\Generator\Model\Column;
use Propel\Generator\Model\Datatype\ColumnType;
use Propel\Generator\Model\IdMethod;
use Propel\Generator\Model\Table;
use Propel\Generator\Model\TypeMapping;
use Propel\Runtime\Connection\ConnectionInterface;

/**
 * Interface for RDBMS platform specific behaviour.
 */
interface PlatformInterface
{
    /**
     * Sets a database connection to use (for quoting, etc.).
     *
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con The database connection to use in this Platform class.
     *
     * @return void
     */
    public function setConnection(?ConnectionInterface $con = null): void;

    /**
     * Returns the database connection to use for this Platform class.
     *
     * @return \Propel\Runtime\Connection\ConnectionInterface|null The database connection or NULL if none has been set.
     */
    public function getConnection(): ?ConnectionInterface;

    /**
     * Sets the GeneratorConfigInterface which contains any generator build properties.
     *
     * @param \Propel\Generator\Config\AbstractGeneratorConfig $generatorConfig
     *
     * @return void
     */
    public function setGeneratorConfig(AbstractGeneratorConfig $generatorConfig): void;

    /**
     * Returns the short name of the database type that this platform represents.
     * For example MysqlPlatform->getDatabaseType() returns 'mysql'.
     *
     * @return string
     */
    public function getDatabaseType(): string;

    /**
     * Returns the native IdMethod
     *
     * @return \Propel\Generator\Model\IdMethod
     */
    public function getNativeIdMethod(): IdMethod;

    /**
     * Returns the max column length supported by the db.
     *
     * @return int
     */
    public function getMaxColumnNameLength(): int;

    /**
     * Returns the db specific type mapping for a column type.
     *
     * @param \Propel\Generator\Model\Datatype\ColumnType $type
     *
     * @return \Propel\Generator\Model\TypeMapping
     */
    public function getColumnTypeMapping(ColumnType $type): TypeMapping;

    /**
     * Returns the RDBMS-specific SQL fragment for <code>NULL</code>
     *   or <code>NOT NULL</code>.
     *
     * @param bool $notNull
     *
     * @return string
     */
    public function getNullString(bool $notNull): string;

    /**
     * Build column DDL fragment for id method (i.e. 'AUTO_INCREMENT' for native id method in MySQL)
     *
     * @param \Propel\Generator\Model\IdMethod $idMethod
     * @param \Propel\Generator\Model\Column $column
     *
     * @return string|null Null means id method is not supported (might trigger Exception),
     *                     empty string means column DDL is not affected by id method.
     */
    public function buildAutoIncrementColumnDdl(IdMethod $idMethod, Column $column): ?string;

    /**
     * Returns the DDL SQL for a Column object.
     *
     * @param \Propel\Generator\Model\Column $col
     *
     * @return string
     */
    public function buildColumnDdl(Column $col): string;

    /**
     * Returns the SQL for the default value of a Column object.
     *
     * @param \Propel\Generator\Model\Column $column
     *
     * @return string
     */
    public function buildColumnDefaultValueDdl(Column $column): string;

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
    public function buildColumnListDdl(array $columns, string $delimiter = ','): string;

    /**
     * Returns the SQL for the primary key of a Table object
     *
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    public function buildPrimaryKeyDdl(Table $table): string;

    /**
     * Returns if the RDBMS-specific SQL type has a size attribute.
     *
     * @param string $sqlType the SQL type
     *
     * @return bool True if the type has a size attribute
     */
    public function hasSize(string $sqlType): bool;

    /**
     * Returns if the RDBMS-specific SQL type has a scale attribute.
     *
     * @param string $sqlType the SQL type
     *
     * @return bool True if the type has a scale attribute
     */
    public function hasScale(string $sqlType): bool;

    /**
     * Quote and escape needed characters in the string for underlying RDBMS.
     *
     * @param string $text
     *
     * @return string
     */
    public function quote(string $text): string;

    /**
     * Quotes a identifier.
     *
     * @param string $text
     *
     * @return string
     */
    public function doQuoting(string $text): string;

    /**
     * Whether RDBMS supports native index sizes.
     *
     * @return bool
     */
    public function supportsIndexSize(): bool;

    /**
     * Whether RDBMS supports native ON DELETE triggers (e.g. ON DELETE CASCADE).
     *
     * @return bool
     */
    public function supportsNativeDeleteTrigger(): bool;

    /**
     * Whether RDBMS supports INSERT null values in autoincremented primary keys
     *
     * @return bool
     */
    public function supportsInsertNullPk(): bool;

    /**
     * Whether RDBMS supports native schemas for table layout.
     *
     * @return bool
     */
    public function supportsSchemas(): bool;

    /**
     * Whether RDBMS supports migrations.
     *
     * @return bool
     */
    public function supportsMigrations(): bool;

    /**
     * Whether RDBMS supports VARCHAR without explicit size
     *
     * @return bool
     */
    public function supportsVarcharWithoutSize(): bool;

    /**
     * Returns the boolean value for the RDBMS.
     *
     * This value should match the boolean value that is set
     * when using Propel's PreparedStatement::setBoolean().
     *
     * This function is used to set default column values when building
     * SQL.
     *
     * @param string|int|bool $value A boolean or string representation of boolean ('y', 'true').
     *
     * @return string
     */
    public function getBooleanString($value): string;

    /**
     * Whether the underlying PDO driver for this platform returns BLOB columns as streams (instead of strings).
     *
     * @return bool
     */
    public function hasStreamBlobImpl(): bool;

    /**
     * Gets the preferred timestamp formatter for setting date/time values.
     *
     * @param bool $withMilliseconds
     *
     * @return string
     */
    public function getTimestampFormatter(bool $withMilliseconds = true): string;

    /**
     * Gets the preferred date formatter for setting time values.
     *
     * @return string
     */
    public function getDateFormatter(): string;

    /**
     * Gets the preferred time formatter for setting time values.
     *
     * @param bool $withMilliseconds
     *
     * @return string
     */
    public function getTimeFormatter(bool $withMilliseconds = true): string;

    /**
     * @param \Propel\Generator\Model\Column $column
     *
     * @return string|null
     */
    public function getTemporalFormatter(Column $column): string|null;

    /**
     * @phpstan-return non-empty-string
     *
     * @return string
     */
    public function getSchemaDelimiter(): string;

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
    public function normalizeTable(Table $table): void;

    /**
     * Get the default On Delete behavior for foreign keys when not explicitly set.
     *
     * @return string
     */
    public function getDefaultForeignKeyOnDeleteBehavior(): string;

    /**
     * Get the default On Update behavior for foreign keys when not explicitly set.
     *
     * @return string
     */
    public function getDefaultForeignKeyOnUpdateBehavior(): string;

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
    public function getColumnBindingPHP(Column $column, string $identifier, string $columnValueAccessor, string $tab = '            '): string;

    /**
     * @return bool
     */
    public function isIdentifierQuotingEnabled(): bool;

    /**
     * @param bool $enabled
     *
     * @return void
     */
    public function setIdentifierQuoting(bool $enabled): void;

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    public function buildAddTableDdl(Table $table): string;

    /**
     * Quotes identifiers used in database SQL if isIdentifierQuotingEnabled is true.
     * Calls doQuoting() when identifierQuoting is enabled.
     *
     * @param string $text
     *
     * @return string Quoted identifier.
     */
    public function quoteIdentifier(string $text): string;

    /**
     * Build ENUM or SET SQL declaration, i.e. "SET('foo', 'bar')"
     *
     * @param \Propel\Generator\Model\Datatype\ColumnType $columnType
     * @param array<string> $valueSet
     *
     * @return string
     */
    public function buildNativeEnumeratedColumnSqlType(ColumnType $columnType, array $valueSet): string;

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string|null
     */
    public function buildDefaultTableIdSequenceName(Table $table): ?string;

    /**
     * @param string $identifier
     * @param string|null $suffix
     *
     * @return string
     */
    public function limitIdentifierName(string $identifier, string|null $suffix = null): string;
}
