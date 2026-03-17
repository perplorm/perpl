<?php

declare(strict_types = 1);

namespace Propel\Generator\Config;

use Propel\Common\Config\ConfigurationManager;
use Propel\Common\Pluralizer\PluralizerInterface;
use Propel\Generator\Builder\Om\AbstractOMBuilder;
use Propel\Generator\Builder\Om\BuilderType;
use Propel\Generator\Builder\Om\ExtensionObjectBuilder;
use Propel\Generator\Builder\Om\ExtensionQueryBuilder;
use Propel\Generator\Builder\Om\ExtensionQueryInheritanceBuilder;
use Propel\Generator\Builder\Om\InterfaceBuilder;
use Propel\Generator\Builder\Om\MultiExtendObjectBuilder;
use Propel\Generator\Builder\Om\ObjectBuilder;
use Propel\Generator\Builder\Om\ObjectCollectionBuilder;
use Propel\Generator\Builder\Om\QueryBuilder;
use Propel\Generator\Builder\Om\QueryInheritanceBuilder;
use Propel\Generator\Builder\Om\TableMapBuilder;
use Propel\Generator\Exception\BuildException;
use Propel\Generator\Exception\ClassNotFoundException;
use Propel\Generator\Exception\InvalidArgumentException;
use Propel\Generator\Model\Inheritance;
use Propel\Generator\Model\Table;
use Propel\Generator\Platform\PlatformInterface;
use Propel\Generator\Reverse\SchemaParserInterface;
use Propel\Generator\Util\BehaviorLocator;
use Propel\Runtime\Connection\ConnectionInterface;
use function assert;
use function class_exists;
use function interface_exists;

/**
 * Holds build properties and provides a class loading mechanism for
 * the generator.
 */
abstract class AbstractGeneratorConfig extends ConfigurationManager
{
    protected BehaviorLocator|null $behaviorLocator = null;

    /**
     * @var array<string, array<value-of<\Propel\Generator\Builder\Om\BuilderType>, \Propel\Generator\Builder\Om\AbstractOMBuilder>>
     */
    protected array $buildersByTable = [];

    /**
     * Returns a configured Pluralizer class.
     *
     * @return \Propel\Common\Pluralizer\PluralizerInterface
     */
    abstract public function getConfiguredPluralizer(): PluralizerInterface;

    /**
     * Creates and configures a new Platform class.
     *
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con
     * @param string|null $databaseName
     *
     * @throws \Propel\Generator\Exception\ClassNotFoundException if the platform class doesn't exists
     * @throws \Propel\Generator\Exception\BuildException if the class isn't an implementation of PlatformInterface
     *
     * @return \Propel\Generator\Platform\PlatformInterface|null
     */
    abstract public function getConfiguredPlatform(?ConnectionInterface $con = null, ?string $databaseName = null): ?PlatformInterface;

    /**
     * Creates and configures a new SchemaParser class for a specified platform.
     *
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con
     * @param string|null $databaseName
     *
     * @throws \Propel\Generator\Exception\ClassNotFoundException if the class doesn't exist
     * @throws \Propel\Generator\Exception\BuildException if the class isn't an implementation of SchemaParserInterface
     *
     * @return \Propel\Generator\Reverse\SchemaParserInterface|null
     */
    abstract public function getConfiguredSchemaParser(?ConnectionInterface $con = null, ?string $databaseName = null): ?SchemaParserInterface;

    /**
     * @return \Propel\Generator\Util\BehaviorLocator
     */
    public function getBehaviorLocator(): BehaviorLocator
    {
        if ($this->behaviorLocator === null) {
            $this->behaviorLocator = new BehaviorLocator($this);
        }

        return $this->behaviorLocator;
    }

    /**
     * @deprecated Use aptly named {@see static::loadConfiguredBuilder()}
     *
     * @param \Propel\Generator\Model\Table $table
     * @param \Propel\Generator\Builder\Om\BuilderType $type
     *
     * @return \Propel\Generator\Builder\Om\AbstractOMBuilder
     */
    public function getConfiguredBuilder(Table $table, BuilderType $type): AbstractOMBuilder
    {
        return $this->loadConfiguredBuilder($table, $type);
    }

