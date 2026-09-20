<?php

declare(strict_types = 1);

namespace Propel\Generator\Model;

use Exception;
use LogicException;
use Propel\Common\Util\SetColumnConverter;
use Propel\Generator\Exception\EngineException;
use Propel\Generator\Exception\SchemaException;
use Propel\Generator\Model\Datatype\ColumnType;
use Propel\Generator\Model\Datatype\PhpDatatype;
use Propel\Generator\Platform\PgsqlPlatform;
use Propel\Generator\Platform\PlatformInterface;
use function addcslashes;
use function count;
use function in_array;
use function lcfirst;
use function rtrim;
use function sprintf;
use function strtolower;
use function strtoupper;

/**
 * A class for holding data about a column used in an application.
 */
class Column extends MappingModel
{
    /**
     * @var \Propel\Generator\Model\Datatype\ColumnType
     */
    public const DEFAULT_TYPE = ColumnType::VARCHAR;

    /**
     * @var string
     */
    public const DEFAULT_VISIBILITY = 'public';

    /**
     * @var string
     */
    public const CONSTANT_PREFIX = 'COL_';

    /**
     * @var array<string>
     */
    public static $validVisibilities = [
        'public',
        'protected',
        'private',
    ];

    private string $name;

    private string|null $description = null;

    private string|null $phpName = null;

    private string|null $phpSingularName = null;

    private string|null $phpNamingMethod = null;

    private bool $isNotNull = false;

    private string|null $namePrefix = null;

    private string|null $accessorVisibility = null;

    private string|null $mutatorVisibility = null;

    private string|null $typeHint = null;

    /**
     * The name to use for the tableMap constant that identifies this column.
     * (Will be converted to all-uppercase in the templates.)
     */
    private string|null $tableMapName = null;

    private TypeMapping $typeMapping;

    private Table|null $parentTable = null;

    private int|null $position = null;

    private bool $isPrimaryKey = false;

    private bool $isNodeKey = false;

    private string $nodeKeySep;

    private bool $isNestedSetLeftKey = false;

    private bool $isNestedSetRightKey = false;

    private bool $isTreeScopeKey = false;

    private bool $isUnique = false;

    private bool $isAutoIncrement = false;

    private bool $isLazyLoad = false;

    private array $referrers = [];

    private bool $isPrimaryString = false;

    // only one type is supported currently, which assumes the
    // column either contains the classnames or a key to
    // classnames specified in the schema.    Others may be
    // supported later.

    private string|null $inheritanceType = null;

    private bool $isInheritance = false;

    private bool $isEnumeratedClasses = false;

    /**
     * @var array<\Propel\Generator\Model\Inheritance>|null
     */
    private array|null $inheritanceList = null;

    /**
     * @param string $name
     * @param \Propel\Generator\Model\Datatype\ColumnType|null $type
     * @param int|null $size
     */
    public function __construct(string $name, ColumnType|null $type = null, int|null $size = null)
    {
        $this->typeMapping = new TypeMapping($type ?? self::DEFAULT_TYPE, null, $size);

        $this->setName($name);
    }

    /**
     * @return string|null
     */
    public function getTypeHint(): ?string
    {
        return $this->typeHint;
    }

    /**
     * @param string|null $typeHint
     *
     * @return void
     */
    public function setTypeHint(?string $typeHint): void
    {
        $this->typeHint = $typeHint;
    }

    /**
     * @param \Propel\Generator\Platform\PlatformInterface|null $platform
     *
     * @throws \LogicException
     *
     * @return \Propel\Generator\Model\TypeMapping
     */
    protected function buildTypeMappingFromAttributes(?PlatformInterface $platform): TypeMapping
    {
        $typeInput = $this->getAttribute('type', static::DEFAULT_TYPE);
        $type = $typeInput instanceof ColumnType ? $typeInput : ColumnType::fromLiteral($typeInput);

        $domainName = $this->getAttribute('domain');
        if ($domainName) {
            $typeMapping = $this->getDatabase()->getTypeMapping($domainName);
            if (!$typeMapping) {
                throw new LogicException("Custom domain '$domainName' not registered in database");
            }
        } else {
            $typeMapping = $platform
                ? $platform->getColumnTypeMapping($type)
                : new TypeMapping($type); // no platform - probably during tests
        }

        $phpType = $this->getAttribute('phpType');
        $typeMapping->setCustomPhpType($phpType);

        $valueSet = $this->resolveValueSetFromAttributes($typeMapping);
        if ($valueSet) {
            $typeMapping->setValueSet($valueSet);
        }

        $sqlType = $this->getAttribute('sqlType');
        if ($sqlType) {
            $typeMapping->setSqlType($sqlType);
        } elseif ($platform && in_array($type, [ColumnType::SET_NATIVE, ColumnType::ENUM_NATIVE], true)) {
            $sqlDeclaration = $platform->buildNativeEnumeratedColumnSqlType($type, $valueSet);
            $typeMapping->setSqlType($sqlDeclaration);
        }

        $requiresSize = $type === ColumnType::VARCHAR
            && !$sqlType
            && $platform
            && !$platform->supportsVarcharWithoutSize();

        $defaultSize = $requiresSize ? 255 : null;
        $size = $this->getAttribute('size', $defaultSize);
        if ($size !== null) {
            $typeMapping->setSize((int)$size);
        }

        $scale = $this->getAttribute('scale');
        if ($scale !== null) {
            $typeMapping->setScale((int)$scale);
        }

        foreach (['defaultValue', 'default', 'defaultExpr'] as $key) {
            $defaultValue = $this->getAttribute($key);
            if ($defaultValue === null || strtolower((string)$defaultValue) === 'null') {
                continue;
            }
            $typeMapping->createDefaultValue($defaultValue, $key === 'defaultExpr');

            break;
        }

        return $typeMapping;
    }

