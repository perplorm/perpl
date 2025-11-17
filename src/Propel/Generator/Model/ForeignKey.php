<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Generator\Model;

use LogicException;
use Propel\Common\Pluralizer\PluralizerInterface;
use Propel\Generator\Platform\PlatformInterface;
use Propel\Runtime\Exception\RuntimeException;

/**
 * A class for information about table foreign keys.
 *
 * @author Hans Lellelid <hans@xmpl.org> (Propel)
 * @author Fedor <fedor.karpelevitch@home.com>
 * @author Daniel Rall <dlr@finemaltcoding.com>
 * @author Ulf Hermann <ulfhermann@kulturserver.de>
 * @author Hugo Hamon <webmaster@apprendre-php.com> (Propel)
 */
class ForeignKey extends MappingModel
{
    /**
     * These constants are the uppercase equivalents of the onDelete / onUpdate
     * values in the schema definition.
     *
     * @var string
     */
    public const NONE = ''; // No 'ON [ DELETE | UPDATE]' behavior

    /**
     * @var string
     */
    public const NOACTION = 'NO ACTION';

    /**
     * @var string
     */
    public const CASCADE = 'CASCADE';

    /**
     * @var string
     */
    public const RESTRICT = 'RESTRICT';

    /**
     * @var string
     */
    public const SETDEFAULT = 'SET DEFAULT';

    /**
     * @var string
     */
    public const SETNULL = 'SET NULL';

    /**
     * @var string
     */
    private $foreignTableCommonName;

    /**
     * @var string
     */
    private $foreignSchemaName;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string|null
     */
    private $phpName;

    /**
     * @var string|null
     */
    private $refPhpName;

    /**
     * @var string
     */
    private $defaultJoin;

    /**
     * @var string
     */
    private $onUpdate = '';

    /**
     * @var string
     */
    private $onDelete = '';

    /**
     * @var \Propel\Generator\Model\Table
     */
    private $sourceTable;

    /**
     * @var array<string>
     */
    private $localColumnNames = [];

    /**
     * @var array<string|null>
     */
    private $foreignColumns = [];

    /**
     * @var array<string|null>
     */
    private $localValues = [];

    /**
     * @var bool
     */
    private $skipSql = false;

    /**
     * @var string
     */
    private $interface;

    /**
     * @var bool
     */
    private $autoNaming = false;

    /**
     * Constructs a new ForeignKey object.
     *
     * @param string|null $name
     */
    public function __construct(?string $name = null)
    {
        if ($name !== null) {
            $this->setName($name);
        }

        $this->onUpdate = self::NONE;
        $this->onDelete = self::NONE;
    }

    /**
     * @return void
     */
    #[\Override]
    protected function setupObject(): void
    {
        $this->foreignTableCommonName = $this->sourceTable->getDatabase()->getTablePrefix() . $this->getAttribute('foreignTable');
        $this->foreignSchemaName = $this->getAttribute('foreignSchema');

        $this->name = $this->getAttribute('name');
        $this->phpName = $this->getAttribute('phpName');
        $this->refPhpName = $this->getAttribute('refPhpName');
        $this->defaultJoin = $this->getAttribute('defaultJoin');
        $this->interface = $this->getAttribute('interface');
        $this->onUpdate = $this->normalizeFKey($this->getAttribute('onUpdate'));
        $this->onDelete = $this->normalizeFKey($this->getAttribute('onDelete'));
        $this->skipSql = $this->booleanValue($this->getAttribute('skipSql'));
    }

    /**
     * @return void
     */
    protected function doNaming(): void
    {
        if (!$this->name || $this->autoNaming) {
            $newName = 'fk_';

            $hash = [];
            $hash[] = $this->foreignSchemaName . '.' . $this->foreignTableCommonName;
            $hash[] = implode(',', $this->localColumnNames);
            $hash[] = implode(',', $this->foreignColumns);

            $newName .= substr(md5(strtolower(implode(':', $hash))), 0, 6);

            if ($this->sourceTable !== null) {
                $newName = $this->sourceTable->getCommonName() . '_' . $newName;
            }

            $this->name = $newName;
            $this->autoNaming = true;
        }
    }