    /**
     * Returns a configured data model builder class for specified table and
     * based on type ('ddl', 'sql', etc.).
     *
     * @param \Propel\Generator\Model\Table $table
     * @param \Propel\Generator\Builder\Om\BuilderType $type
     *
     * @return \Propel\Generator\Builder\Om\AbstractOMBuilder
     */
    public function loadConfiguredBuilder(Table $table, BuilderType $type): AbstractOMBuilder
    {
        $tableId = $table->getQualifiedClassName();
        if (empty($this->buildersByTable[$tableId][$type->value])) {
            $this->buildersByTable[$tableId][$type->value] = $this->setupBuilder($table, $type);
        }

        return $this->buildersByTable[$tableId][$type->value];
    }

    /**
     * @template T of \Propel\Generator\Builder\Om\AbstractOMBuilder
     *
     * @param \Propel\Generator\Model\Table $table
     * @param \Propel\Generator\Builder\Om\BuilderType $type
     * @param class-string<T> $expectedBuilderClass
     *
     * @return T
     */
    protected function loadConfiguredBuilderTypeSafe(Table $table, BuilderType $type, string $expectedBuilderClass): AbstractOMBuilder
    {
        $builder = $this->loadConfiguredBuilder($table, $type);
        assert($builder instanceof $expectedBuilderClass);

        return $builder;
    }

    /**
     * @psalm-return
     *  ($type is Propel\Generator\Builder\Om\BuilderType::Collection ? \Propel\Generator\Builder\Om\ObjectCollectionBuilder :
     *  ($type is Propel\Generator\Builder\Om\BuilderType::Interface ? \Propel\Generator\Builder\Om\InterfaceBuilder :
     *  ($type is Propel\Generator\Builder\Om\BuilderType::TableMap ? \Propel\Generator\Builder\Om\TableMapBuilder :
     *  ($type is Propel\Generator\Builder\Om\BuilderType::ObjectBase ? \Propel\Generator\Builder\Om\ObjectBuilder :
     *  ($type is Propel\Generator\Builder\Om\BuilderType::ObjectStub ? \Propel\Generator\Builder\Om\ExtensionObjectBuilder :
     *  ($type is Propel\Generator\Builder\Om\BuilderType::QueryBase ? \Propel\Generator\Builder\Om\QueryBuilder :
     *  ($type is Propel\Generator\Builder\Om\BuilderType::QueryStub ? \Propel\Generator\Builder\Om\ExtensionQueryBuilder :
     *  ($type is Propel\Generator\Builder\Om\BuilderType::QueryInheritance ? \Propel\Generator\Builder\Om\QueryInheritanceBuilder :
     *  ($type is Propel\Generator\Builder\Om\BuilderType::ObjectInheritanceStub ? \Propel\Generator\Builder\Om\MultiExtendObjectBuilder :
     *  ($type is Propel\Generator\Builder\Om\BuilderType::QueryInheritanceStub ? \Propel\Generator\Builder\Om\ExtensionQueryInheritanceBuilder :
     *      \Propel\Generator\Builder\Om\AbstractOMBuilder
     *  ))))))))))
     *
     * @param \Propel\Generator\Model\Table $table
     * @param \Propel\Generator\Builder\Om\BuilderType $type
     *
     * @return \Propel\Generator\Builder\Om\AbstractOMBuilder
     */
    public function setupBuilder(Table $table, BuilderType $type): AbstractOMBuilder
    {
        $configProperty = "generator.objectModel.builders.{$type->value}";
        $classname = $this->getConfigPropertyString($configProperty, true);

        $builder = $this->createInstance($classname, $table);
        assert($builder instanceof AbstractOMBuilder);
        $builder->setGeneratorConfig($this);

        return $builder;
    }

