<?php

declare(strict_types = 1);

namespace Propel\Generator\Model;

use Propel\Generator\Config\AbstractGeneratorConfig;
use Propel\Generator\Exception\EngineException;
use Propel\Generator\Exception\InvalidArgumentException;
use Propel\Generator\Exception\SchemaException;
use Propel\Generator\Platform\PlatformInterface;
use function array_filter;
use function array_map;
use function count;
use function explode;
use function implode;
use function in_array;
use function ltrim;
use function sprintf;
use function str_contains;
use function strpos;
use function strtolower;
use function strtoupper;

/**
 * A class for holding application data structures.
 */
class Database extends ScopedMappingModel
{
    use BehaviorableTrait;

    private PlatformInterface|null $platform = null;

    /**
     * @var array<\Propel\Generator\Model\Table>
     */
    private $tables = [];

    private string|null $name = null;

    private string|null $baseClass = null;

    /**
     * @var class-string|null
     */
    private string|null $baseQueryClass = null;

    private IdMethod $defaultIdMethod;

    private string $defaultPhpNamingMethod;

    /**
     * Visibility of generated model setter methods (public, private or protected).
     */
    private string $defaultAccessorVisibility;

    /**
     * Visibility of generated model getter methods (public, private or protected).
     */
    private string $defaultMutatorVisibility;

    /**
     * @var array<string, \Propel\Generator\Model\TypeMapping>
     */
    private array $typeMapping = [];

    private bool $heavyIndexing = false;

    private bool $identifierQuoting = false;

    private Schema|null $parentSchema = null;

    /**
     * @var array<\Propel\Generator\Model\Table>
     */
    private array $tablesByName = [];

    /**
     * @var array<\Propel\Generator\Model\Table>
     */
    private $tablesByLowercaseName = [];

    /**
     * @var array<\Propel\Generator\Model\Table>
     */
    private array $tablesByPhpName = [];

    /**
     * @var array<string>
     */
    private array $sequencesNames = [];

    protected string $defaultStringFormat;

    protected string|null $tablePrefix = null;

    /**
     * @param string|null $name
     * @param \Propel\Generator\Platform\PlatformInterface|null $platform
     */
    public function __construct(?string $name = null, ?PlatformInterface $platform = null)
    {
        parent::__construct();

        if ($name !== null) {
            $this->setName($name);
        }

        if ($platform !== null) {
            $this->setPlatform($platform);
        }

        $this->defaultPhpNamingMethod = NameGeneratorInterface::CONV_METHOD_UNDERSCORE;
        $this->defaultIdMethod = IdMethod::NATIVE;
        $this->defaultStringFormat = static::DEFAULT_STRING_FORMAT;
        $this->defaultAccessorVisibility = static::VISIBILITY_PUBLIC;
        $this->defaultMutatorVisibility = static::VISIBILITY_PUBLIC;
    }

    /**
     * @return void
     */
    #[\Override]
    protected function setupObject(): void
    {
        parent::setupObject();

        $this->name = $this->getAttribute('name');
        $this->baseClass = $this->getAttribute('baseClass');
        $this->baseQueryClass = $this->getAttribute('baseQueryClass');
        $this->defaultIdMethod = IdMethod::fromAttribute($this->getAttribute('defaultIdMethod')) ?? IdMethod::NATIVE;
        $this->defaultPhpNamingMethod = $this->getAttribute('defaultPhpNamingMethod', NameGeneratorInterface::CONV_METHOD_UNDERSCORE);
        $this->heavyIndexing = $this->booleanValue($this->getAttribute('heavyIndexing'));

        if ($this->getAttribute('identifierQuoting')) {
            $this->identifierQuoting = $this->booleanValue($this->getAttribute('identifierQuoting'));
        }

        $this->tablePrefix = $this->getAttribute('tablePrefix', $this->getGeneratorConfig()?->getConfigPropertyString('generator.tablePrefix'));
        $this->defaultStringFormat = $this->getAttribute('defaultStringFormat', static::DEFAULT_STRING_FORMAT);
    }

