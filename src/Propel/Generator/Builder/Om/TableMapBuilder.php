<?php

declare(strict_types = 1);

namespace Propel\Generator\Builder\Om;

use Propel\Generator\Builder\Om\InstancePoolCodeProducer\InstancePoolCodeProducer;
use Propel\Generator\Builder\Om\TableMapBuilder\TableMapBuilderValidation;
use Propel\Generator\Builder\Util\EntityObjectClassNames;
use Propel\Generator\Config\AbstractGeneratorConfig;
use Propel\Generator\Model\Column;
use Propel\Generator\Model\ForeignKey;
use Propel\Generator\Model\IdMethod;
use Propel\Generator\Model\PropelTypes;
use Propel\Generator\Model\Table;
use Propel\Generator\Platform\PgsqlPlatform;
use Propel\Generator\Platform\PlatformInterface;
use Propel\Runtime\Exception\LogicException as RuntimeLogicException;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Map\RelationMap;
use Propel\Runtime\Map\TableMap;
use function addslashes;
use function array_filter;
use function array_keys;
use function array_map;
use function array_shift;
use function array_unique;
use function array_walk;
use function count;
use function end;
use function implode;
use function in_array;
use function is_array;
use function lcfirst;
use function preg_replace;
use function strtoupper;
use function var_export;
use const PHP_EOL;

/**
 * Generates the table map class for user object model (OM).
 */
class TableMapBuilder extends AbstractOMBuilder
{
    /**
     * @var \Propel\Generator\Builder\Om\BuilderType|null
     */
    public const BUILDER_TYPE = BuilderType::TableMap;

    protected EntityObjectClassNames $tableNames;

    /**
     * @var \Propel\Generator\Builder\Om\InstancePoolCodeProducer\InstancePoolCodeProducer<static>
     */
    protected $instancePoolCodeBuilder;

    /**
     * @param \Propel\Generator\Model\Table $table
     */
    public function __construct(Table $table)
    {
        parent::__construct($table);
        $this->tableNames = $this->referencedClasses->useEntityObjectClassNames($table);
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     * @param \Propel\Generator\Config\AbstractGeneratorConfig $generatorConfig
     *
     * @return void
     */
    #[\Override()]
    protected function onGeneratorConfigAvailable(Table $table, AbstractGeneratorConfig $generatorConfig): void
    {
        parent::onGeneratorConfigAvailable($table, $generatorConfig);
        $this->instancePoolCodeBuilder = new InstancePoolCodeProducer($table, $this);
    }

    /**
     * @return void
     */
    #[\Override]
    protected function validateModel(): void
    {
        parent::validateModel();
        $this->disallowImplicitCollectionReplacement();
    }

    /**
     * @return void
     */
    protected function disallowImplicitCollectionReplacement(): void
    {
        TableMapBuilderValidation::validate($this);
    }

    /**
     * Gets the package for the map builder classes.
     *
     * @return string
     */
    #[\Override]
    public function getPackage(): string
    {
        return parent::getPackage() . '.Map';
    }

    /**
     * @return string|null
     */
    #[\Override]
    public function getNamespace(): ?string
    {
        $namespace = parent::getNamespace();
        if (!$namespace) {
            return 'Map';
        }

        $namespaceMap = $this->getBuildPropertyString('generator.objectModel.namespaceMap');
        if (!$namespaceMap) {
            return $namespace . 'Map';
        }

        return "$namespace\\$namespaceMap";
    }

    /**
     * @return string
     */
    public function getBaseTableMapClassName(): string
    {
        return 'TableMap';
    }

    /**
     * Returns the name of the current class being built.
     *
     * @return string
     */
    #[\Override]
    public function getUnprefixedClassName(): string
    {
        return $this->getTable()->getPhpName() . 'TableMap';
    }

    /**
     * Adds class phpdoc comment and opening of class.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    #[\Override]
    protected function addClassOpen(string &$script): void
    {
        $table = $this->getTable();
        $tableName = $table->getName();
        $timeStampBlock = $this->generateTimestampBlock();
        $className = $this->getUnqualifiedClassName();

        $script .= "
/**
 * This class defines the structure of the '$tableName' table.
 *{$timeStampBlock}
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 */
class $className extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;

";
    }

    /**
     * Specifies the methods that are added as part of the map builder class.
     * This can be overridden by subclasses that wish to add more methods.
     *
     * @see ObjectBuilder::addClassBody()
     *
     * @param string $script
     *
     * @return void
     */
    #[\Override]
    protected function addClassBody(string &$script): void
    {
        $table = $this->getTable();

        $this->declareClasses(
            '\Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\LocalColumnExpression',
            '\Propel\Runtime\ActiveQuery\InstancePoolTrait',
            '\Propel\Runtime\Map\TableMap',
            '\Propel\Runtime\Map\TableMapTrait',
            '\Propel\Runtime\ActiveQuery\Criteria',
            '\Propel\Runtime\Connection\ConnectionInterface',
            '\Propel\Runtime\DataFetcher\DataFetcherInterface',
            '\Propel\Runtime\Propel',
        );

        $script .= $this->addConstants();

        $this->addInheritanceColumnConstants($script);
        if ($table->hasValueSetColumns()) {
            $this->addValueSetColumnConstants($script);
        }

        $this->applyBehaviorModifier('staticConstants', $script, '    ');
        $this->applyBehaviorModifier('staticAttributes', $script, '    ');
        $this->applyBehaviorModifier('staticMethods', $script, '    ');

        $this->addAttributes($script);

        $script .= $this->addFieldsAttributes();
        $this->addNormalizedColumnNameMap($script);

        if ($table->hasValueSetColumns()) {
            $this->addValueSetColumnAttributes($script);
            $this->addGetValueSets($script);
            $this->addGetValueSet($script);
        }

        $this->addInitialize($script);
        $this->addBuildRelations($script);
        $this->addGetBehaviors($script);

        $script .= $this->addInstancePool();
        $script .= $this->addClearRelatedInstancePool();

        $this->addGetPrimaryKeyHashFromRow($script);
        $this->addGetPrimaryKeyHashFromObject($script);
        $this->addGetPrimaryKeyFromRow($script);

        $this->addGetOMClassMethod($script);
        $this->addPopulateObject($script);
        $this->addPopulateObjects($script);

        if (!$table->isAlias()) {
            $this->addSelectMethods($script);
            $this->addGetTableMap($script);
        }

        $this->addDoDelete($script);
        $this->addDoDeleteAll($script);

        $this->addDoInsert($script);
    }