    /**
     * Return an instance of $className
     *
     * @param string $className The name of the class to return an instance
     * @param mixed|null $arguments
     * @param string|null $interfaceName The name of the interface to be implemented by the returned class
     *
     * @throws \Propel\Generator\Exception\ClassNotFoundException if the class doesn't exists
     * @throws \Propel\Generator\Exception\InvalidArgumentException if the interface doesn't exists
     * @throws \Propel\Generator\Exception\BuildException if the class isn't an implementation of the given interface
     *
     * @return object
     */
    protected function createInstance(string $className, $arguments = null, ?string $interfaceName = null): object
    {
        if (!class_exists($className)) {
            throw new ClassNotFoundException("Class $className not found.");
        }

        $object = new $className($arguments);

        if ($interfaceName !== null) {
            if (!interface_exists($interfaceName)) {
                throw new InvalidArgumentException("Interface $interfaceName does not exists.");
            }

            if (!$object instanceof $interfaceName) {
                throw new BuildException("Specified class ($className) does not implement $interfaceName interface.");
            }
        }

        return $object;
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return \Propel\Generator\Builder\Om\ObjectBuilder
     */
    public function loadObjectBuilder(Table $table): ObjectBuilder
    {
        return $this->loadConfiguredBuilderTypeSafe($table, BuilderType::ObjectBase, ObjectBuilder::class);
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return \Propel\Generator\Builder\Om\ExtensionObjectBuilder
     */
    public function loadStubObjectBuilder(Table $table): ExtensionObjectBuilder
    {
        return $this->loadConfiguredBuilderTypeSafe($table, BuilderType::ObjectStub, ExtensionObjectBuilder::class);
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return \Propel\Generator\Builder\Om\QueryBuilder
     */
    public function loadQueryBuilder(Table $table): QueryBuilder
    {
        return $this->loadConfiguredBuilderTypeSafe($table, BuilderType::QueryBase, QueryBuilder::class);
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return \Propel\Generator\Builder\Om\ExtensionQueryBuilder
     */
    public function loadStubQueryBuilder(Table $table): ExtensionQueryBuilder
    {
        return $this->loadConfiguredBuilderTypeSafe($table, BuilderType::QueryStub, ExtensionQueryBuilder::class);
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return \Propel\Generator\Builder\Om\ObjectCollectionBuilder
     */
    public function loadObjectCollectionBuilder(Table $table): ObjectCollectionBuilder
    {
        return $this->loadConfiguredBuilderTypeSafe($table, BuilderType::Collection, ObjectCollectionBuilder::class);
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return \Propel\Generator\Builder\Om\TableMapBuilder
     */
    public function loadTableMapBuilder(Table $table): TableMapBuilder
    {
        return $this->loadConfiguredBuilderTypeSafe($table, BuilderType::TableMap, TableMapBuilder::class);
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return \Propel\Generator\Builder\Om\InterfaceBuilder
     */
    public function loadInterfaceBuilder(Table $table): InterfaceBuilder
    {
        return $this->loadConfiguredBuilderTypeSafe($table, BuilderType::Interface, InterfaceBuilder::class);
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return \Propel\Generator\Builder\Om\MultiExtendObjectBuilder
     */
    public function loadObjectInheritanceStubBuilder(Table $table): MultiExtendObjectBuilder
    {
        return $this->loadConfiguredBuilderTypeSafe($table, BuilderType::ObjectInheritanceStub, MultiExtendObjectBuilder::class);
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     * @param \Propel\Generator\Model\Inheritance $child
     *
     * @return \Propel\Generator\Builder\Om\QueryInheritanceBuilder
     */
    public function loadQueryInheritanceBuilder(Table $table, Inheritance $child): QueryInheritanceBuilder
    {
        $builder = $this->setupBuilder($table, BuilderType::QueryInheritance);
        assert($builder instanceof QueryInheritanceBuilder);
        $builder->setChild($child);

        return $builder;
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     * @param \Propel\Generator\Model\Inheritance $child
     *
     * @return \Propel\Generator\Builder\Om\ExtensionQueryInheritanceBuilder
     */
    public function loadQueryInheritanceStubBuilder(Table $table, Inheritance $child): ExtensionQueryInheritanceBuilder
    {
        $builder = $this->setupBuilder($table, BuilderType::QueryInheritanceStub);
        assert($builder instanceof ExtensionQueryInheritanceBuilder);
        $builder->setChild($child);

        return $builder;
    }
}