    /**
     * @return array<string>|null
     */
    protected function resolveValueSetFromAttributes(TypeMapping $typeMapping): array|null
    {
        if ($this->getAttribute('valueSet')) {
            $valueSet = $this->getAttribute('valueSet');

            return SetColumnConverter::itemsCsvToArray($valueSet);
        }
        if ($this->getAttribute('valueEnum')) {
            $valueEnumClass = $this->getAttribute('valueEnum');

            return SetColumnConverter::getItemsFromEnum($valueEnumClass);
        }
        if ($typeMapping->isPhpEnumType()) {
            /** @var class-string<\UnitEnum> $enumClass */
            $enumClass = $typeMapping->getCustomPhpType();

            return SetColumnConverter::getItemsFromEnum($enumClass);
        }

        return null;
    }

    /**
     * @throws \Propel\Generator\Exception\EngineException
     * @throws \Propel\Generator\Exception\SchemaException
     *
     * @return void
     */
    #[\Override]
    protected function setupObject(): void
    {
        try {
            $database = $this->getDatabase();
            $platform = ($this->hasPlatform()) ? $this->getPlatform() : null;

            $this->typeMapping = $this->buildTypeMappingFromAttributes($platform);

            $this->name = $this->getAttribute('name');
            $this->phpName = $this->getAttribute('phpName');
            $this->phpSingularName = $this->getAttribute('phpSingularName');
            $this->typeHint = $this->getAttribute('typeHint');
            $this->tableMapName = $this->getAttribute('tableMapName');
            $this->description = $this->getAttribute('description');

            /*
                Retrieves the method for converting from specified name
                to a PHP name, defaulting to parent tables default method.
            */
            $this->phpNamingMethod = $this->getAttribute('phpNamingMethod', $database->getDefaultPhpNamingMethod());

            $this->namePrefix = $this->getAttribute('prefix', $this->parentTable->getAttribute('columnPrefix'));

            // Accessor visibility - no idea why this returns null, or the use case for that
            $visibility = $this->getMethodVisibility('accessorVisibility', 'defaultAccessorVisibility') ?: '';
            $this->setAccessorVisibility($visibility);

            // Mutator visibility
            $visibility = $this->getMethodVisibility('mutatorVisibility', 'defaultMutatorVisibility') ?: '';
            $this->setMutatorVisibility($visibility);

            $this->isPrimaryString = $this->booleanValue($this->getAttribute('primaryString'));

            $this->isPrimaryKey = $this->booleanValue($this->getAttribute('primaryKey'));

            $this->isNodeKey = $this->booleanValue($this->getAttribute('nodeKey'));
            $this->nodeKeySep = $this->getAttribute('nodeKeySep', '.');

            $this->isNestedSetLeftKey = $this->booleanValue($this->getAttribute('nestedSetLeftKey'));
            $this->isNestedSetRightKey = $this->booleanValue($this->getAttribute('nestedSetRightKey'));
            $this->isTreeScopeKey = $this->booleanValue($this->getAttribute('treeScopeKey'));

            $this->isNotNull = ($this->booleanValue($this->getAttribute('required')) || $this->isPrimaryKey); // primary keys are required

            // AutoIncrement/Sequences
            $this->isAutoIncrement = $this->booleanValue($this->getAttribute('autoIncrement'));
            $this->isLazyLoad = $this->booleanValue($this->getAttribute('lazyLoad'));

            $this->inheritanceType = $this->getAttribute('inheritance');

            /*
                here we are only checking for 'false', so don't
                use booleanValue()
            */
            $this->isInheritance = ($this->inheritanceType !== null && $this->inheritanceType !== 'false');
        } catch (Exception $e) {
            throw new EngineException(sprintf(
                'Error setting up column %s: %s',
                $this->getAttribute('name'),
                $e->getMessage(),
            ), 0, $e);
        }

        if ($this->isPhpEnumType() && ($this->isBinaryEnumType() || $this->isBinarySetType())) {
            throw new SchemaException("Column `{$this->getTableName()}.{$this->getName()}`: Combining binary ENUM/SET type with a PHP enum type (`phpType=\"{$this->getPhpType()}\"` is not supported");
        }
    }