    /**
     * Returns the normalized input of onDelete and onUpdate behaviors.
     *
     * @param string|null $behavior
     * @param string|null $default
     *
     * @return string
     */
    public function normalizeFKey(?string $behavior, ?string $default = null): string
    {
        if ($behavior === null) {
            return $default ?: self::NONE;
        }

        $behavior = strtoupper($behavior);

        if ($behavior === 'NONE' || $behavior === self::NONE) {
            return $default ?: self::NONE;
        }

        if ($behavior === 'SETNULL') {
            return self::SETNULL;
        }

        if ($behavior === 'NOACTION') {
            return self::NOACTION;
        }

        return $behavior;
    }

    /**
     * Returns whether the onUpdate behavior is set.
     *
     * @return bool
     */
    public function hasOnUpdate(): bool
    {
        return $this->onUpdate !== self::NONE;
    }

    /**
     * Returns whether the onDelete behavior is set.
     *
     * @return bool
     */
    public function hasOnDelete(): bool
    {
        return $this->onDelete !== self::NONE;
    }

    /**
     * Returns true if $column is in our local columns list.
     *
     * @param \Propel\Generator\Model\Column $column
     *
     * @return bool
     */
    public function hasLocalColumn(Column $column): bool
    {
        return in_array($column, $this->getLocalColumnObjects(), true);
    }

    /**
     * Returns the onUpdate behavior.
     *
     * @return string
     */
    public function getOnUpdate(): string
    {
        return $this->onUpdate;
    }

    /**
     * Returns the normalized onUpdate behavior taking into account the default of the platform in case the behavior is implicit
     *
     * @return string|null
     */
    public function getOnUpdateWithDefault(): ?string
    {
        $rawBehavior = $this->getOnUpdate();
        $platform = $this->getPlatform();
        $defaultBehavior = ($platform) ? $platform->getDefaultForeignKeyOnUpdateBehavior() : null;

        return $this->normalizeFKey($rawBehavior, $defaultBehavior);
    }

    /**
     * Returns the onDelete behavior.
     *
     * @return string
     */
    public function getOnDelete(): string
    {
        return $this->onDelete;
    }

    /**
     * Returns the normalized onDelete behavior taking into account the default of the platform in case the behavior is implicit
     *
     * @return string|null
     */
    public function getOnDeleteWithDefault(): ?string
    {
        $rawBehavior = $this->getOnDelete();
        $platform = $this->getPlatform();
        $defaultBehavior = ($platform) ? $platform->getDefaultForeignKeyOnDeleteBehavior() : null;

        return $this->normalizeFKey($rawBehavior, $defaultBehavior);
    }

    /**
     * Sets the onDelete behavior.
     *
     * @param string|null $behavior
     *
     * @return void
     */
    public function setOnDelete(?string $behavior): void
    {
        $this->onDelete = $this->normalizeFKey($behavior);
    }

    /**
     * Sets the onUpdate behavior.
     *
     * @param string|null $behavior
     *
     * @return void
     */
    public function setOnUpdate(?string $behavior): void
    {
        $this->onUpdate = $this->normalizeFKey($behavior);
    }

    /**
     * Returns the foreign key name.
     *
     * @return string
     */
    public function getName(): string
    {
        $this->doNaming();

        return $this->name;
    }

    /**
     * Sets the foreign key name.
     *
     * @param string $name
     *
     * @return void
     */
    public function setName(string $name): void
    {
        $this->autoNaming = !$name; //if no name we activate autoNaming
        $this->name = $name;
    }

    /**
     * @return string|null
     */
    public function getInterface(): ?string
    {
        return $this->interface;
    }