    /**
     * Adds the addSelectColumns(), doCount(), etc. methods.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addSelectMethods(string &$script): void
    {
        $this->addAddSelectColumns($script);
        $this->addRemoveSelectColumns($script);
    }

    /**
     * Adds any constants needed for this TableMap class.
     *
     * @return string
     */
    protected function addConstants(): string
    {
        $table = $this->getTable();
        $stubObjectBuilder = $this->getStubObjectBuilder();
        $collectionBuilder = $this->getObjectCollectionBuilder();

        return $this->renderTemplate('tableMapConstants', [
            'className' => $this->getClasspath(),
            'dbName' => $this->getDatabase()->getName(),
            'tableName' => $table->getName(),
            'tablePhpName' => $table->getPhpName(),
            'omClassName' => $this->declareClassFromBuilder($stubObjectBuilder),
            'omClassNameFq' => $stubObjectBuilder->getFullyQualifiedClassName(),
            'isAbstract' => $table->isAbstract(),
            'stubClassPath' => $stubObjectBuilder->getClasspath(),
            'nbColumns' => $table->getNumColumns(),
            'nbLazyLoadColumns' => $table->getNumLazyLoadColumns(),
            'nbHydrateColumns' => $table->getNumColumns() - $table->getNumLazyLoadColumns(),
            'columns' => $table->getColumns(),
            'stringFormat' => $table->getDefaultStringFormat(),
            'objectCollectionClassName' => $this->declareClass($collectionBuilder->resolveTableCollectionClassNameFq()),
            'objectCollectionType' => $collectionBuilder->resolveTableCollectionClassType(),
        ]);
    }