    /**
     * Returns the generated methods visibility by looking for the
     * attribute value in the column, parent table or parent database.
     * Finally, it defaults to the default visibility (public).
     *
     * @param string $attribute Local column attribute
     * @param string $parentAttribute Parent (table or database) attribute
     *
     * @return string|null
     */
    private function getMethodVisibility(string $attribute, string $parentAttribute): ?string
    {
        $database = $this->getDatabase();

        $visibility = $this->getAttribute(
            $attribute,
            $this->parentTable->getAttribute(
                $parentAttribute,
                $database->getAttribute(
                    $parentAttribute,
                    self::DEFAULT_VISIBILITY,
                ),
            ),
        );

        return $visibility;
    }

    /**
     * Returns the database object the current column is in.
     *
     * @return \Propel\Generator\Model\Database|null
     */
    private function getDatabase(): ?Database
    {
        return $this->parentTable->getDatabase();
    }

    /**
     * @return \Propel\Generator\Model\TypeMapping
     */
    public function getTypeMapping(): TypeMapping
    {
        return $this->typeMapping;
    }

    /**
     * @deprecated Use aptly named {@see static::getTypeMapping()}
     *
     * @return \Propel\Generator\Model\TypeMapping
     */
    public function getDomain(): TypeMapping
    {
        return $this->getTypeMapping();
    }

    /**
     * @param \Propel\Generator\Model\TypeMapping $mapping
     *
     * @return void
     */
    public function setTypeMapping(TypeMapping $mapping): void
    {
        $this->typeMapping = $mapping;
    }

    /**
     * @deprecated Use {@see static::setTypeMapping()}
     *
     * @param \Propel\Generator\Model\TypeMapping $mapping
     *
     * @return void
     */
    public function setDomain(TypeMapping $mapping): void
    {
        $this->setTypeMapping($mapping);
    }

    /**
     * @return \Propel\Generator\Model\IdMethod|null
     */
    public function getIdMethod(): IdMethod|null
    {
        return $this->isAutoIncrement ? $this->parentTable?->getIdMethod() : null;
    }

    /**
     * Returns the fully qualified column name (table.COLUMN or table.column).
     *
     * @param bool $lowercaseColumnName
     *
     * @return string
     */
    public function getFullyQualifiedName(bool $lowercaseColumnName = false): string
    {
        $columnName = $this->getName();

        return $this->parentTable->getName() . '.' . ($lowercaseColumnName ? $columnName : strtoupper($columnName));
    }

    /**
     * Returns the column name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns the lowercased column name.
     *
     * @return string
     */
    public function getLowercasedName(): string
    {
        return strtolower($this->name);
    }

    /**
     * Returns the uppercased column name.
     *
     * @return string
     */
    public function getUppercasedName(): string
    {
        return strtoupper($this->name);
    }

    /**
     * Sets the column name.
     *
     * @param string $name
     *
     * @return void
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * Returns whether the column name is plural.
     *
     * @return bool
     */
    public function isNamePlural(): bool
    {
        return $this->getSingularName() !== $this->name;
    }

    /**
     * Returns the column singular name.
     *
     * @return string
     */
    public function getSingularName(): string
    {
        if ($this->getAttribute('phpSingularName')) {
            return $this->getAttribute('phpSingularName');
        }

        return rtrim($this->name, 's');
    }

    /**
     * Returns the column description.
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Sets the column description.
     *
     * @param string $description
     *
     * @return void
     */
    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * Returns the name to use in PHP sources. It will set & return
     * a self-generated phpName from its name if its not already set.
     *
     * @return string
     */
    public function getPhpName(): string
    {
        if ($this->phpName === null) {
            $this->phpName = $this->buildPhpName();
        }

        return $this->phpName;
    }

    /**
     * Returns the singular form of the name to use in PHP sources.
     * It will set & return a self-generated phpName from its name
     * if its not already set.
     *
     * @return string
     */
    public function getPhpSingularName(): string
    {
        if ($this->phpSingularName === null) {
            $this->phpSingularName = self::generatePhpSingularName($this->getPhpName());
        }

        return $this->phpSingularName;
    }

    /**
     * Sets the name to use in PHP sources.
     *
     * It will generate a phpName from its name if no
     * $phpName is passed.
     *
     * @param string|null $phpName
     *
     * @return void
     */
    public function setPhpName(?string $phpName = null): void
    {
        $this->phpName = $phpName ?? $this->buildPhpName();
    }

    /**
     * @return bool
     */
    public function hasCustomPhpName(): bool
    {
        return $this->phpName && !$this->namePrefix && $this->phpName !== $this->buildPhpName();
    }

    /**
     * @return string
     */
    public function buildPhpName(): string
    {
        return self::generatePhpName($this->name, $this->phpNamingMethod, $this->namePrefix);
    }

    /**
     * Sets the singular forn of the name to use in PHP
     * sources.
     *
     * It will generate a phpName from its name if no
     * $phpSingularName is passed.
     *
     * @param string|null $phpSingularName
     *
     * @return void
     */
    public function setPhpSingularName(?string $phpSingularName = null): void
    {
        $this->phpSingularName = $phpSingularName ?? self::generatePhpSingularName($this->getPhpName());
    }

    /**
     * Returns the camelCase version of the PHP name.
     *
     * The studly name is the PHP name with the first character lowercase.
     *
     * @return string
     */
    public function getCamelCaseName(): string
    {
        return lcfirst($this->getPhpName());
    }