    /**
     * @return \Propel\Generator\Platform\PlatformInterface|null
     */
    public function getPlatform(): ?PlatformInterface
    {
        return $this->platform;
    }

    /**
     * @param \Propel\Generator\Platform\PlatformInterface|null $platform A Platform implementation
     *
     * @return void
     */
    public function setPlatform(?PlatformInterface $platform = null): void
    {
        $this->platform = $platform;
    }

    /**
     * @return int
     */
    public function getMaxColumnNameLength(): int
    {
        return $this->platform->getMaxColumnNameLength();
    }

    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string $name
     *
     * @return void
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * Returns the name of the base super class inherited by active record
     * objects. This parameter is overridden at the table level.
     *
     * @return string|null
     */
    public function getBaseClass(): ?string
    {
        return $this->baseClass;
    }

    /**
     * Returns the name of the base super class inherited by query
     * objects. This parameter is overridden at the table level.
     *
     * @return class-string|null
     */
    public function getBaseQueryClass(): ?string
    {
        return $this->baseQueryClass;
    }

    /**
     * Sets the name of the base super class inherited by active record objects.
     * This parameter is overridden at the table level.
     *
     * @param class-string $class
     *
     * @return void
     */
    public function setBaseClass(string $class): void
    {
        $this->baseClass = $this->makeClassNameAbsolute($class);
    }

    /**
     * Sets the name of the base super class inherited by query objects.
     * This parameter is overridden at the table level.
     *
     * @param class-string $class
     *
     * @return void
     */
    public function setBaseQueryClass(string $class): void
    {
        $this->baseQueryClass = $this->makeClassNameAbsolute($class);
    }

    /**
     * Returns the name of the default ID method strategy.
     * This parameter can be overridden at the table level.
     *
     * @return \Propel\Generator\Model\IdMethod
     */
    public function getDefaultIdMethod(): IdMethod
    {
        return $this->defaultIdMethod;
    }

    /**
     * Sets the name of the default ID method strategy.
     * This parameter can be overridden at the table level.
     *
     * @param \Propel\Generator\Model\IdMethod $strategy
     *
     * @return void
     */
    public function setDefaultIdMethod(IdMethod $strategy): void
    {
        $this->defaultIdMethod = $strategy;
    }

    /**
     * Returns the name of the default PHP naming method strategy, which
     * specifies the method for converting schema names for table and column to
     * PHP names. This parameter can be overridden at the table layer.
     *
     * @return string
     */
    public function getDefaultPhpNamingMethod(): string
    {
        return $this->defaultPhpNamingMethod;
    }

    /**
     * Sets name of the default PHP naming method strategy.
     *
     * @param string $strategy
     *
     * @return void
     */
    public function setDefaultPhpNamingMethod(string $strategy): void
    {
        $this->defaultPhpNamingMethod = $strategy;
    }

    /**
     * Returns the list of supported string formats
     *
     * @return array<string>
     */
    public static function getSupportedStringFormats(): array
    {
        return ['XML', 'YAML', 'JSON', 'CSV'];
    }

    /**
     * Sets the default string format for ActiveRecord objects in this table.
     * This parameter can be overridden at the table level.
     *
     * Any of 'XML', 'YAML', 'JSON', or 'CSV'.
     *
     * @param string $format
     *
     * @throws \Propel\Generator\Exception\InvalidArgumentException
     *
     * @return void
     */
    public function setDefaultStringFormat(string $format): void
    {
        $formats = static::getSupportedStringFormats();

        $format = strtoupper($format);
        if (!in_array($format, $formats, true)) {
            throw new InvalidArgumentException(sprintf('Given "%s" default string format is not supported. Only "%s" are valid string formats.', $format, implode(', ', $formats)));
        }

        $this->defaultStringFormat = $format;
    }

    /**
     * Returns the default string format for ActiveRecord objects in this table.
     * This parameter can be overridden at the table level.
     *
     * @return string
     */
    public function getDefaultStringFormat(): string
    {
        return $this->defaultStringFormat;
    }