    /**
     * @param string $interface
     *
     * @return void
     */
    public function setInterface(string $interface): void
    {
        $this->interface = $interface;
    }

    /**
     * Returns the phpName for this foreign key (if any).
     *
     * @return string|null
     */
    public function getPhpName(): ?string
    {
        return $this->phpName;
    }

    /**
     * Sets a phpName to use for this foreign key.
     *
     * @param string $name
     *
     * @return void
     */
    public function setPhpName(string $name): void
    {
        $this->phpName = $name;
    }

    /**
     * Returns the refPhpName for this foreign key (if any).
     *
     * @return string|null
     */
    public function getRefPhpName(): ?string
    {
        return $this->refPhpName;
    }

    /**
     * Sets a refPhpName to use for this foreign key.
     *
     * @param string $name
     *
     * @return void
     */
    public function setRefPhpName(string $name): void
    {
        $this->refPhpName = $name;
    }

    /**
     * Returns the default join strategy for this foreign key (if any).
     *
     * @return string|null
     */
    public function getDefaultJoin(): ?string
    {
        return $this->defaultJoin;
    }

    /**
     * Sets the default join strategy for this foreign key (if any).
     *
     * @param string $join
     *
     * @return void
     */
    public function setDefaultJoin(string $join): void
    {
        $this->defaultJoin = $join;
    }

    /**
     * Returns the PlatformInterface instance.
     *
     * @return \Propel\Generator\Platform\PlatformInterface|null
     */
    private function getPlatform(): ?PlatformInterface
    {
        return $this->sourceTable->getPlatform();
    }

    /**
     * Returns the Database object of this Column.
     *
     * @return \Propel\Generator\Model\Database|null
     */
    public function getDatabase(): ?Database
    {
        return $this->sourceTable->getDatabase();
    }

    /**
     * Returns the foreign table name of the FK.
     *
     * @return string|null
     */
    public function getForeignTableName(): ?string
    {
        $platform = $this->getPlatform();
        if ($this->foreignSchemaName && $platform && $platform->supportsSchemas()) {
            return $this->foreignSchemaName
                . $platform->getSchemaDelimiter()
                . $this->foreignTableCommonName;
        }

        $database = $this->getDatabase();
        $schema = $this->sourceTable->guessSchemaName();
        if ($database && $schema && $platform && $platform->supportsSchemas()) {
            return $schema
                . $platform->getSchemaDelimiter()
                . $this->foreignTableCommonName;
        }

        return $this->foreignTableCommonName;
    }

    /**
     * Returns the foreign table name without schema.
     *
     * @return string|null
     */
    public function getForeignTableCommonName(): ?string
    {
        return $this->foreignTableCommonName;
    }

    /**
     * Sets the foreign table common name of the FK.
     *
     * @param string $tableName
     *
     * @return void
     */
    public function setForeignTableCommonName(string $tableName): void
    {
        $this->foreignTableCommonName = $tableName;
    }

    /**
     * Returns the resolved foreign Table model object.
     *
     * @return \Propel\Generator\Model\Table|null
     */
    public function getForeignTable(): ?Table
    {
        $database = $this->sourceTable->getDatabase();
        if ($database) {
            return $database->getTable($this->getForeignTableName());
        }

        return null;
    }

    /**
     * Returns the resolved foreign Table model object.
     *
     * @throws \LogicException
     *
     * @return \Propel\Generator\Model\Table
     */
    public function getForeignTableOrFail(): Table
    {
        $database = $this->sourceTable->getDatabaseOrFail();
        $tableName = $this->getForeignTableName();
        $table = $database->getTable($tableName);
        if (!$table) {
            throw new LogicException("Table '$tableName' does not exist.");
        }

        return $table;
    }

    /**
     * Returns the foreign schema name of the FK.
     *
     * @return string|null
     */
    public function getForeignSchemaName(): ?string
    {
        return $this->foreignSchemaName;
    }