    /**
     * Adds the COLUMN_NAME constant to the class definition.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addColumnNameConstants(string &$script): void
    {
        $tableName = $this->getTable()->getName();

        foreach ($this->getTable()->getColumns() as $column) {
            $columnName = $column->getName();
            $columnConstant = $column->getConstantName();
            $qualifiedName = $column->getFullyQualifiedName(true);

            $script .= "
    /**
     * The column name for the $columnName field
     */
    public const {$columnConstant} = '$qualifiedName';\n";
        }
    }

    /**
     * Adds the valueSet constants for ENUM_BINARY and SET_BINARY columns.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addValueSetColumnConstants(string &$script): void
    {
        foreach ($this->getTable()->getColumns() as $column) {
            if (!$column->isValueSetType()) {
                continue;
            }
            $columnName = $column->getName();
            $columnConstant = $column->getConstantName();
            $script .= "
    // The enumerated values for the $columnName field";

            foreach ($column->getValueSet() as $value) {
                $valueSetConstant = $this->getValueSetConstant($value);
                $script .= "

    /**
     * @var string
     */
    public const {$columnConstant}_{$valueSetConstant} = '$value';";
            }
            $script .= "\n";
        }
    }

    /**
     * Adds the valueSet attributes for ENUM_BINARY columns.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addValueSetColumnAttributes(string &$script): void
    {
        $script .= "
    /**
     * The enumerated values for this table
     *
     * @var array<string, array<string>>
     */
    protected static \$enumValueSets = [";
        foreach ($this->getTable()->getColumns() as $col) {
            if (!$col->isValueSetType()) {
                continue;
            }
            $columnConstant = $col->getConstantName();
            $columnConstantFq = $this->getColumnConstant($col, 'self');
            $valueSetConstants = array_map([$this, 'getValueSetConstant'], $col->getValueSet());
            $indent = '            ';
            $values = array_map(fn ($valueSetConstant) => "\n{$indent}self::{$columnConstant}_{$valueSetConstant},", $valueSetConstants);
            $valuesCsv = implode('', $values);

            $script .= "
        $columnConstantFq => [$valuesCsv
        ],";
        }
        $script .= "
    ];\n";
    }

    /**
     * Adds the getValueSets() method.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addGetValueSets(string &$script): void
    {
        $script .= "
    /**
     * Gets the list of values for all ENUM_BINARY and SET_BINARY columns
     *
     * @return array<string, array<string>>
     */
    public static function getValueSets(): array
    {
        return static::\$enumValueSets;
    }\n";
    }

    /**
     * Adds the getValueSet() method.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addGetValueSet(string &$script): void
    {
        $script .= "
    /**
     * Gets the list of values for an ENUM or SET column
     *
     * @param string \$colname
     *
     * @return array<string> list of possible values for the column
     */
    public static function getValueSet(string \$colname): array
    {
        return static::\$enumValueSets[\$colname];
    }\n";
    }

    /**
     * Adds the CLASSKEY_* and CLASSNAME_* constants used for inheritance.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    public function addInheritanceColumnConstants(string &$script): void
    {
        $col = $this->getTable()->getChildrenColumn();
        if (!$col || !$col->isEnumeratedClasses()) {
            return;
        }

        $classKeyColumnName = $col->getName();
        $isNumericKey = $col->isNumericType() && $col->getType() !== PropelTypes::DECIMAL;
        $type = $isNumericKey ? 'int' : 'string';
        $keyToClassName = [];

        foreach ($col->getChildren() as $child) {
            $childBuilder = $this->getObjectInheritanceStubBuilder();
            $childBuilder->setChild($child);
            $fqcn = $childBuilder->getFullyQualifiedClassName();
            $className = $this->declareClassFromBuilder($childBuilder);
            $rawClassName = $child->getClassName();

            $suffix = $child->getConstantSuffix();
            $key = $isNumericKey ? $child->getKey() : "'" . $child->getKey() . "'";

            $keyToClassName[$key] = $className;

            $script .= "
    /**
     * Values used in [$classKeyColumnName] column to identify object class.
     *
     * @var $type 
     */
    public const CLASSKEY_{$suffix} = $key;

    /**
     * @deprecated Get class from {@see static::\$objectClassLookup}.
     *
     * @var class-string<$fqcn>
     */
    public const CLASSNAME_{$suffix} = $className::class;\n";

            if (strtoupper($rawClassName) !== $suffix) {
                $childClassLiteral = strtoupper($rawClassName);
                $script .= "
    /**
     * Child model class for model objects with value `$suffix` in [$classKeyColumnName] column.
     *
     * @var class-string<$fqcn>
     */
    public const CLASSKEY_{$childClassLiteral} = $className::class;\n";
            }
        }

        $script .= "
    /**
     * Maps values of [$classKeyColumnName] column to the corresponding entity class.
     *
     * @var array<$type, class-string>
     */
    protected static \$objectClassLookup = [";
        foreach ($keyToClassName as $key => $className) {
            $script .= "
        $key => $className::class,";
        }
        $script .= "
    ];\n";
    }

    /**
     * @param string $value
     *
     * @return string
     */
    protected function getValueSetConstant(string $value): string
    {
        return strtoupper(preg_replace('/[^a-zA-Z0-9_\x7f-\xff]/', '_', $value));
    }

    /**
     * Adds any attributes needed for this TableMap class.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addAttributes(string &$script): void
    {
    }

    /**
     * @return string
     */
    protected function addFieldsAttributes(): string
    {
        $tableColumns = $this->getTable()->getColumns();
        $map = fn (callable $fun, bool $quoted = true) => array_map($quoted ? fn ($s) => "'{$fun($s)}'" : $fun, $tableColumns);

        $phpNames = $map(fn (Column $col) => $col->getPhpName());
        $camelCaseNames = $map(fn (Column $col) => $col->getCamelCaseName());
        $colnames = $map(fn (Column $col) => $this->getColumnConstant($col, 'self'), false);
        $rawColnames = $map(fn (Column $col) => $col->getConstantName());
        $fieldNames = $map(fn (Column $col) => $col->getName());
        $fieldIndexes = array_keys($tableColumns);

        $toIndexMap = fn (array $keys) => array_map(fn (string $key, int $index) => "$key => $index", $keys, $fieldIndexes);

        return $this->renderTemplate('tableMapFields', [
            'fieldNamesPhpName' => implode(', ', $phpNames),
            'fieldNamesCamelCaseName' => implode(', ', $camelCaseNames),
            'fieldNamesColname' => implode(', ', $colnames),
            'fieldNamesRawColname' => implode(', ', $rawColnames),
            'fieldNamesFieldName' => implode(', ', $fieldNames),
            'fieldNamesNum' => implode(', ', $fieldIndexes),
            'fieldKeysPhpName' => implode(', ', $toIndexMap($phpNames)),
            'fieldKeysCamelCaseName' => implode(', ', $toIndexMap($camelCaseNames)),
            'fieldKeysColname' => implode(', ', $toIndexMap($colnames)),
            'fieldKeysRawColname' => implode(', ', $toIndexMap($rawColnames)),
            'fieldKeysFieldName' => implode(', ', $toIndexMap($fieldNames)),
            'fieldKeysNum' => implode(', ', $fieldIndexes),
        ]);
    }

    /**
     * @param string $script
     *
     * @return void
     */
    protected function addNormalizedColumnNameMap(string &$script): void
    {
        $table = $this->getTable();
        $tableColumns = $table->getColumns();

        $arrayString = '';
        foreach ($tableColumns as $column) {
            $variants = [
                $column->getPhpName(), // ColumnName => COLUMN_NAME
                $table->getPhpName() . '.' . $column->getPhpName(), // TableName.ColumnName => COLUMN_NAME
                $column->getCamelCaseName(), // columnName => COLUMN_NAME
                $table->getCamelCaseName() . '.' . $column->getCamelCaseName(), // tableName.columnName => COLUMN_NAME
                $this->getColumnConstant($column, $this->getTableMapClass()), // TableNameTableMap::COL_COLUMN_NAME => COLUMN_NAME
                $column->getConstantName(), // COL_COLUMN_NAME => COLUMN_NAME
                $column->getName(), // column_name => COLUMN_NAME
                $table->getName() . '.' . $column->getName(), // table_name.column_name => COLUMN_NAME
            ];

            $variants = array_unique($variants);

            $normalizedName = strtoupper($column->getName());
            array_walk($variants, static function ($variant) use (&$arrayString, $normalizedName): void {
                $arrayString .= PHP_EOL . "        '{$variant}' => '{$normalizedName}',";
            });
        }

        $script .= '
    /**
     * Holds a list of column names and their normalized version.
     *
     * @var array<string, string>
     */
    protected $normalizedColumnNameMap = [' . $arrayString . PHP_EOL
            . '    ];' . PHP_EOL;
    }

    /**
     * Closes class.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    #[\Override]
    protected function addClassClose(string &$script): void
    {
        $script .= "}\n";
        $this->applyBehaviorModifier('tableMapFilter', $script, '');
    }

    /**
     * Adds the addInitialize() method to the table map class.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addInitialize(string &$script): void
    {
        $table = $this->getTable();
        /** @var \Propel\Generator\Platform\DefaultPlatform $platform */
        $platform = $this->getPlatform();

        $script .= "
    /**
     * Initialize the table attributes and columns
     * Relations are not initialized by this method since they are lazy loaded
     *
     * @return void
     */
    public function initialize(): void
    {
        // attributes
        \$this->setName('" . $table->getName() . "');
        \$this->setPhpName('" . $table->getPhpName() . "');
        \$this->setIdentifierQuoting(" . ($table->isIdentifierQuotingEnabled() ? 'true' : 'false') . ");
        \$this->setClassName('" . addslashes($this->getStubObjectBuilder()->getFullyQualifiedClassName()) . "');
        \$this->setPackage('" . parent::getPackage() . "');";
        if ($table->getIdMethod() === 'native') {
            $script .= "
        \$this->setUseIdGenerator(true);";
        } else {
            $script .= "
        \$this->setUseIdGenerator(false);";
        }

        if ($table->getIdMethodParameters()) {
            $params = $table->getIdMethodParameters();
            $imp = $params[0];
            $script .= "
        \$this->setPrimaryKeyMethodInfo('" . $imp->getValue() . "');";
        } elseif ($table->getIdMethod() == IdMethod::NATIVE && ($platform->getNativeIdMethod() == PlatformInterface::SEQUENCE || $platform->getNativeIdMethod() == PlatformInterface::SERIAL)) {
            $script .= "
        \$this->setPrimaryKeyMethodInfo('" . $platform->getSequenceName($table) . "');";
        }

        if ($this->getTable()->getChildrenColumn()) {
            $script .= "
        \$this->setSingleTableInheritance(true);";
        }

        if ($this->getTable()->getIsCrossRef()) {
            $script .= "
        \$this->setIsCrossRef(true);";
        }

        // Add columns to map
        $script .= "
        // columns";
        foreach ($table->getColumns() as $col) {
            $columnName = $col->getName();
            $phpName = $col->getPhpName();
            $size = $col->getSize() ?: 'null';
            $default = $col->getDefaultValueString();
            $columnType = $col->getType();
            $isNotNull = $col->isNotNull() ? 'true' : 'false';

            if (!$col->isForeignKey()) {
                $method = $col->isPrimaryKey() ? 'addPrimaryKey' : 'addColumn';
                $script .= "
        \$this->$method('$columnName', '$phpName', '$columnType', $isNotNull, $size, $default);";
            } else {
                $method = $col->isPrimaryKey() ? 'addForeignPrimaryKey' : 'addForeignKey';
                foreach ($col->getForeignKeys() as $fk) {
                    $foreignTableName = $fk->getForeignTableName();
                    $mappedForeignColumn = $fk->getMappedForeignColumn($col->getName());
                    $script .= "
        \$this->$method('$columnName', '$phpName', '$columnType', '$foreignTableName', '$mappedForeignColumn', $isNotNull, $size, $default);";
                }
            }

            if ($col->isValueSetType()) {
                $valueSet = '[\'' . implode('\', \'', $col->getValueSet()) . '\']';
                $script .= "
        \$this->getColumn('$columnName')->setValueSet($valueSet);";
            }

            if ($col->isPrimaryString()) {
                $script .= "
        \$this->getColumn('$columnName')->setPrimaryString(true);";
            }
        }

        $script .= "
    }\n";
    }

    /**
     * Adds the method that build the RelationMap objects
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addBuildRelations(string &$script): void
    {
        $addRelationStatements = '';

        foreach ($this->getTable()->getForeignKeys() as $fkey) {
            $relationName = $fkey->getIdentifier();
            $addRelationStatements .= $this->buildAddRelationStatement($relationName, $fkey, 'RelationMap::MANY_TO_ONE', false, false, 'null');
        }

        foreach ($this->getTable()->getReferrers() as $fkey) {
            $relationName = $fkey->getIdentifierReversed();
            $cardinalityConstant = 'RelationMap::ONE_TO_' . ($fkey->isLocalPrimaryKey() ? 'ONE' : 'MANY');
            $pluralName = $fkey->isLocalPrimaryKey() ? 'null' : "'" . $this->getRefFKPhpNameAffix($fkey, true) . "'";
            $addRelationStatements .= $this->buildAddRelationStatement($relationName, $fkey, $cardinalityConstant, true, false, $pluralName);
        }

        foreach ($this->getTable()->getCrossRelations() as $crossFKs) {
            foreach ($crossFKs->getCrossForeignKeys() as $fk) {
                $relationName = $fk->getIdentifier();
                $pluralName = "'" . $this->getFKPhpNameAffix($fk, true) . "'";
                $addRelationStatements .= $this->buildAddRelationStatement($relationName, $fk, 'RelationMap::MANY_TO_MANY', false, true, $pluralName);
            }
        }

        if ($addRelationStatements) {
            $this->declareClass(RelationMap::class);
        }

        $script .= "
    /**
     * Build the RelationMap objects for this table relationships
     *
     * @return void
     */
    public function buildRelations(): void
    {{$addRelationStatements}
    }
