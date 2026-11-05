<?php

declare(strict_types = 1);

namespace Propel\Generator\Builder\Om;

use Propel\Generator\Builder\Util\EntityObjectClassNames;
use Propel\Generator\Exception\BuildException;
use Propel\Generator\Model\Inheritance;
use Propel\Generator\Model\Table;
use function array_find;
use function assert;

/**
 * Generates the base query class for use with single table inheritance.
 */
class QueryInheritanceBuilder extends AbstractOMBuilder implements ExtensionBuilderInterface
{
    /**
     * @var \Propel\Generator\Builder\Om\BuilderType|null
     */
    public const BUILDER_TYPE = BuilderType::QueryInheritance;

    protected EntityObjectClassNames $tableNames;

    protected Inheritance|null $inheritanceConfiguration = null;

    /**
     * @param \Propel\Generator\Model\Table $table
     */
    public function __construct(Table $table)
    {
        parent::__construct($table);
        $this->tableNames = $this->referencedClasses->useEntityObjectClassNames($table);
    }

    /**
     * Returns the name of the current class being built.
     *
     * @return string
     */
    #[\Override]
    public function getUnprefixedClassName(): string
    {
        return $this->getStubQueryInheritanceBuilder($this->getInheritanceConfiguration())->getUnprefixedClassName();
    }

    /**
     * Gets the package for the [base] object classes.
     *
     * @return string
     */
    #[\Override]
    public function getPackage(): string
    {
        return ($this->getInheritanceConfiguration()->getPackage() ?: parent::getPackage()) . '.Base';
    }

    /**
     * Gets the namespace for the [base] object classes.
     *
     * @return string|null
     */
    #[\Override]
    public function getNamespace(): ?string
    {
        $namespace = parent::getNamespace();

        return $namespace ? "$namespace\\Base" : 'Base';
    }

    /**
     * @param \Propel\Generator\Model\Inheritance $inheritance
     *
     * @return void
     */
    #[\Override]
    public function setInheritanceConfiguration(Inheritance $inheritance): void
    {
        $this->inheritanceConfiguration = $inheritance;
    }

    /**
     * @throws \Propel\Generator\Exception\BuildException
     *
     * @return \Propel\Generator\Model\Inheritance
     */
    #[\Override]
    public function getInheritanceConfiguration(): Inheritance
    {
        if (!$this->inheritanceConfiguration) {
            throw new BuildException('The MultiExtendObjectBuilder needs to be told which child class to build (via setChild() method) before it can build the stub class.');
        }

        return $this->inheritanceConfiguration;
    }

    /**
     * @deprecated Use aptly named {@see static::setInheritanceConfiguration()}
     *
     * @param \Propel\Generator\Model\Inheritance $child
     *
     * @return void
     */
    public function setChild(Inheritance $child): void
    {
        $this->setInheritanceConfiguration($child);
    }

    /**
     * @deprecated Use aptly named {@see static::getInheritanceConfiguration()}
     *
     * @return \Propel\Generator\Model\Inheritance
     */
    public function getChild(): Inheritance
    {
        return $this->getInheritanceConfiguration();
    }

    /**
     * Returns parent class builder.
     *
     * @return \Propel\Generator\Builder\Om\ExtensionQueryBuilder|\Propel\Generator\Builder\Om\ExtensionQueryInheritanceBuilder|null
     */
    protected function getParentClassBuilder(): ExtensionQueryBuilder|ExtensionQueryInheritanceBuilder|null
    {
        if ($this->inheritanceConfiguration->isMainTableClass()) {
            return $this->getStubQueryBuilder($this->getTable());
        }

        $parentClassIdentifier = ClassTools::classname($this->inheritanceConfiguration->getParentClassIdentifier());
        $parentTable = $this->getDatabase()->getTableByPhpName($parentClassIdentifier);
        if ($parentTable) {
            return $this->getStubQueryBuilder($parentTable);
        }

        $inheritanceConfigurations = $this->getTable()->getClassKeyColumn()->getInheritanceConfigurations();
        $parentConfiguration = array_find($inheritanceConfigurations, fn (Inheritance $i) => $i->getClassName() === $parentClassIdentifier);

        return $parentConfiguration
            ? $this->getStubQueryInheritanceBuilder($parentConfiguration)
            : null;
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
        $tableDesc = $table->getDescription() ? $table->getDescription() . "\n * " : '';

        $className = $this->getUnqualifiedClassName();

        $parentClassBuilder = $this->getParentClassBuilder();
        assert($parentClassBuilder !== null);
        $localParentClassName = $this->declareClassFromBuilder($parentClassBuilder);
        $parentClassNameFq = $this->referencedClasses->getQualifiedNameForClassAlias($localParentClassName);

        $timeStampBlock = $this->generateTimestampBlock();

        $script .= "
/**
 * Base query class for one of the classes of the '$tableName' table.
 *{$tableDesc}{$timeStampBlock}
 * @template ParentQuery extends \Propel\Runtime\ActiveQuery\ModelCriteria|null = null
 * @extends \\$parentClassNameFq<ParentQuery>
 */
class $className extends $localParentClassName
{";
    }

    /**
     * Specifies the methods that are added as part of the stub object class.
     *
     * By default there are no methods for the empty stub classes; override this
     * method if you want to change that behavior.
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
        $this->declareClassFromBuilder($this->getTableMapBuilder());
        $this->declareClasses(
            '\Propel\Runtime\Connection\ConnectionInterface',
            '\Propel\Runtime\ActiveQuery\Criteria',
        );
        $this->addFactory($script);
        $this->addPreSelect($script);
        $this->addPreUpdate($script);
        $this->addPreDelete($script);
        $this->addDoDeleteAll($script);
    }

    /**
     * Adds the factory for this object.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addFactory(string &$script): void
    {
        $stubClassName = $this->getUnqualifiedClassName();
        $builder = $this->getStubQueryInheritanceBuilder($this->getInheritanceConfiguration());
        $localStubClassName = $this->declareClassFromBuilder($builder, 'Child');
        $stubClassNameFq = $builder->getFullyQualifiedClassName();

        $script .= "
    /**
     * Returns a new $stubClassName object.
     *
     * @param string|null \$modelAlias The alias of a model in the query
     * @param \Propel\Runtime\ActiveQuery\Criteria|null \$criteria Optional Criteria to build the query from
     *
     * @return $stubClassNameFq<null>
     */
    public static function create(?string \$modelAlias = null, ?Criteria \$criteria = null): Criteria
    {
        if (\$criteria instanceof $localStubClassName) {
            return \$criteria;
        }
        \$query = new $localStubClassName();
        if (\$modelAlias !== null) {
            \$query->setModelAlias(\$modelAlias);
        }
        if (\$criteria !== null) {
            \$query->mergeWith(\$criteria);
        }

        return \$query;
    }\n";
    }