    /**
     * Set the foreign schema name of the foreign key.
     *
     * @param string|null $schemaName
     *
     * @return void
     */
    public function setForeignSchemaName(?string $schemaName): void
    {
        $this->foreignSchemaName = $schemaName;
    }

    /**
     * Sets the parent Table of the foreign key.
     *
     * @param \Propel\Generator\Model\Table $parent
     *
     * @return void
     */
    public function setTable(Table $parent): void
    {
        $this->sourceTable = $parent;
    }

    /**
     * Returns the parent Table of the foreign key.
     *
     * @return \Propel\Generator\Model\Table
     */
    public function getTable(): Table
    {
        return $this->sourceTable;
    }

    /**
     * Returns the name of the table the foreign key is in.
     *
     * @return string
     */
    public function getTableName(): string
    {
        return $this->sourceTable->getName();
    }

    /**
     * Returns the name of the schema the foreign key is in.
     *
     * @return string|null
     */
    public function getSchemaName(): ?string
    {
        return $this->sourceTable->getSchema();
    }

    /**
     * Adds a new reference entry to the foreign key.
     *
     * @param mixed $ref1 A Column object or an associative array or a string
     * @param mixed $ref2 A Column object or a single string name
     *
     * @return void
     */
    public function addReference($ref1, $ref2 = null): void
    {
        if (is_array($ref1)) {
            $this->localColumnNames[] = $ref1['local'] ?? null;
            $this->foreignColumns[] = $ref1['foreign'] ?? null;
            $this->localValues[] = $ref1['value'] ?? null;

            return;
        }

        if (is_string($ref1)) {
            $this->localColumnNames[] = $ref1;
            $this->foreignColumns[] = is_string($ref2) ? $ref2 : null;
            $this->localValues[] = null;

            return;
        }

        $local = null;
        if ($ref1 instanceof Column) {
            /** @var string $local */
            $local = $ref1->getName();
            $this->localColumnNames[] = $local;
        } else {
            $this->localValues[] = $local;
        }

        if ($ref2 instanceof Column) {
            $foreign = $ref2->getName();
            $this->foreignColumns[] = $foreign;
            $this->localValues[] = null;
        } elseif ($ref1 instanceof Column) {
            $this->foreignColumns[] = null;
            $this->localValues[] = $ref2;
        }
    }

    /**
     * Clears the references of this foreign key.
     *
     * @return void
     */
    public function clearReferences(): void
    {
        $this->localColumnNames = [];
        $this->foreignColumns = [];
        $this->localValues = [];
    }

    /**
     * Returns an array of local column names.
     *
     * @return array<string>
     */
    public function getLocalColumns(): array
    {
        return $this->localColumnNames;
    }

    /**
     * Returns an array of local column objects.
     *
     * @return array<\Propel\Generator\Model\Column>
     */
    public function getLocalColumnObjects(): array
    {
        $columns = [];
        foreach ($this->localColumnNames as $columnName) {
            /** @var \Propel\Generator\Model\Column $column */
            $column = $this->sourceTable->getColumn($columnName);
            $columns[] = $column;
        }

        return $columns;
    }

    /**
     * Returns a local column name identified by a position.
     *
     * @param int $index
     *
     * @return string
     */
    public function getLocalColumnName(int $index = 0): string
    {
        return $this->localColumnNames[$index];
    }

    /**
     * Returns a local Column object identified by a position.
     *
     * @param int $index
     *
     * @return \Propel\Generator\Model\Column|null
     */
    public function getLocalColumn(int $index = 0): ?Column
    {
        return $this->sourceTable->getColumn($this->getLocalColumnName($index));
    }