    /**
     * Returns the accessor methods visibility of this column / attribute.
     *
     * @return string
     */
    public function getAccessorVisibility(): string
    {
        if ($this->accessorVisibility !== null) {
            return $this->accessorVisibility;
        }

        return self::DEFAULT_VISIBILITY;
    }

    /**
     * Sets the accessor methods visibility for this column / attribute.
     *
     * @param string $visibility
     *
     * @return void
     */
    public function setAccessorVisibility(string $visibility): void
    {
        $visibility = strtolower($visibility);
        if (!in_array($visibility, self::$validVisibilities, true)) {
            $visibility = self::DEFAULT_VISIBILITY;
        }

        $this->accessorVisibility = $visibility;
    }

    /**
     * Returns the mutator methods visibility for this current column.
     *
     * @return string
     */
    public function getMutatorVisibility(): string
    {
        if ($this->mutatorVisibility !== null) {
            return $this->mutatorVisibility;
        }

        return self::DEFAULT_VISIBILITY;
    }

    /**
     * Sets the mutator methods visibility for this column / attribute.
     *
     * @param string $visibility
     *
     * @return void
     */
    public function setMutatorVisibility(string $visibility): void
    {
        $visibility = strtolower($visibility);
        if (!in_array($visibility, self::$validVisibilities, true)) {
            $visibility = self::DEFAULT_VISIBILITY;
        }

        $this->mutatorVisibility = $visibility;
    }

    /**
     * Returns the full column constant name (e.g. TableMapName::COL_COLUMN_NAME).
     *
     * @return string A column constant name for insertion into PHP code
     */
    public function getFQConstantName(): string
    {
        $classname = $this->parentTable->getPhpName() . 'TableMap';
        $const = $this->getConstantName();

        return "$classname::$const";
    }

    /**
     * Returns the column constant name (i.e. COL_ID).
     *
     * @return string
     */
    public function getConstantName(): string
    {
        $identifier = $this->getCustomColumnIdentifier() ?: $this->getName();

        return self::CONSTANT_PREFIX . strtoupper($identifier);
    }

    /**
     * @deprecated Use aptly named {@see static::getCustomColumnIdentifier()}
     *
     * @return string|null
     */
    public function getTableMapName(): ?string
    {
        return $this->getCustomColumnIdentifier();
    }

    /**
     * Get custom column identifier to be used in TableMap.
     *
     * @return string|null
     */
    public function getCustomColumnIdentifier(): ?string
    {
        return $this->tableMapName;
    }

    /**
     * Sets the TableMap constant name that will identify this column.
     *
     * @param string $name
     *
     * @return void
     */
    public function setTableMapName(string $name): void
    {
        $this->tableMapName = $name;
    }

    /**
     * @return string
     */
    public function getPhpType(): string
    {
        return $this->typeMapping->resolvePhpType();
    }

    /**
     * Get column index in table (one-based).
     *
     * @return int|null
     */
    public function getPosition(): ?int
    {
        return $this->position;
    }

    /**
     * Set column index in table (one-based).
     *
     * @param int $position
     *
     * @return void
     */
    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return void
     */
    public function setTable(Table $table): void
    {
        $this->parentTable = $table;
    }

    /**
     * @return \Propel\Generator\Model\Table|null
     */
    public function getTable(): ?Table
    {
        return $this->parentTable;
    }

    /**
     * @return string
     */
    public function getTableName(): string
    {
        return $this->parentTable->getName();
    }

    /**
     * Adds a new inheritance definition to the inheritance list and sets the
     * parent column of the inheritance to the current column.
     *
     * @param \Propel\Generator\Model\Inheritance|array $inheritance
     *
     * @return \Propel\Generator\Model\Inheritance
     */
    public function addInheritance($inheritance): Inheritance
    {
        if ($inheritance instanceof Inheritance) {
            $inheritance->setColumn($this);
            if ($this->inheritanceList === null) {
                $this->inheritanceList = [];
                $this->isEnumeratedClasses = true;
            }
            $this->inheritanceList[] = $inheritance;

            return $inheritance;
        }

        $inh = new Inheritance();
        $inh->loadMapping($inheritance);

        return $this->addInheritance($inh);
    }

    /**
     * Returns the inheritance type.
     *
     * @return string|null
     */
    public function getInheritanceType(): ?string
    {
        return $this->inheritanceType;
    }

    /**
     * Returns the inheritance list.
     *
     * @return array<\Propel\Generator\Model\Inheritance>|null
     */
    public function getInheritanceList(): ?array
    {
        return $this->inheritanceList;
    }

    /**
     * Returns the inheritance definitions.
     *
     * @return array<\Propel\Generator\Model\Inheritance>|null
     */
    public function getChildren(): ?array
    {
        return $this->getInheritanceList();
    }

    /**
     * Returns whether this column is a normal property or specifies a
     * the classes that are represented in the table containing this column.
     *
     * @return bool
     */
    public function isInheritance(): bool
    {
        return $this->isInheritance;
    }

    /**
     * Returns whether possible classes have been enumerated in the
     * schema file.
     *
     * @return bool
     */
    public function isEnumeratedClasses(): bool
    {
        return $this->isEnumeratedClasses;
    }