";
    }

    /**
     * @param string $relationName
     * @param \Propel\Generator\Model\ForeignKey $fkey
     * @param string $cardinalityConstant
     * @param bool $isBack
     * @param bool $isCrossFk
     * @param string $pluralName
     *
     * @return string
     */
    protected function buildAddRelationStatement(
        string $relationName,
        ForeignKey $fkey,
        string $cardinalityConstant,
        bool $isBack,
        bool $isCrossFk,
        string $pluralName
    ): string {
        $table = $isBack ? $fkey->getTable() : $fkey->getForeignTable();
        $fkTableNameFq = addslashes($this->getStubObjectBuilder($table)->getFullyQualifiedClassName());
        $joinCondition = $isCrossFk ? '[]' : $this->arrayToString($fkey->getNormalizedMap($fkey->getMapping()));
        $onDelete = $fkey->hasOnDelete() ? "'{$fkey->getOnDelete()}'" : 'null';
        $onUpdate = $fkey->hasOnUpdate() ? "'{$fkey->getOnUpdate()}'" : 'null';
        $isPolymorphic = !$isCrossFk && $fkey->isPolymorphic() ? 'true' : 'false';

        return "
        \$this->addRelation(
            '$relationName',
            '$fkTableNameFq',
            $cardinalityConstant,
            $joinCondition,
            $onDelete,
            $onUpdate,
            $pluralName,
            $isPolymorphic,
        );";
    }

    /**
     * Adds the behaviors getter
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addGetBehaviors(string &$script): void
    {
        $behaviors = $this->getTable()->getBehaviors();
        if (!$behaviors) {
            return;
        }

        $stringifiedBehaviors = [];
        foreach ($behaviors as $behavior) {
            $id = $behavior->getId();
            $params = $this->stringify($behavior->getParameters());
            $stringifiedBehaviors[] = "'$id' => $params,";
        }
        $itemsString = implode(PHP_EOL . '            ', $stringifiedBehaviors);

        $script .= "
    /**
     *
     * Gets the list of behaviors registered for this table
     *
     * @return array<string, array> Associative array (name => parameters) of behaviors
     */
    public function getBehaviors(): array
    {
        return [
            $itemsString
        ];
    }\n";
    }

    /**
     * @param array|string|float|int|bool|null $value
     *
     * @return string
     */
    protected function stringify($value): string
    {
        if (!is_array($value)) {
            return var_export($value, true);
        }

        $items = [];
        foreach ($value as $key => $arrayValue) {
            $keyString = var_export($key, true);
            $valString = $this->stringify($arrayValue);
            $items[] = "$keyString => $valString";
        }
        $itemsCsv = implode(', ', $items);

        return "[$itemsCsv]";
    }

    /**
     * @return string
     */
    public function addInstancePool(): string
    {
        $pks = $this->getTable()->getPrimaryKey();
        if (!$pks) {
            return '';
        }

        return $this->renderTemplate('tableMapInstancePool', [
            'modelClassName' => $this->tableNames->useObjectStubClassName(),
            'modelClassNameFq' => $this->tableNames->useObjectStubClassName(false),
            'pkType' => $this->getTable()->getPrimaryKeyDocType(false),
            'poolKeyFromObjectStatementFormat' => $this->instancePoolCodeBuilder->buildPoolKeyFromObjectVariable('%1$s'),
            'poolKeyFromRowStatementFormat' => $this->instancePoolCodeBuilder->buildPoolKeyFromArrayAccess('%1$s', true, null),
        ]);
    }

    /**
     * @return string
     */
    public function addClearRelatedInstancePool(): string
    {
        $table = $this->getTable();
        $relatedTableMapClassNames = [];

        // Handle ON DELETE CASCADE for updating instance pool
        foreach ($table->getReferrers() as $fk) {
            $relatedTable = $fk->getTable();
            if ($relatedTable->isForReferenceOnly()) {
                continue;
            }

            $joinedTableTableMapBuilder = $this->getTableMapBuilder($relatedTable);
            $tableMapClassName = $joinedTableTableMapBuilder === $this ? 'static' : $this->declareClassFromBuilder($joinedTableTableMapBuilder, true);

            if (in_array($fk->getOnDelete(), [ForeignKey::CASCADE, ForeignKey::SETNULL])) {
                $relatedTableMapClassNames[] = $tableMapClassName;
            }
        }

        return $this->renderTemplate('tableMapClearRelatedInstancePool', [
            'tableName' => $this->getObjectClassName(),
            'relatedTableMapClassNames' => $relatedTableMapClassNames,
        ]);
    }

    /**
     * Checks whether any registered behavior on that table has a modifier for a hook
     *
     * @param string $hookName The name of the hook as called from one of this class methods, e.g. "preSave"
     * @param string $modifier
     *
     * @return bool
     */
    #[\Override]
    public function hasBehaviorModifier(string $hookName, string $modifier = ''): bool
    {
        return parent::hasBehaviorModifier($hookName, 'TableMapBuilderModifier');
    }

    /**
     * Checks whether any registered behavior on that table has a modifier for a hook
     *
     * @param string $hookName The name of the hook as called from one of this class methods, e.g. "preSave"
     * @param string $script The script will be modified in this method.
     * @param string $tab
     *
     * @return void
     */
    public function applyBehaviorModifier(string $hookName, string &$script, string $tab = '        '): void
    {
        $this->applyBehaviorModifierBase($hookName, 'TableMapBuilderModifier', $script, $tab);
    }

    /**
     * Adds method to get a version of the primary key that can be used as a unique key for identifier map.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addGetPrimaryKeyHashFromRow(string &$script): void
    {
        $columns = array_filter($this->getTable()->getEagerColumns(), fn (Column $column) => $column->isPrimaryKey());

        $script .= "
    /**
     * Returns a serialized version of the primary key as unique identifier of the row.
     *
     * @param array<mixed> \$row Resultset row.
     * @param int \$offset The 0-based offset for reading from the resultset row.
     * @param string \$indexType One of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_CAMELNAME
     *                           TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM
     *
     * @return string|null The primary key hash of the row or null if value cannot be resolved
     */
    public static function getPrimaryKeyHashFromRow(array \$row, int \$offset = 0, string \$indexType = TableMap::TYPE_NUM): ?string
    {";
        if (count($columns) === 0) {
            $script .= "
        return null;
    }\n";

            return;
        }

        $this->declareClass(TableMap::class);

        $varNames = [];
        foreach ($columns as $index => $column) {
            $phpName = $column->getPhpName();
            $varName = '$' . lcfirst($phpName);
            $varNames[] = $varName;

            $script .= "
        $varName = \$row[\$indexType === TableMap::TYPE_NUM ? $index + \$offset : static::translateFieldName('$phpName', TableMap::TYPE_PHPNAME, \$indexType)];";

            if (
                $column->getType() === PropelTypes::OBJECT
                || ($this->getPlatform() instanceof PgsqlPlatform && $column->getType() === PropelTypes::UUID_BINARY )
            ) {
                $this->declareGlobalFunction('is_resource', 'stream_get_contents', 'is_callable');
                $script .= "
        if (is_resource($varName)) {
            \$resourceValue = stream_get_contents($varName);
            rewind($varName);
            $varName =  is_callable([\$resourceValue, '__toString']) ? (string)\$resourceValue : \$resourceValue;
        }";
            } elseif (!$column->isTextType() && $column->isPhpPrimitiveType() && !$column->isUuidBinaryType()) {
                $script .= "
        $varName = $varName === null ? null : (string)$varName;";
            }
            $script .= "\n";
        }

        if (count($varNames) === 1) {
            $nullOrKeyExpression = $varNames[0];
        } else {
            $this->declareGlobalFunction('serialize');
            $isNullConjunction = implode(' === null && ', $varNames) . ' === null';
            $varNamesCsv = implode(', ', $varNames);

            $nullOrKeyExpression = "$isNullConjunction ? null : serialize([$varNamesCsv])";
        }

        $script .= "
        return $nullOrKeyExpression;
    }\n";
    }

    /**
     * Adds method to get a version of the primary key that can be used as a unique key for identifier map.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addGetPrimaryKeyHashFromObject(string &$script): void
    {
        $modelClass = $this->getObjectClassName();
        $modelClassFq = $this->getObjectClassName(true);
        $objectVar = '$' . lcfirst($modelClass);
        if (!$this->getTable()->hasPrimaryKey()) {
            $exception = $this->declareClass(RuntimeLogicException::class);
            $poolKeyBuilderExpression = "throw new {$exception}('Cannot build PK has from table without PK.')";
            $throwsDoc = "
     * 
     * @throws \\" . RuntimeLogicException::class;
        } else {
            $poolKeyBuilderExpression = 'return ' . $this->instancePoolCodeBuilder->buildPoolKeyFromObjectVariable($objectVar);
            $throwsDoc = '';
        }

        $script .= "
    /**
     * Returns a serialized version of the primary key as unique identifier of the model instance.
     *
     * @param $modelClassFq $objectVar{$throwsDoc}
     *
     * @return string|null
     */
    public static function getPrimaryKeyHashFromObject($modelClass $objectVar): string|null
    {
        $poolKeyBuilderExpression;
    }\n";
    }

    /**
     * @return string
     */
    protected function buildPKDocType(): string
    {
        $pks = $this->getTable()->getPrimaryKey();
        $docTypes = array_map(fn (Column $col) => $this->referencedClasses->resolveTypeDeclarationFromDocType($col->getPhpType()), $pks) ?: ['null'];

        return count($docTypes) === 1 ? $docTypes[0] : 'array{' . implode(', ', $docTypes) . '}';
    }

    /**
     * Adds method to get the primary key from a row
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addGetPrimaryKeyFromRow(string &$script): void
    {
        $table = $this->getTable();

        $pkColumnAccesses = [];
        foreach ($table->getEagerColumns() as $index => $col) {
            if (!$col->isPrimaryKey()) {
                continue;
            }
            $columnPhpType = $col->getPhpType();
            $columnPhpName = $col->getPhpName();
            $rowAccess = "\$row[\$indexType === TableMap::TYPE_NUM ? $index + \$offset : self::translateFieldName('$columnPhpName', TableMap::TYPE_PHPNAME, \$indexType)]";
            $pkColumnAccesses[] = $col->isPhpObjectType() ? "new $columnPhpType($rowAccess)" : "($columnPhpType)$rowAccess";
        }
        $isCompositePk = count($pkColumnAccesses) > 1;
        $returnType = count($pkColumnAccesses) > 0 ? $this->buildPKDocType() : 'null';

        $script .= "
    /**
     * Retrieves the primary key from the DB resultset row
     * For tables with a single-column primary key, that simple pkey value will be returned. 
     * For tables with a multi-column primary key, an array of the primary key columns will be returned.
     *
     * @param array<mixed> \$row Resultset row.
     * @param int \$offset The 0-based offset for reading from the resultset row.
     * @param string \$indexType One of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_CAMELNAME
     *                           TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM
     *
     * @return $returnType
     */
    public static function getPrimaryKeyFromRow(array \$row, int \$offset = 0, string \$indexType = TableMap::TYPE_NUM)
    {";
        if (!$isCompositePk) {
            $pkAccess = end($pkColumnAccesses) ?: 'null';
            $script .= "
        return $pkAccess;";
        } else {
            $script .= "
        return [";
            foreach ($pkColumnAccesses as $pkColumnAccess) {
                $script .= "
            $pkColumnAccess,";
            }
            $script .= "
        ];";
        }
        $script .= "
    }\n";
    }

    /**
     * Adds the correct getOMClass() method, depending on whether this table uses inheritance.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addGetOMClassMethod(string &$script): void
    {
        $table = $this->getTable();
        if ($table->getChildrenColumn()) {
            $this->addGetOMClassInheritance($script);
        } elseif ($table->isAbstract()) {
            $this->addGetOMClassNoInheritanceAbstract($script);
        } else {
            $this->addGetOMClassNoInheritance($script);
        }
    }

    /**
     * Adds a getOMClass() for non-abstract tables that have inheritance.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addGetOMClassInheritance(string &$script): void
    {
        $this->declareClass(PropelException::class);
        $this->declareGlobalFunction('preg_replace');

        $col = $this->getTable()->getChildrenColumn();
        $columnIndex = $col->getPosition() - 1;
        $stubObjectNameFq = $this->tableNames->useObjectStubClassName(false);

        $script .= "
    /**
     * The returned Class will contain objects of the default type or
     * objects that inherit from the default.
     *
     * @psalm-return (\$withPrefix is true ? string : class-string<$stubObjectNameFq>)
     *
     * @param array \$row Fetched row.
     * @param int \$offset Start of tuple data in row.
     * @param bool \$withPrefix If true, namespace will be separated by dots, regular backslashes otherwise.
     *
     * @return class-string<$stubObjectNameFq>|string
     */
    public static function getOMClass(array \$row, int \$offset, bool \$withPrefix = true): string
    {
        \$classKey = \$row[\$offset + $columnIndex];";
        if (!$col->isEnumeratedClasses()) {
            $script .= "
        \$omClass = preg_replace('#\.#', '\\\\', '.'.\$classKey);";
        } else {
            $script .= "
        \$omClass = static::\$objectClassLookup[\$classKey] ?? static::OM_CLASS;";
        }
        $script .= "

        return \$withPrefix ? \$omClass : preg_replace('/\./', '\\\\', \$omClass); // replace dots with backslash 
    }\n";
    }

    /**
     * Adds a getOMClass() for non-abstract tables that do not use inheritance.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addGetOMClassNoInheritance(string &$script): void
    {
        $stubObjectBuilder = $this->getStubObjectBuilder();
        $stubClassWithDots = $stubObjectBuilder->getClasspath();
        $stubObjectNameFq = $stubObjectBuilder->getFullyQualifiedClassName();

        $script .= "
    /**
     * The class that the tableMap will make instances of.
     *
     * If \$withPrefix is true, the returned path
     * uses a dot-path notation which is translated into a path
     * relative to a location on the PHP include_path.
     * (e.g. path.to.MyClass -> 'path/to/MyClass.php')
     *
     * @psalm-return (\$withPrefix is true ? string : class-string<$stubObjectNameFq>)
     *
     * @param bool \$withPrefix If true, namespace will be separated by dots, regular backslashes otherwise.
     *
     * @return class-string<$stubObjectNameFq>|string
     */
    public static function getOMClass(bool \$withPrefix = true): string
    {
        return \$withPrefix ? '$stubClassWithDots' : static::OM_CLASS;
    }\n";
    }

    /**
     * Adds a getOMClass() signature for abstract tables that do not have inheritance.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addGetOMClassNoInheritanceAbstract(string &$script): void
    {
        $this->declareClass(PropelException::class);
        $objectClassName = $this->registerOwnClassIdentifier();

        $script .= "
    /**
     * The class that the tableMap will make instances of.
     *
     * This method must be overridden by the stub subclass, because
     * $objectClassName is declared abstract in the schema.
     *
     * @param bool \$withPrefix
     *
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return string
     */
    public static function getOMClass(bool \$withPrefix = true): string
    {
        throw new PropelException('$objectClassName is declared abstract, it cannot be instantiated.');
    }\n";
    }

    /**
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addPopulateObject(string &$script): void
    {
        $table = $this->getTable();
        $stubObjectNameFq = $this->tableNames->useObjectStubClassName(false);
        $script .= "
    /**
     * Populates an object of the default type or an object that inherit from the default.
     *
     * @param array \$row Row returned by DataFetcher->fetch().
     * @param int \$offset The 0-based offset for reading from the resultset row.
     * @param string \$indexType The index type of \$row. Mostly DataFetcher->getIndexType().
     *                           One of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_CAMELNAME
     *                           TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     *
     * @return array{{$stubObjectNameFq}|null, int} Hydrated object and number of hydrated columns
     */
    public static function populateObject(array \$row, int \$offset = 0, string \$indexType = TableMap::TYPE_NUM): array
    {
        \$key = static::getPrimaryKeyHashFromRow(\$row, \$offset, \$indexType);
        /** @var $stubObjectNameFq|null \$obj */
        \$obj = static::getInstanceFromPool(\$key);
        if (\$obj) {
            \$nextColumnIndex = \$offset + static::NUM_HYDRATE_COLUMNS;";
        if ($table->isAbstract()) {
            $script .= "
        } elseif (\$key === null) {
            // empty resultset, probably from a left join
            // since this table is abstract, we can't hydrate an empty object
            \$obj = null;
            \$nextColumnIndex = \$offset + static::NUM_HYDRATE_COLUMNS;";
        }

        $clsLocation = $table->getChildrenColumn()
            ? 'static::getOMClass($row, $offset, false)'
            : 'static::OM_CLASS';

        $script .= "
        } else {
            \$cls = $clsLocation;
            \$obj = new \$cls();
            \$nextColumnIndex = \$obj->hydrate(\$row, \$offset, false, \$indexType);
            static::addInstanceToPool(\$obj, \$key);
        }

        return [\$obj, \$nextColumnIndex];
    }\n";
    }

    /**
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addPopulateObjects(string &$script): void
    {
        $this->declareGlobalFunction('assert', 'is_array');
        $table = $this->getTable();
        $objectStubClassName = $this->tableNames->useObjectStubClassName(true);
        $objectStubClassNameFq = $this->tableNames->useObjectStubClassName(false);
        $script .= "
    /**
     * The returned array will contain objects of the default type or
     * objects that inherit from the default.
     *
     * @param \Propel\Runtime\DataFetcher\DataFetcherInterface \$dataFetcher
     *
     * @return array<object>
     */
    public static function populateObjects(DataFetcherInterface \$dataFetcher): array
    {
        \$results = [];

        while (\$row = \$dataFetcher->fetch()) {
            assert(is_array(\$row));
            \$key = static::getPrimaryKeyHashFromRow(\$row, 0, \$dataFetcher->getIndexType());
            /** @var $objectStubClassNameFq|null \$obj */
            \$obj = static::getInstanceFromPool(\$key);
            if (!\$obj) {";

        if (!$table->getChildrenColumn()) {
            $script .= "
                \$obj = new $objectStubClassName();";
        } else {
            $this->declareGlobalFunction('preg_replace');
            $script .= "
                // class must be set each time from the record row
                \$cls = static::getOMClass(\$row, 0);
                \$cls = preg_replace('#\.#', '\\\\', \$cls);
                /** @var $objectStubClassNameFq \$obj */
                \$obj = new \$cls();";
        }
        $script .= "
                \$obj->hydrate(\$row);
                static::addInstanceToPool(\$obj, \$key);
            }
            \$results[] = \$obj;
        }

        return \$results;
    }\n";
    }

    /**
     * Adds the addSelectColumns() method.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addAddSelectColumns(string &$script): void
    {
        $script .= "
    /**
     * Add all the columns needed to create a new object.
     *
     * Note: any columns that were marked with lazyLoad=\"true\" in the
     * XML schema will not be added to the select list and only loaded
     * on demand.
     *
     * @param \Propel\Runtime\ActiveQuery\Criteria \$criteria Object containing the columns to add.
     * @param string|null \$alias Optional table alias
     *
     * @return void
     */
    public static function addSelectColumns(Criteria \$criteria, ?string \$alias = null): void
    {
        \$tableMap = static::getTableMap();
        \$tableAlias = \$alias ?: '{$this->getTable()->getName()}';";
        foreach ($this->getTable()->getColumns() as $col) {
            if ($col->isLazyLoad()) {
                continue;
            }
            $normalizedColumnName = strtoupper($col->getName());
            $script .= "
        \$criteria->addSelectColumn(new LocalColumnExpression(\$criteria, \$tableAlias, \$tableMap->columns['$normalizedColumnName']));";
        }
        $script .= "
    }\n";
    }

    // addAddSelectColumns()

    /**
     * Adds the removeSelectColumns() method.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addRemoveSelectColumns(string &$script): void
    {
        $eagerLoadedColumns = $this->getTable()->getEagerColumns();
        $script .= "
    /**
     * Remove all the columns needed to create a new object.
     *
     * Note: any columns that were marked with lazyLoad=\"true\" in the
     * XML schema will not be removed as they are only loaded on demand.
     *
     * @param \Propel\Runtime\ActiveQuery\Criteria \$criteria Object containing the columns to remove.
     * @param string|null \$alias Optional table alias
     *
     * @return void
     */
    public static function removeSelectColumns(Criteria \$criteria, ?string \$alias = null): void
    {
        if (\$alias === null) {";
        foreach ($eagerLoadedColumns as $col) {
            $columnIdentifier = $this->getColumnConstant($col, 'static');
            $script .= "
            \$criteria->removeSelectColumn($columnIdentifier);";
        }
        $script .= "
        } else {";
        foreach ($eagerLoadedColumns as $col) {
            $columnName = $col->getName();
            $script .= "
            \$criteria->removeSelectColumn(\"\$alias.$columnName\");";
        }
        $script .= "
        }
    }\n";
    }

    // addRemoveSelectColumns()

    /**
     * Adds the getTableMap() method which is a convenience method for apps to get DB metadata.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addGetTableMap(string &$script): void
    {
        $script .= "
    /**
     * Returns the TableMap related to this object.
     * This method is not needed for general use but a specific application could have a need.
     *
     * @return static
     */
    public static function getTableMap(): TableMap
    {
        /** @var static \$tableMap */
        \$tableMap = Propel::getServiceContainer()->getDatabaseMap(static::DATABASE_NAME)->getTable(static::TABLE_NAME);

        return \$tableMap;
    }\n";
    }

    /**
     * Adds the doDeleteAll() method.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addDoDeleteAll(string &$script): void
    {
        $table = $this->getTable();
        $queryClassName = $this->getQueryClassName();
        $script .= "
    /**
     * Deletes all rows from the " . $table->getName() . " table.
     *
     * @param \Propel\Runtime\Connection\ConnectionInterface|null \$con the connection to use
     *
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(?ConnectionInterface \$con = null): int
    {
        return $queryClassName::create()->doDeleteAll(\$con);
    }
";
    }

    /**
     * Adds the doDelete() method.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addDoDelete(string &$script): void
    {
        $this->declareGlobalFunction('is_object', 'trigger_deprecation');
        $table = $this->getTable();
        $queryClassName = $this->getQueryClassName();
        $modelClassName = $this->tableNames->useObjectStubClassName();
        $ownClassIdentifier = $this->registerOwnClassIdentifier();

        $script .= "
    /**
     * @deprecated Delete via model or $queryClassName.
     *
     * Performs a DELETE on the database, given a $ownClassIdentifier or Criteria object OR a primary key value.
     *
     * @param mixed \$values Criteria or $ownClassIdentifier object or primary key or array of primary keys
     *              which is used to create the DELETE statement
     * @param \Propel\Runtime\Connection\ConnectionInterface|null \$con the connection to use
     *";
        if (!$table->getPrimaryKey()) {
            $script .= "
     * @throws \Propel\Runtime\Exception\LogicException
     *";
        }
        $script .= "
     * @return int The number of affected rows (if supported by underlying database driver). This includes CASCADE-related rows
     *                         if supported by native driver or if emulated using Propel.
     */
    public static function doDelete(\$values, ?ConnectionInterface \$con = null): int
    {
        trigger_deprecation('Propel', '2.0', 'TableMap::doDelete() should not be used anymore, delete via model or $queryClassName');

        \$con ??= Propel::getServiceContainer()->getWriteConnection(static::DATABASE_NAME);

        if (\$values instanceof Criteria) {
            \$criteria = \$values;
        } elseif (\$values instanceof $modelClassName) {";
        if (count($table->getPrimaryKey()) > 0) {
            $script .= "
            \$criteria = \$values->buildPkeyCriteria();";
        } else {
            $script .= "
            // create criteria based on pk value
            \$criteria = \$values->buildCriteria();";
        }

        $script .= "
        } else { // it's a primary key, or an array of pks";

        if (!$table->getPrimaryKey()) {
            $class = $this->getObjectName();
            $exception = $this->declareClass(RuntimeLogicException::class);
            $script .= "
            throw new {$exception}('The $class object has no primary key');";
        } else {
            $script .= "
            \$criteria = new Criteria(static::DATABASE_NAME);";

            $pkey = $table->getPrimaryKey();
            if (count($pkey) === 1) {
                $col = array_shift($pkey);
                $columnConstant = $this->getColumnConstant($col, 'static');
                $script .= "
            \$criteria->addAnd($columnConstant, (array)\$values, Criteria::IN);";
            } else {
                $this->declareGlobalFunction('count');
                $this->declareGlobalConstant('COUNT_RECURSIVE');
                $script .= "
            // turn to multi-dimensional array
            \$values = count(\$values) === count(\$values, COUNT_RECURSIVE) ? [\$values] : \$values;
            
            foreach (\$values as \$value) {
                \$criteria->_or()->combineFilters()";
                foreach ($pkey as $i => $col) {
                    $columnConstant = $this->getColumnConstant($col, 'static');
                    $script .= "
                    ->addUsingOperator($columnConstant, \$value[$i])";
                }
                $script .= "
                    ->endCombineFilters();";
                $script .= "
            }";
            }
        }

        $script .= "
        }

        if (\$values instanceof Criteria) {
            static::clearInstancePool();
        } elseif (!is_object(\$values)) { // it's a primary key, or an array of pks
            foreach ((array)\$values as \$singleval) {
                static::removeInstanceFromPool(\$singleval);
            }
        }

        return $queryClassName::create(null, \$criteria)->delete(\$con);
    }\n";
    }

    /**
     * Adds the doInsert() method.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addDoInsert(string &$script): void
    {
        $table = $this->getTable();
        $stubObjectName = $this->tableNames->useObjectStubClassName();
        $stubObjectNameFq = $this->tableNames->useObjectStubClassName(false);
        $queryClassName = $this->getQueryClassName();
        $autoIncrementedKeyColumns = $table->getIdMethod() === 'none' ? [] : array_filter($table->getPrimaryKey(), fn (Column $pkCol) => $pkCol->isAutoIncrement());

        $throwsException = $autoIncrementedKeyColumns && !$table->isAllowPkInsert();
        if ($throwsException) {
            $this->declareClass(PropelException::class);
        }
        $script .= "
    /**
     * Performs an INSERT on the database, given a $stubObjectName or Criteria object.
     *
     * @param \Propel\Runtime\ActiveQuery\Criteria|$stubObjectNameFq \$criteria
     * @param \Propel\Runtime\Connection\ConnectionInterface|null \$con
     *";
        if ($throwsException) {
            $script .= "
     * @throws \Propel\Runtime\Exception\PropelException
     *";
        }
        $script .= "
     * @return mixed The new primary key.
     */
    public static function doInsert(\$criteria, ?ConnectionInterface \$con = null)
    {
        \$con ??= Propel::getServiceContainer()->getWriteConnection(static::DATABASE_NAME);

        if (\$criteria instanceof Criteria) {
            \$criteria = clone \$criteria;
            \$criteria->turnFiltersToUpdateValues();
        } else {
            \$criteria = \$criteria->buildCriteria(); // build Criteria from $stubObjectName object
        }\n";

        foreach ($autoIncrementedKeyColumns as $col) {
            $columnConstant = $this->getColumnConstant($col, 'static');
            if (!$table->isAllowPkInsert()) {
                $script .= "
        if (\$criteria->hasUpdateValue($columnConstant)) {
            throw new PropelException('Cannot insert a value for auto-increment primary key ($columnConstant)');
        }\n";
                if (!$this->getPlatform()->supportsInsertNullPk()) {
                    $script .= "
        // remove pkey col since this table uses auto-increment and passing a null value for it is not valid
        \$criteria->remove($columnConstant);\n";
                }
            } elseif ($table->isAllowPkInsert() && !$this->getPlatform()->supportsInsertNullPk()) {
                $script .= "
        // remove pkey col if it is null since this table does not accept that
        if (\$criteria->containsKey() && !\$criteria->hasUpdateValue($columnConstant) ) {
            \$criteria->remove($columnConstant);
        }\n";
            }
        }

        $script .= "
        return $queryClassName::create(null, \$criteria)->doInsert(\$con);
    }\n";
    }
}