    /**
     * Returns whether heavy indexing is enabled.
     *
     * This is an alias for getHeavyIndexing().
     *
     * @return bool
     */
    public function isHeavyIndexing(): bool
    {
        return $this->getHeavyIndexing();
    }

    /**
     * Returns whether heavy indexing is enabled.
     *
     * This is an alias for isHeavyIndexing().
     *
     * @return bool
     */
    public function getHeavyIndexing(): bool
    {
        return $this->heavyIndexing;
    }

    /**
     * @param bool $flag
     *
     * @return void
     */
    public function setHeavyIndexing(bool $flag): void
    {
        $this->heavyIndexing = $flag;
    }

    /**
     * @return array<\Propel\Generator\Model\Table>
     */
    public function getTables(): array
    {
        return $this->tables;
    }

    /**
     * Return the number of tables in the database.
     *
     * Read-only tables are excluded from the count.
     *
     * @return int
     */
    public function countTables(): int
    {
        $mutableTables = array_filter($this->tables, fn (Table $t) => !$t->isReadOnly());

        return count($mutableTables);
    }

    /**
     * Returns the list of all tables that have a SQL representation.
     *
     * @return array<\Propel\Generator\Model\Table>
     */
    public function getTablesForSql(): array
    {
        return array_filter($this->tables, fn (Table $t) => !$t->isSkipSql());
    }

    /**
     * @param string $name
     * @param bool $caseInsensitive
     *
     * @return bool
     */
    public function hasTable(string $name, bool $caseInsensitive = false): bool
    {
        return $caseInsensitive
            ? isset($this->tablesByLowercaseName[strtolower($name)])
            : isset($this->tablesByName[$name]);
    }

    /**
     * @param string $name
     * @param bool $caseInsensitive
     *
     * @return \Propel\Generator\Model\Table|null
     */
    public function getTable(string $name, bool $caseInsensitive = false): ?Table
    {
        if (
            $this->getSchema() && $this->platform->supportsSchemas()
            && !str_contains($name, $this->platform->getSchemaDelimiter())
        ) {
            $name = $this->getSchema() . $this->platform->getSchemaDelimiter() . $name;
        }

        if (!$this->hasTable($name, $caseInsensitive)) {
            return null;
        }

        return $caseInsensitive
            ? $this->tablesByLowercaseName[strtolower($name)]
            : $this->tablesByName[$name];
    }

    /**
     * @param string $phpName
     *
     * @return bool
     */
    public function hasTableByPhpName(string $phpName): bool
    {
        return isset($this->tablesByPhpName[$phpName]);
    }

    /**
     * @param string $phpName
     *
     * @return \Propel\Generator\Model\Table|null
     */
    public function getTableByPhpName(string $phpName): ?Table
    {
        return $this->tablesByPhpName[$phpName] ?? null;
    }