    /**
     * Returns whether the column is not null.
     *
     * @return bool
     */
    public function isNotNull(): bool
    {
        return $this->isNotNull;
    }

    /**
     * Sets whether the column is not null.
     *
     * @param bool $flag
     *
     * @return void
     */
    public function setNotNull(bool $flag): void
    {
        $this->isNotNull = $flag;
    }

    /**
     * Returns NOT NULL string for this column.
     *
     * @return string
     */
    public function getNotNullString(): string
    {
        return $this->parentTable->getPlatform()->getNullString($this->isNotNull);
    }

    /**
     * Sets whether the column is used as the primary string.
     *
     * The primary string is the value used by default in the magic
     * __toString method of an active record object.
     *
     * @param bool $isPrimaryString
     *
     * @return void
     */
    public function setPrimaryString(bool $isPrimaryString): void
    {
        $this->isPrimaryString = $isPrimaryString;
    }

    /**
     * Returns true if the column is the primary string (used for the magic
     * __toString() method).
     *
     * @return bool
     */
    public function isPrimaryString(): bool
    {
        return $this->isPrimaryString;
    }

    /**
     * Sets whether the column is a primary key.
     *
     * @param bool $flag
     *
     * @return void
     */
    public function setPrimaryKey(bool $flag): void
    {
        $this->isPrimaryKey = $flag;
    }

    /**
     * Returns whether the column is the primary key.
     *
     * @return bool
     */
    public function isPrimaryKey(): bool
    {
        return $this->isPrimaryKey;
    }

    /**
     * Sets whether the column is a node key of a tree.
     *
     * @param bool $isNodeKey
     *
     * @return void
     */
    public function setNodeKey(bool $isNodeKey): void
    {
        $this->isNodeKey = $isNodeKey;
    }

    /**
     * Returns whether the column is a node key of a tree.
     *
     * @return bool
     */
    public function isNodeKey(): bool
    {
        return $this->isNodeKey;
    }

    /**
     * Sets the separator for the node key column in a tree.
     *
     * @param string $sep
     *
     * @return void
     */
    public function setNodeKeySep(string $sep): void
    {
        $this->nodeKeySep = $sep;
    }

    /**
     * Returns the node key column separator for a tree.
     *
     * @return string
     */
    public function getNodeKeySep(): string
    {
        return $this->nodeKeySep;
    }

    /**
     * Sets whether the column is the nested set left key of a tree.
     *
     * @param bool $isNestedSetLeftKey
     *
     * @return void
     */
    public function setNestedSetLeftKey(bool $isNestedSetLeftKey): void
    {
        $this->isNestedSetLeftKey = $isNestedSetLeftKey;
    }

    /**
     * Returns whether the column is a nested set key of a tree.
     *
     * @return bool
     */
    public function isNestedSetLeftKey(): bool
    {
        return $this->isNestedSetLeftKey;
    }

    /**
     * Set if the column is the nested set right key of a tree.
     *
     * @param bool $isNestedSetRightKey
     *
     * @return void
     */
    public function setNestedSetRightKey(bool $isNestedSetRightKey): void
    {
        $this->isNestedSetRightKey = $isNestedSetRightKey;
    }

    /**
     * Return whether the column is a nested set right key of a tree.
     *
     * @return bool
     */
    public function isNestedSetRightKey(): bool
    {
        return $this->isNestedSetRightKey;
    }

    /**
     * Sets whether the column is the scope key of a tree.
     *
     * @param bool $isTreeScopeKey
     *
     * @return void
     */
    public function setTreeScopeKey(bool $isTreeScopeKey): void
    {
        $this->isTreeScopeKey = $isTreeScopeKey;
    }

    /**
     * Returns whether the column is a scope key of a tree.
     *
     * @return bool
     */
    public function isTreeScopeKey(): bool
    {
        return $this->isTreeScopeKey;
    }

    /**
     * Returns whether the column must have a unique index.
     *
     * @return bool
     */
    public function isUnique(): bool
    {
        return $this->isUnique;
    }

    /**
     * @deprecated Doesn't seem to be used.
     *
     * @return bool
     */
    public function requiresTransactionInPostgres(): bool
    {
        return PgsqlPlatform::columnTypeRequiresTransaction($this->typeMapping->getColumnType());
    }

    /**
     * Returns whether this column is a foreign key.
     *
     * @return bool
     */
    public function isForeignKey(): bool
    {
        return count($this->getForeignKeys()) > 0;
    }

    /**
     * Returns whether this column is part of more than one foreign key.
     *
     * @return bool
     */
    public function hasMultipleFK(): bool
    {
        return count($this->getForeignKeys()) > 1;
    }

    /**
     * Returns the foreign key objects for this column.
     *
     * Only if it is a foreign key or part of a foreign key.
     *
     * @return array<\Propel\Generator\Model\ForeignKey>
     */
    public function getForeignKeys(): array
    {
        return $this->parentTable->getColumnForeignKeys($this->name);
    }

    /**
     * Adds the foreign key from another table that refers to this column.
     *
     * @param \Propel\Generator\Model\ForeignKey $fk
     *
     * @return void
     */
    public function addReferrer(ForeignKey $fk): void
    {
        $this->referrers[] = $fk;
    }