    /**
     * @param string $script
     *
     * @return void
     */
    protected function addPreSelect(string &$script): void
    {
        $childClassName = $this->inheritanceConfiguration->getClassName();

        $script .= "
    /**
     * Filters the query to target only $childClassName objects.
     *
     * @param \Propel\Runtime\Connection\ConnectionInterface \$con a connection object
     *
     * @return void
     */
    public function preSelect(ConnectionInterface \$con): void
    {
        " . $this->getClassKeyCondition() . "
    }\n";
    }

    /**
     * @param string $script
     *
     * @return void
     */
    protected function addPreUpdate(string &$script): void
    {
        $childClassName = $this->inheritanceConfiguration->getClassName();

        $script .= "
    /**
     * Filters the query to target only $childClassName objec
     *
     * @param array \$values The associative array of columns and values for the update
     * @param \Propel\Runtime\Connection\ConnectionInterface \$con The connection object used by the query
     * @param bool \$forceIndividualSaves If false (default), the resulting call is a Criteria::doUpdate(), otherwise it is a series of save() calls on all the found objects
     *
     * @return int|null
     */
    public function preUpdate(&\$values, ConnectionInterface \$con, \$forceIndividualSaves = false): ?int
    {
        " . $this->getClassKeyCondition() . "

        return null;
    }\n";
    }

    /**
     * @param string $script
     *
     * @return void
     */
    protected function addPreDelete(string &$script): void
    {
        $childClassName = $this->inheritanceConfiguration->getClassName();

        $script .= "
    /**
     * Filters the query to target only $childClassName objects.
     *
     * @param \Propel\Runtime\Connection\ConnectionInterface \$con a connection object
     *
     * @return int|null
     */
    public function preDelete(ConnectionInterface \$con): ?int
    {
        " . $this->getClassKeyCondition() . "

        return null;
    }\n";
    }

    /**
     * @return string
     */
    protected function getClassKeyCondition(): string
    {
        $tableMapClassName = $this->getTableMapClassName();
        $columnConstant = $this->inheritanceConfiguration->getConstantSuffix();
        $columnName = $this->inheritanceConfiguration->getColumn()->getName();

        return "\$this->addUsingOperator(\$this->resolveLocalColumnByName('{$columnName}'), {$tableMapClassName}::CLASSKEY_{$columnConstant});";
    }

    /**
     * @param string $script
     *
     * @return void
     */
    protected function addDoDeleteAll(string &$script): void
    {
        $childClassName = $this->inheritanceConfiguration->getClassName();

        $script .= "
    /**
     * Issue a DELETE query based on the current ModelCriteria deleting all rows in the table
     * Having the $childClassName class.
     * This method is called by ModelCriteria::deleteAll() inside a transaction
     *
     * @param \Propel\Runtime\Connection\ConnectionInterface|null \$con a connection object
     *
     * @return int The number of deleted rows
     */
    public function doDeleteAll(?ConnectionInterface \$con = null): int
    {
        // condition on class key is already added in preDelete()
        return parent::delete(\$con);
    }\n";
    }

    /**
     * Closes class.
     *
     * @param string $script
     *
     * @return void
     */
    #[\Override]
    protected function addClassClose(string &$script): void
    {
        $script .= "}\n";
    }
}