    /**
     * @throws \LogicException
     *
     * @return array<array{0: \Propel\Generator\Model\Column, 1: \Propel\Generator\Model\Column|string}> [[Column $leftColumn, $rightValueOrColumn], ..., ...]
     */
    public function getMapping(): array
    {
        $mapping = [];
        foreach ($this->localColumnNames as $i => $localColumnName) {
            $right = $this->foreignColumns[$i]
                ? $this->getForeignTable()->getColumn($this->foreignColumns[$i])
                : $this->localValues[$i];
            $leftColumn = $this->sourceTable->getColumn($localColumnName);
            if ($right === null || $leftColumn === null) {
                throw new LogicException('ForeignKey mapping cannot contain null values.');
            }
            $mapping[] = [$leftColumn, $right];
        }

        return $mapping;
    }

    /**
     * @return array<array{0: \Propel\Generator\Model\Column|string, 1: \Propel\Generator\Model\Column}> [[$leftValueOrColumn, Column $rightColumn], ..., ...]
     */
    public function getInverseMapping(): array
    {
        /** @var array<array{0: string|\Propel\Generator\Model\Column, 1: \Propel\Generator\Model\Column}> */
        return array_map('array_reverse', $this->getMapping());
    }

    /**
     * Returns an array of local and foreign column objects
     * mapped for this foreign key.
     *
     * @return array
     */
    public function getColumnObjectsMapping(): array
    {
        $mapping = [];
        $foreignTable = $this->getForeignTable();
        $size = count($this->localColumnNames);
        for ($i = 0; $i < $size; $i++) {
            $mapping[] = [
                'local' => $this->sourceTable->getColumn($this->localColumnNames[$i]),
                'foreign' => $foreignTable->getColumn($this->foreignColumns[$i]),
                'value' => $this->localValues[$i],
            ];
        }

        return $mapping;
    }

    /**
     * Returns the foreign column name mapped to a specified local column.
     *
     * @param string $local
     *
     * @return string|null
     */
    public function getMappedForeignColumn(string $local): ?string
    {
        $index = array_search($local, $this->localColumnNames);

        return $this->foreignColumns[$index];
    }

    /**
     * Returns the local column name mapped to a specified foreign column.
     *
     * @param string $foreign
     *
     * @return string|null
     */
    public function getMappedLocalColumn(string $foreign): ?string
    {
        $index = array_search($foreign, $this->foreignColumns);

        return $this->localColumnNames[$index];
    }

    /**
     * Returns an array of foreign column names.
     *
     * @return array<string|null>
     */
    public function getForeignColumns(): array
    {
        return $this->foreignColumns;
    }

    /**
     * Returns an array of foreign column objects.
     *
     * @return array<\Propel\Generator\Model\Column>
     */
    public function getForeignColumnObjects(): array
    {
        $columns = [];
        $foreignTable = $this->getForeignTable();
        foreach ($this->foreignColumns as $columnName) {
            $column = $foreignTable->getColumn($columnName);
            if ($column !== null) {
                $columns[] = $column;
            }
        }

        return $columns;
    }

    /**
     * Returns a foreign column name.
     *
     * @param int $index
     *
     * @return string|null
     */
    public function getForeignColumnName(int $index = 0): ?string
    {
        return $this->foreignColumns[$index];
    }

    /**
     * Returns a foreign column object.
     *
     * @param int $index
     *
     * @return \Propel\Generator\Model\Column|null
     */
    public function getForeignColumn(int $index = 0): ?Column
    {
        return $this->getForeignTable()->getColumn($this->getForeignColumnName($index));
    }