    /**
     * Returns the list of references to this column.
     *
     * @return array<\Propel\Generator\Model\ForeignKey>
     */
    public function getReferrers(): array
    {
        return $this->referrers;
    }

    /**
     * Returns whether this column has referers.
     *
     * @return bool
     */
    public function hasReferrers(): bool
    {
        return count($this->referrers) > 0;
    }

    /**
     * Returns whether this column has a specific referrer for a
     * specific foreign key object.
     *
     * @param \Propel\Generator\Model\ForeignKey $fk
     *
     * @return bool
     */
    public function hasReferrer(ForeignKey $fk): bool
    {
        return $this->referrers && in_array($fk, $this->referrers, true);
    }

    /**
     * Clears all referrers.
     *
     * @return void
     */
    public function clearReferrers(): void
    {
        $this->referrers = [];
    }

    /**
     * Clears all inheritance children.
     *
     * @return void
     */
    public function clearInheritanceList(): void
    {
        $this->inheritanceList = [];
    }

    /**
     * Sets up type mapping for specified column type.
     *
     * Calling this method will implicitly overwrite any previously set type,
     * size, scale, etc.
     *
     * @param \Propel\Generator\Model\Datatype\ColumnType $columnType
     *
     * @return void
     */
    public function setUpTypeMapping(ColumnType $columnType): void
    {
        $this->typeMapping = $this->getPlatform()->getColumnTypeMapping($columnType);
    }

    /**
     * @deprecated Use {@see static::setUpTypeMapping()}
     *
     * @param \Propel\Generator\Model\Datatype\ColumnType $mappingType
     *
     * @return void
     */
    public function setDomainForType(ColumnType $mappingType): void
    {
        $this->setUpTypeMapping($mappingType);
    }

    /**
     * Sets the mapping column type.
     *
     * @param \Propel\Generator\Model\Datatype\ColumnType $mappingType
     *
     * @return void
     */
    public function setType(ColumnType $mappingType): void
    {
        $this->typeMapping->setColumnType($mappingType);
    }

    /**
     * @see TypeMapping::getColumnType()
     *
     * @return \Propel\Generator\Model\Datatype\ColumnType
     */
    public function getColumnType(): ColumnType
    {
        return $this->typeMapping->getColumnType();
    }

    /**
     * @return string
     */
    public function resolveSqlTypeName(): string
    {
        return $this->typeMapping->resolveSqlTypeName();
    }

    /**
     * @deprecated Use aptly named {@see static::resolveSqlTypeName()}
     *
     * @return string
     */
    public function getSqlType(): string
    {
        return $this->resolveQualifiedType();
    }

    /**
     * Returns the column PDO type integer for this column's mapping type.
     *
     * @return int
     */
    public function getPdoType(): int
    {
        return $this->getColumnType()->toPdoType();
    }

    /**
     * Used to check if SQL column type requires size/scale.
     *
     * @param \Propel\Generator\Platform\PlatformInterface|null $platform
     *
     * @return bool
     */
    public function isDefaultSqlType(?PlatformInterface $platform = null): bool
    {
        $sqlType = $this->typeMapping->getSqlType();
        if (!$platform || !$sqlType) {
            return true;
        }

        $columnType = $this->typeMapping->getColumnType();
        $defaultSqlType = $platform->getColumnTypeMapping($columnType)->resolveSqlTypeName();

        return $defaultSqlType === $sqlType;
    }

    /**
     * @return bool
     */
    public function isLobType(): bool
    {
        return $this->getColumnType()->isLobType();
    }

    /**
     * @return bool
     */
    public function isTextType(): bool
    {
        return $this->getColumnType()->isTextType();
    }

    /**
     * @return bool
     */
    public function isNumericType(): bool
    {
        return $this->getColumnType()->isNumericType();
    }

    /**
     * @return bool
     */
    public function isBooleanType(): bool
    {
        return $this->getColumnType()->isBooleanType();
    }

    /**
     * Returns whether this column is a temporal type.
     *
     * @return bool
     */
    public function isTemporalType(): bool
    {
        return $this->getColumnType()->isTemporalType();
    }

    /**
     * Returns whether this column is a uuid type.
     *
     * @return bool
     */
    public function isUuidType(): bool
    {
        return $this->getColumnType()->isUuidType();
    }

    /**
     * Returns whether this column is a uuid bin type.
     *
     * @return bool
     */
    public function isUuidBinaryType(): bool
    {
        return $this->getColumnType() === ColumnType::UUID_BINARY;
    }

    /**
     * Returns whether the column is an array column.
     *
     * @return bool
     */
    public function isPhpArrayType(): bool
    {
        return $this->getColumnType()->isPhpArrayType();
    }

    /**
     * Returns whether this column uses the valueSet attribute (enums and sets).
     *
     * @return bool
     */
    public function isValueSetType(): bool
    {
        return in_array($this->getColumnType(), [
            ColumnType::ENUM_BINARY,
            ColumnType::ENUM_NATIVE,
            ColumnType::SET_BINARY,
            ColumnType::SET_NATIVE,
        ], true);
    }