    /**
     * @param array<\Propel\Generator\Model\Table> $tables
     *
     * @return void
     */
    public function addTables(array $tables): void
    {
        array_map([$this, 'addTable'], $tables);
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return void
     */
    public function removeTable(Table $table): void
    {
        if (!$this->hasTable($table->getName(), true)) {
            return;
        }
        foreach ($this->tables as $id => $tableExam) {
            if ($table->getName() === $tableExam->getName()) {
                unset($this->tables[$id]);
            }
        }

        unset($this->tablesByName[$table->getName()]);
        unset($this->tablesByLowercaseName[strtolower($table->getName())]);
        unset($this->tablesByPhpName[$table->getPhpName()]);
    }

    /**
     * @param \Propel\Generator\Model\Table|array $table
     *
     * @throws \Propel\Generator\Exception\EngineException
     * @throws \Propel\Generator\Exception\SchemaException
     *
     * @return \Propel\Generator\Model\Table
     */
    public function addTable($table): Table
    {
        if (!$table instanceof Table) {
            if (empty($table['name'])) {
                throw new SchemaException('Table misses required attribute `name`');
            }
            $tbl = new Table($table['name']);
            $tbl->setDatabase($this);
            $tbl->loadMapping($table);

            return $this->addTable($tbl);
        }

        $table->setDatabase($this);

        if (isset($this->tablesByName[$table->getName()])) {
            throw new EngineException(sprintf('Table "%s" declared twice', $table->getName()));
        }

        $this->tables[] = $table;
        $this->tablesByName[$table->getName()] = $table;
        $this->tablesByLowercaseName[strtolower($table->getName())] = $table;
        $this->tablesByPhpName[$table->getPhpName()] = $table;

        $newTableNamespace = $this->getCombinedNamespace($table);
        if ($newTableNamespace !== null) {
            $table->setNamespace($newTableNamespace);
        }

        if ($table->getPackage() === null) {
            $table->setPackage($this->getPackage());
        }

        return $table;
    }

    /**
     * @param array<string> $sequenceNames
     *
     * @return void
     */
    public function setSequences(array $sequenceNames): void
    {
        $this->sequencesNames = $sequenceNames;
    }

    /**
     * @return array<string>
     */
    public function getSequences(): array
    {
        return $this->sequencesNames;
    }

    /**
     * @param string $sequenceName
     *
     * @return void
     */
    public function addSequence(string $sequenceName): void
    {
        $this->sequencesNames[] = $sequenceName;
    }

    /**
     * @param string $sequenceName
     *
     * @return void
     */
    public function removeSequence(string $sequenceName): void
    {
        $this->sequencesNames = array_filter($this->sequencesNames, fn (string $s) => $s !== $sequenceName);
    }

    /**
     * @param string $sequence
     *
     * @return bool
     */
    public function hasSequence(string $sequence): bool
    {
        return $this->sequencesNames && in_array($sequence, $this->sequencesNames, true);
    }

    /**
     * Returns the schema delimiter character.
     *
     * For example, the dot character with mysql when
     * naming tables. For instance: schema.the_table.
     *
     * @return string
     */
    public function getSchemaDelimiter(): string
    {
        return $this->platform->getSchemaDelimiter();
    }

    /**
     * @param string|null $schema
     *
     * @return void
     */
    #[\Override]
    public function setSchema(?string $schema): void
    {
        if ($this->schema !== $schema && $this->platform) {
            $oldSchema = $this->schema;
            $schemaDelimiter = $this->platform->getSchemaDelimiter();
            $fixHash = function (&$array) use ($schema, $oldSchema, $schemaDelimiter): void {
                foreach ($array as $k => $v) {
                    if ($schema && $this->platform->supportsSchemas()) {
                        if (!str_contains($k, $schemaDelimiter)) {
                            $array[$schema . $schemaDelimiter . $k] = $v;
                            unset($array[$k]);
                        }
                    } elseif ($oldSchema) {
                        if (strpos($k, $schemaDelimiter) !== false) {
                            $array[explode($schemaDelimiter, $k)[1]] = $v;
                            unset($array[$k]);
                        }
                    }
                }
            };

            $fixHash($this->tablesByName);
            $fixHash($this->tablesByLowercaseName);
        }
        parent::setSchema($schema);
    }

    /**
     * Computes the table namespace based on the current relative or
     * absolute table namespace and the database namespace.
     *
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string|null
     */
    private function getCombinedNamespace(Table $table): ?string
    {
        $tableNamespace = $table->getNamespace();

        if ($this->isAbsoluteNamespace($tableNamespace)) {
            return ltrim($tableNamespace, '\\');
        }

        $databaseNamespace = $this->getNamespace();
        if ($this->isAbsoluteNamespace($databaseNamespace)) {
            $databaseNamespace = ltrim($databaseNamespace, '\\');
        }

        if (!$tableNamespace) {
            return $databaseNamespace;
        }
        if ($databaseNamespace) {
            return "$databaseNamespace\\$tableNamespace";
        }

        return $tableNamespace;
    }

    /**
     * @param \Propel\Generator\Model\Schema $parent The parent schema
     *
     * @return void
     */
    public function setParentSchema(Schema $parent): void
    {
        $this->parentSchema = $parent;
    }

    /**
     * @return \Propel\Generator\Model\Schema|null
     */
    public function getParentSchema(): ?Schema
    {
        return $this->parentSchema;
    }

    /**
     * @param \Propel\Generator\Model\TypeMapping|array $data
     *
     * @return \Propel\Generator\Model\TypeMapping
     */
    public function addTypeMapping($data): TypeMapping
    {
        if ($data instanceof TypeMapping) {
            $typeMapping = $data; // alias
            $typeMapping->setDatabase($this);
            $this->typeMapping[$typeMapping->getName()] = $typeMapping;

            return $typeMapping;
        }

        $typeMapping = new TypeMapping();
        $typeMapping->setDatabase($this);
        $typeMapping->loadMapping($data);

        return $this->addTypeMapping($typeMapping); // call self w/ different param
    }

    /**
     * @deprecated Use aptly named {@see static::addTypeMapping()}
     *
     * @param \Propel\Generator\Model\TypeMapping|array $data
     *
     * @return \Propel\Generator\Model\TypeMapping
     */
    public function addDomain($data): TypeMapping
    {
        return $this->addTypeMapping($data);
    }

    /**
     * @param string $name
     *
     * @return \Propel\Generator\Model\TypeMapping|null
     */
    public function getTypeMapping(string $name): ?TypeMapping
    {
        return $this->typeMapping[$name] ?? null;
    }

    /**
     * @deprecated Use aptly named {@see static::getTypeMapping()}
     *
     * @param string $name
     *
     * @return \Propel\Generator\Model\TypeMapping|null
     */
    public function getDomain(string $name): ?TypeMapping
    {
        return $this->getTypeMapping($name);
    }

    /**
     * @return \Propel\Generator\Config\AbstractGeneratorConfig|null
     */
    #[\Override]
    public function getGeneratorConfig(): ?AbstractGeneratorConfig
    {
        return $this->parentSchema?->getGeneratorConfig();
    }

    /**
     * @return string|null
     */
    public function getTablePrefix(): ?string
    {
        return $this->tablePrefix;
    }

    /**
     * @param string $tablePrefix
     *
     * @return void
     */
    public function setTablePrefix(string $tablePrefix): void
    {
        $this->tablePrefix = $tablePrefix;
    }

    /**
     * Returns the next behavior on all tables, ordered by behavior priority,
     * and skipping the ones that were already executed.
     *
     * Behaviors can add other behaviors, so this can't be precomputed.
     *
     * @return \Propel\Generator\Model\Behavior|null
     */
    public function getNextTableBehavior(): ?Behavior
    {
        /** @var \Propel\Generator\Model\Behavior|null $nextBehavior */
        $nextBehavior = null;
        foreach ($this->tables as $table) {
            foreach ($table->getBehaviors() as $behavior) {
                if ($behavior->hasBeenApplied() || ($nextBehavior && $nextBehavior->getTableModificationOrder() <= $behavior->getTableModificationOrder())) {
                    continue;
                }
                $nextBehavior = $behavior;
            }
        }

        return $nextBehavior;
    }

    /**
     * Finalizes the setup process.
     *
     * @return void
     */
    public function doFinalInitialization(): void
    {
        // add the referrers for the foreign keys
        $this->setupTableReferrers();

        // execute database behaviors
        foreach ($this->getBehaviors() as $behavior) {
            $behavior->modifyDatabase();
        }

        // execute table behaviors (may add new tables and new behaviors)
        while ($behavior = $this->getNextTableBehavior()) {
            $behavior->getTableModifier()->modifyTable();
            $behavior->setTableModified(true);
        }

        // do naming and heavy indexing
        foreach ($this->tables as $table) {
            $table->doFinalInitialization();
            // setup referrers again, since final initialization may have added columns
            $table->setupReferrers(true);
        }
    }

    /**
     * @param \Propel\Generator\Model\Behavior $behavior
     *
     * @return void
     */
    #[\Override]
    protected function registerBehavior(Behavior $behavior): void
    {
        $behavior->setDatabase($this);
    }

    /**
     * @return void
     */
    protected function setupTableReferrers(): void
    {
        foreach ($this->tables as $table) {
            $table->setupReferrers();
        }
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        $tables = [];
        foreach ($this->getTables() as $table) {
            $columns = [];
            foreach ($table->getColumns() as $column) {
                $columns[] = sprintf(
                    '      %s %s %s %s %s %s %s',
                    $column->getName(),
                    $column->getColumnType()->name,
                    $column->getSize() ? '(' . $column->getSize() . ')' : '',
                    $column->isPrimaryKey() ? 'PK' : '',
                    $column->isNotNull() ? 'NOT NULL' : '',
                    $column->getDefaultValueString() ? "'" . $column->getDefaultValueString() . "'" : '',
                    $column->isAutoIncrement() ? 'AUTO_INCREMENT' : '',
                );
            }

            $fks = [];
            foreach ($table->getForeignKeys() as $fk) {
                $fks[] = sprintf(
                    '      %s to %s.%s (%s => %s)',
                    $fk->getName(),
                    $fk->getForeignSchemaName(),
                    $fk->getForeignTableCommonName(),
                    implode(', ', $fk->getLocalColumns()),
                    implode(', ', $fk->getForeignColumns()),
                );
            }

            $indices = [];
            foreach ($table->getIndices() as $index) {
                $indexColumns = [];
                foreach ($index->getColumns() as $indexColumnName) {
                    $indexColumns[] = sprintf('%s (%s)', $indexColumnName, $index->getColumnSize($indexColumnName));
                }
                $indices[] = sprintf(
                    '      %s (%s)',
                    $index->getName(),
                    implode(', ', $indexColumns),
                );
            }

            $unices = [];
            foreach ($table->getUnices() as $index) {
                $unices[] = sprintf(
                    '      %s (%s)',
                    $index->getName(),
                    implode(', ', $index->getColumns()),
                );
            }

            $tableDef = sprintf(
                "  %s (%s):\n%s",
                $table->getName(),
                $table->getCommonName(),
                implode("\n", $columns),
            );

            if ($fks) {
                $tableDef .= "\n    FKs:\n" . implode("\n", $fks);
            }

            if ($indices) {
                $tableDef .= "\n    indices:\n" . implode("\n", $indices);
            }

            if ($unices) {
                $tableDef .= "\n    unices:\n" . implode("\n", $unices);
            }

            $tables[] = $tableDef;
        }

        $identifier = $this->getName() . ($this->getSchema() ? '.' . $this->getSchema() : '');
        $properties = implode("\n", $tables);

        return "$identifier:\n$properties";
    }

    /**
     * @param string $defaultAccessorVisibility
     *
     * @return void
     */
    public function setDefaultAccessorVisibility(string $defaultAccessorVisibility): void
    {
        $this->defaultAccessorVisibility = $defaultAccessorVisibility;
    }

    /**
     * @return string
     */
    public function getDefaultAccessorVisibility(): string
    {
        return $this->defaultAccessorVisibility;
    }

    /**
     * @param string $defaultMutatorVisibility
     *
     * @return void
     */
    public function setDefaultMutatorVisibility(string $defaultMutatorVisibility): void
    {
        $this->defaultMutatorVisibility = $defaultMutatorVisibility;
    }

    /**
     * @return string
     */
    public function getDefaultMutatorVisibility(): string
    {
        return $this->defaultMutatorVisibility;
    }

    /**
     * @return void
     */
    public function __clone()
    {
        $tables = [];
        foreach ($this->tables as $oldTable) {
            $table = clone $oldTable;
            $table->setDatabase($this);
            $tables[] = $table;
            $this->tablesByName[$table->getName()] = $table;
            $this->tablesByLowercaseName[strtolower($table->getName())] = $table;
            $this->tablesByPhpName[$table->getPhpName()] = $table;
        }
        $this->tables = $tables;
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
}