    /**
     * Returns whether this foreign key uses only required local columns.
     *
     * @return bool
     */
    public function isLocalColumnsRequired(): bool
    {
        foreach ($this->localColumnNames as $columnName) {
            if (!$this->sourceTable->getColumn($columnName)->isNotNull()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns whether this foreign key uses at least one required local column.
     *
     * @return bool
     */
    public function usesNotNullSourceColumn(): bool
    {
        foreach ($this->localColumnNames as $columnName) {
            if ($this->sourceTable->getColumn($columnName)->isNotNull()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns whether this foreign key uses at least one required(notNull && no defaultValue) local primary key.
     *
     * @return bool
     */
    public function hasColumnWithRequiredValue(): bool
    {
        foreach ($this->getLocalColumnObjects() as $pk) {
            if ($pk->isNotNull() && !$pk->hasDefault()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns whether this foreign key is also the primary key of the foreign
     * table.
     *
     * @return bool Returns true if all columns inside this foreign key are primary keys of the foreign table
     */
    public function isForeignPrimaryKey(): bool
    {
        $foreignTable = $this->getForeignTable();

        $foreignPKCols = [];
        foreach ($foreignTable->getPrimaryKey() as $fPKCol) {
            $foreignPKCols[] = $fPKCol->getName();
        }

        $foreignCols = [];
        foreach ($this->localColumnNames as $idx => $colName) {
            if ($this->foreignColumns[$idx]) {
                $foreignCols[] = $foreignTable->getColumn($this->foreignColumns[$idx])->getName();
            }
        }

        return ((count($foreignPKCols) === count($foreignCols))
            && !array_diff($foreignPKCols, $foreignCols));
    }

    /**
     * Returns whether this foreign key is not the primary key of the foreign
     * table.
     *
     * @return bool Returns true if all columns inside this foreign key are not primary keys of the foreign table
     */
    public function isForeignNonPrimaryKey(): bool
    {
        $foreignTable = $this->getForeignTable();

        $foreignPKCols = [];
        foreach ($foreignTable->getPrimaryKey() as $fPKCol) {
            $foreignPKCols[] = $fPKCol->getName();
        }

        $foreignCols = [];
        foreach ($this->localColumnNames as $idx => $colName) {
            if ($this->foreignColumns[$idx]) {
                $foreignCols[] = $foreignTable->getColumn($this->foreignColumns[$idx])->getName();
            }
        }

        return (bool)array_diff($foreignCols, $foreignPKCols);
    }

    /**
     * Returns whether this foreign key relies on more than one
     * column binding.
     *
     * @return bool
     */
    public function isComposite(): bool
    {
        return count($this->localColumnNames) > 1;
    }

    /**
     * @param array<array{0: \Propel\Generator\Model\Column|string, 1: string|\Propel\Generator\Model\Column|string}> $mapping
     *
     * @return array<array{0: string, 1: string}> [[$localColumnName, $right, $compare], ...]
     */
    public function getNormalizedMap(array $mapping): array
    {
        $result = [];

        foreach ($mapping as $map) {
            $result[] = array_map(fn ($col) => $col instanceof Column ? ':' . $col->getName() : $col, $map);
        }

        return $result;
    }

    /**
     * Whether this relation is a polymorphic association.
     *
     * At least one reference with a expression attribute set.
     *
     * @return bool
     */
    public function isPolymorphic(): bool
    {
        foreach ($this->localValues as $value) {
            if ($value !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array [[$localColumnName, $localValue], [.., ..], ...]
     */
    public function getLocalValues(): array
    {
        $map = [];

        foreach ($this->localColumnNames as $idx => $columnName) {
            if ($this->localValues[$idx]) {
                $map[] = [$columnName, $this->localValues[$idx]];
            }
        }

        return $map;
    }

    /**
     * Returns whether this foreign key is also the primary key of
     * the local table.
     *
     * @return bool True if all local columns are at the same time a primary key
     */
    public function isLocalPrimaryKey(): bool
    {
        $localPKCols = [];
        foreach ($this->sourceTable->getPrimaryKey() as $lPKCol) {
            $localPKCols[] = $lPKCol->getName();
        }

        return count($localPKCols) === count($this->localColumnNames) && !array_diff($localPKCols, $this->localColumnNames);
    }

    /**
     * Sets whether this foreign key should have its creation SQL
     * generated.
     *
     * @param bool $skip
     *
     * @return void
     */
    public function setSkipSql(bool $skip): void
    {
        $this->skipSql = $skip;
    }

    /**
     * Returns whether the SQL generation must be skipped for this
     * foreign key.
     *
     * @return bool
     */
    public function isSkipSql(): bool
    {
        return $this->skipSql;
    }

    /**
     * Whether this foreign key is matched by an inverted foreign key (on foreign table).
     *
     * This is to prevent duplicate columns being generated for a 1:1 relationship that is represented
     * by foreign keys on both tables. I don't know if that's good practice ... but hell, why not
     * support it.
     *
     * @link http://propel.phpdb.org/trac/ticket/549
     *
     * @return bool
     */
    public function isMatchedByInverseFK(): bool
    {
        return (bool)$this->getInverseFK();
    }

    /**
     * Tries to find a foreign key on the target table that references this table using the same columns and values.
     *
     * @throws \Propel\Runtime\Exception\RuntimeException
     *
     * @return \Propel\Generator\Model\ForeignKey|null
     */
    public function getInverseFK(): ?self
    {
        $foreignTable = $this->getForeignTable();
        if (!$foreignTable) {
            throw new RuntimeException('No foreign table given');
        }

        $map = $this->getInverseMapping();

        foreach ($foreignTable->getForeignKeys() as $refFK) {
            $fkMap = $refFK->getMapping();
            // compares keys and values, but doesn't care about order, included check to make sure it's the same table (fixes #679)
            if (($refFK->getTableName() === $this->getTableName()) && ($map === $fkMap)) {
                return $refFK;
            }
        }

        return null;
    }

    /**
     * Returns all local columns which are also a primary key of the local table.
     *
     * @return array<\Propel\Generator\Model\Column>
     */
    public function getLocalPrimaryKeys(): array
    {
        $cols = [];
        $localCols = $this->getLocalColumnObjects();

        foreach ($localCols as $localCol) {
            if ($localCol->isPrimaryKey()) {
                $cols[] = $localCol;
            }
        }

        return $cols;
    }

    /**
     * Whether at least one local column is also a primary key.
     *
     * @return bool True if there is at least one column that is a primary key
     */
    public function isAtLeastOneLocalPrimaryKey(): bool
    {
        $cols = $this->getLocalPrimaryKeys();

        return count($cols) !== 0;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        $localTableName = $this->sourceTable->getName();
        $targetTableName = $this->getForeignTableName();

        $connections = [];
        foreach ($this->localColumnNames as $index => $columnName) {
            $connections[] = "$localTableName.$columnName -> $targetTableName.{$this->foreignColumns[$index]}";
        }
        $glue = "\n - ";
        $columns = implode($glue, $connections) . "\n";

        return "Foreign key from $localTableName to $targetTableName: $glue$columns";
    }

    /**
     * Find other relations on source table to the same target table.
     *
     * @return array<\Propel\Generator\Model\ForeignKey>
     */
    public function findParallelRelations(): array
    {
        $fksOnSource = $this->sourceTable->getForeignKeys();
        $filter = fn (ForeignKey $fk) => $fk->foreignTableCommonName === $this->foreignTableCommonName && $fk !== $this;

        return array_filter($fksOnSource, $filter);
    }

    /**
     * PascalCase identifier of this FK. Used in getters/setters on the source model object.
     *
     * @param \Propel\Common\Pluralizer\PluralizerInterface|null $usePluralizer
     *
     * @return string
     */
    public function getIdentifier(?PluralizerInterface $usePluralizer = null): string
    {
        return $this->buildIdentifier(
            $usePluralizer,
            $this->phpName,
            $this->getForeignTableOrFail()->getPhpName(),
            fn () => $this->buildIdentifierRelatedBySuffix(),
        );
    }

    /**
     * Gets the "RelatedBy*" suffix (if needed) that is attached to method and variable names.
     *
     * The related by suffix is based on the local columns of the foreign key. If there is more than
     * one column in a table that points to the same foreign table, then a 'RelatedByLocalColName' suffix
     * will be appended.
     *
     * @return string
     */
    public function buildIdentifierRelatedBySuffix(): string
    {
        $relatedByColumnNames = '';

        $foreignTableName = (string)$this->getForeignTableName();
        $tableName = $this->getTableName();

        $isSelfJoin = $foreignTableName === $tableName;
        $hasParallelRelations = count($this->findParallelRelations()) > 0
            || count($this->getForeignTableOrFail()->getForeignKeysReferencingTable($tableName)) > 0;

        if (!$isSelfJoin && !$hasParallelRelations) {
            return '';
        }

        foreach ($this->localColumnNames as $localColumnName) {
            $localColumn = $this->sourceTable->getColumn($localColumnName);
            $relatedByColumnNames .= $localColumn->getPhpName();
        }

        return "RelatedBy$relatedByColumnNames";
    }

    /**
     * PascalCase identifier of this FK's reversed relation. Used in getters/setters on the target model object.
     *
     * @param \Propel\Common\Pluralizer\PluralizerInterface|null $usePluralizer
     *
     * @return string
     */
    public function getIdentifierReversed(?PluralizerInterface $usePluralizer = null): string
    {
        return $this->buildIdentifier(
            $usePluralizer,
            $this->refPhpName,
            $this->sourceTable->getPhpName(),
            fn () => $this->buildIdentifierReversedRelatedBySuffix(),
        );
    }

    /**
     * Build relation identifier.
     *
     * @param \Propel\Common\Pluralizer\PluralizerInterface|null $usePluralizer
     * @param string|null $phpName
     * @param string $targetTableName
     * @param callable $suffixBuilder
     *
     * @return string
     */
    protected function buildIdentifier(?PluralizerInterface $usePluralizer, ?string $phpName, string $targetTableName, callable $suffixBuilder): string
    {
        if ($phpName) {
            return $usePluralizer ? $usePluralizer->getPluralForm($phpName) : $phpName;
        }

        if ($usePluralizer) {
            $targetTableName = $usePluralizer->getPluralForm($targetTableName);
        }

        return $targetTableName . $suffixBuilder();
    }

    /**
     * @return string
     */
    public function buildIdentifierReversedRelatedBySuffix(): string
    {
        $localTable = $this->getTable();
        $tableName = $this->getTableName();
        $foreignTableName = (string)$this->getForeignTableName();
        $parallelRelations = $localTable->getForeignKeysReferencingTable($foreignTableName);

        $isSelfJoin = $foreignTableName === $tableName;
        $isParallelRelation = count($parallelRelations) > 1;
        $hasBackRelation = count($this->getForeignTableOrFail()->getForeignKeysReferencingTable($tableName)) > 0;

        if (!$isSelfJoin && !$isParallelRelation && !$hasBackRelation) {
            return '';
        }

        $relationIndex = $isSelfJoin ? array_search($this, $parallelRelations) : '';

        $relCol = '';
        foreach ($this->getMapping() as $mapping) {
            [$localColumn, $foreignValueOrColumn] = $mapping;

            if ($foreignValueOrColumn instanceof Column && $isSelfJoin) {
                $relCol .= $foreignValueOrColumn->getPhpName();

                if ($isParallelRelation) {
                    // several self-referential foreign keys
                    $relCol .= $relationIndex;
                }
            } elseif ($isParallelRelation || $hasBackRelation) {
                // several foreign keys to the same table, or symmetrical foreign key in foreign table
                $relCol .= $localColumn->getPhpName();
            }
        }

        return "RelatedBy$relCol";
    }

    /**
     * @return bool
     */
    public function isOneToOne(): bool
    {
        return $this->isLocalPrimaryKey() && !$this->isForeignNonPrimaryKey();
    }
}