    /**
     * @deprecated Use {@see static::isBinaryEnumType}
     *
     * @return bool
     */
    public function isEnumType(): bool
    {
        return $this->isBinaryEnumType();
    }

    /**
     * @deprecated Use {@see static::isBinaryEnumType}
     *
     * @return bool
     */
    public function isSetType(): bool
    {
        return $this->isBinarySetType();
    }

    /**
     * Returns whether this column is an ENUM_BINARY column.
     *
     * @return bool
     */
    public function isBinaryEnumType(): bool
    {
        return $this->getColumnType() === ColumnType::ENUM_BINARY;
    }

    /**
     * Returns whether this column is a SET_BINARY column.
     *
     * @return bool
     */
    public function isBinarySetType(): bool
    {
        return $this->getColumnType() === ColumnType::SET_BINARY;
    }

    /**
     * Sets the list of possible values for an ENUM or SET column.
     *
     * @param array<string>|string $valueSet
     *
     * @return void
     */
    public function setValueSet($valueSet): void
    {
        $this->typeMapping->setValueSet($valueSet);
    }

    /**
     * Returns the list of possible values for an ENUM or SET column.
     *
     * @return array<string>
     */
    public function getValueSet(): array
    {
        return $this->typeMapping->getValueSet();
    }

    /**
     * Returns the column size.
     *
     * @return int|null
     */
    public function getSize(): ?int
    {
        return $this->typeMapping->getSize();
    }

    /**
     * Sets the column size.
     *
     * @param int|null $size
     *
     * @return void
     */
    public function setSize(?int $size): void
    {
        $this->typeMapping->setSize($size);
    }

    /**
     * Returns the column scale.
     *
     * @return int|null
     */
    public function getScale(): ?int
    {
        return $this->typeMapping->getScale();
    }

    /**
     * Sets the column scale.
     *
     * @param int $scale
     *
     * @return void
     */
    public function setScale(int $scale): void
    {
        $this->typeMapping->setScale($scale);
    }

    /**
     * Returns the size and precision in brackets for use in an SQL DLL.
     *
     * Example: (size[,scale]) <-> (10) or (10,2)
     *
     * @return string
     */
    public function getSizeDefinition(): string
    {
        return $this->typeMapping->getSizeDefinition();
    }

    /**
     * Returns true if this table has a default value (and which is not NULL).
     *
     * @return bool
     */
    public function hasDefault(): bool
    {
        return $this->getDefaultValue() !== null;
    }

    /**
     * Check if the column has a default value that is a value (not an expression).
     *
     * @return bool
     */
    public function hasDefaultValue(): bool
    {
        return (bool)$this->getDefaultValue()?->isValueType();
    }

    /**
     * Check if the column has a default value that is an expression (not a value).
     *
     * @return bool
     */
    public function hasDefaultExpression(): bool
    {
        return (bool)$this->getDefaultValue()?->isExpression();
    }

    /**
     * Returns a string that will give this column a default value in PHP.
     *
     * @return string
     */
    public function getDefaultValueString(): string
    {
        $defaultValue = $this->getDefaultValue();

        if ($defaultValue === null) {
            return 'null';
        }

        $value = $defaultValue->getValue();

        if ($this->isNumericType()) {
            return (string)$value;
        }

        if ($this->isTextType() || $this->getDefaultValue()->isExpression()) {
            return "'" . addcslashes((string)$value, "'") . "'";
        }

        if ($this->getColumnType() === ColumnType::BOOLEAN) {
            return $this->booleanValue($value) ? 'true' : 'false';
        }

        return "'$value'";
    }

    /**
     * Sets a string that will give this column a default value.
     *
     * @param \Propel\Generator\Model\ColumnDefaultValue|string|null $defaultValue The column's default value
     *
     * @return void
     */
    public function setDefaultValue($defaultValue): void
    {
        if (!$defaultValue instanceof ColumnDefaultValue) {
            $defaultValue = new ColumnDefaultValue($defaultValue, ColumnDefaultValue::TYPE_VALUE);
        }

        $this->typeMapping->setDefaultValue($defaultValue);
    }

    /**
     * Returns the default value object for this column.
     *
     * @see TypeMapping::getDefaultValue()
     *
     * @return \Propel\Generator\Model\ColumnDefaultValue|null
     */
    public function getDefaultValue(): ?ColumnDefaultValue
    {
        return $this->typeMapping->getDefaultValue();
    }

    /**
     * Returns the default value suitable for use in PHP.
     *
     * @see TypeMapping::getPhpDefaultValue()
     *
     * @return mixed|null
     */
    public function getPhpDefaultValue()
    {
        return $this->typeMapping->getPhpDefaultValue();
    }

    /**
     * Returns whether or the column is an auto increment/sequence value for
     * the target database. We need to pass in the properties for the target
     * database!
     *
     * @return bool
     */
    public function isAutoIncrement(): bool
    {
        return $this->isAutoIncrement;
    }

    /**
     * Return whether the column has to be lazy loaded.
     *
     * For example, if a runtime query on the table doesn't hydrate this column
     * but a getter does.
     *
     * @return bool
     */
    public function isLazyLoad(): bool
    {
        return $this->isLazyLoad;
    }

    /**
     * Returns the auto-increment string.
     *
     * @throws \Propel\Generator\Exception\EngineException
     *
     * @return string|null
     */
    public function buildAutoIncrementString(): string|null
    {
        $idMethod = $this->getIdMethod();
        if (!$idMethod) {
            return null;
        }
        if ($this->isAutoIncrement() && $idMethod === IdMethod::NO_ID_METHOD) {
            $columnName = $this->getFullyQualifiedName();

            throw new EngineException("Column `$columnName` uses auto increment but no idMethod is set on database or table.");
        }
        $autoIncrementClause = $this->getPlatform()->buildAutoIncrementColumnDdl($idMethod, $this);

        if ($autoIncrementClause !== null) {
            return $autoIncrementClause;
        }

        $columnName = $this->getFullyQualifiedName();

        throw new EngineException("Column `$columnName` auto increment id method `$idMethod->value` is not compatible with current platform.");
    }

    /**
     * @param bool $flag
     *
     * @return void
     */
    public function setAutoIncrement(bool $flag): void
    {
        $this->isAutoIncrement = $flag;
    }

    /**
     * Returns whether the column PHP native type is primitive type (aka
     * a boolean, an integer, a long, a float, a double or a string).
     *
     * @return bool
     */
    public function isPhpPrimitiveType(): bool
    {
        return PhpDatatype::isPhpPrimitiveType($this->getPhpType());
    }

    /**
     * Returns whether the column PHP native type is a primitive numeric
     * type (aka an integer, a long, a float or a double).
     *
     * @return bool
     */
    public function isPhpPrimitiveNumericType(): bool
    {
        return PhpDatatype::isPhpPrimitiveNumericType($this->getPhpType());
    }

    /**
     * Returns whether the column PHP native type is an object.
     *
     * @return bool
     */
    public function isPhpObjectType(): bool
    {
        return PhpDatatype::isPhpObjectType($this->getPhpType());
    }

    /**
     * @return bool
     */
    public function isPhpEnumType(): bool
    {
        return $this->typeMapping->isPhpEnumType();
    }

    /**
     * @return bool
     */
    public function isPhpBackedEnumType(): bool
    {
        return $this->typeMapping->isPhpBackedEnumType();
    }

    /**
     * Returns whether this column's phpType is a UnitEnum (non-backed).
     *
     * @return bool
     */
    public function isPhpUnitEnumType(): bool
    {
        return $this->typeMapping->isPhpUnitEnumType();
    }

    /**
     * Returns an instance of PlatformInterface interface.
     *
     * @return \Propel\Generator\Platform\PlatformInterface|null
     */
    public function getPlatform(): ?PlatformInterface
    {
        return $this->parentTable->getPlatform();
    }

    /**
     * Returns whether this column has a platform adapter.
     *
     * @return bool
     */
    public function hasPlatform(): bool
    {
        if ($this->parentTable === null) {
            return false;
        }

        return $this->parentTable->getPlatform() ? true : false;
    }

    /**
     * Clones the current object.
     *
     * @return void
     */
    public function __clone()
    {
        $this->referrers = [];
        $this->typeMapping = clone $this->typeMapping;
    }

    /**
     * Returns a generated PHP name.
     *
     * @param string $name
     * @param string|null $phpNamingMethod
     * @param string|null $namePrefix
     *
     * @return string
     */
    public static function generatePhpName(string $name, ?string $phpNamingMethod = null, ?string $namePrefix = null): string
    {
        if ($phpNamingMethod === null) {
            $phpNamingMethod = NameGeneratorInterface::CONV_METHOD_CLEAN;
        }

        return NameFactory::generateName(NameFactory::PHP_GENERATOR, [$name, $phpNamingMethod, (string)$namePrefix]);
    }

    /**
     * Generates the singular form of a PHP name.
     *
     * @param string $phpName
     *
     * @return string
     */
    public static function generatePhpSingularName(string $phpName): string
    {
        return rtrim($phpName, 's');
    }

    /**
     * Checks if xml attributes from schema.xml matches expected content declaration.
     *
     * @param string $content
     *
     * @return bool
     */
    public function isContent(string $content): bool
    {
        $contentAttribute = $this->getAttribute('content');

        return $contentAttribute && strtoupper($contentAttribute) === strtoupper($content);
    }

    /**
     * @return string
     */
    public function resolveQualifiedType(): string
    {
        $typeHint = $this->getTypeHint();
        if ($typeHint) {
            return $typeHint;
        } elseif ($this->getColumnType() === ColumnType::OBJECT) {
            return 'mixed';
        } elseif ($this->isPhpArrayType()) {
            return 'string';
        }
        $phpType = $this->getPhpType();
        if ($this->isLobType()) {
            return $phpType && $phpType !== 'string' ? "$phpType|string" : 'string';
        } elseif ($phpType && PhpDatatype::isPhpObjectType($phpType) && $phpType !== 'stdClass') {
            return $phpType[0] === '\\' ? $phpType : "\\$phpType";
        } elseif ($phpType) {
            return $phpType;
        } else {
            return 'mixed';
        }
    }
}
