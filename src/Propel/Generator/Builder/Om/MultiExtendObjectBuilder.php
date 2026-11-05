<?php

declare(strict_types = 1);

namespace Propel\Generator\Builder\Om;

use Propel\Generator\Exception\BuildException;
use Propel\Generator\Model\Inheritance;

/**
 * Generates the empty stub object class for use with inheritance in the
 * user object model (OM).
 *
 * This class produces the empty stub class that can be customized with
 * application business logic, custom behavior, etc.
 */
class MultiExtendObjectBuilder extends AbstractObjectBuilder implements ExtensionBuilderInterface
{
    /**
     * @var \Propel\Generator\Builder\Om\BuilderType|null
     */
    public const BUILDER_TYPE = BuilderType::ObjectInheritanceStub;

    private Inheritance|null $inheritanceConfiguration = null;

    /**
     * Returns the name of the current class being built.
     *
     * @return string
     */
    #[\Override]
    public function getUnprefixedClassName(): string
    {
        return $this->getInheritanceConfiguration()->getClassName();
    }

    /**
     * Overrides method to return child package, if specified.
     *
     * @return string|null
     */
    #[\Override]
    public function getPackage(): ?string
    {
        return ($this->getInheritanceConfiguration()->getPackage() ?: parent::getPackage());
    }

    /**
     * @param \Propel\Generator\Model\Inheritance $inheritance Inheritance
     *
     * @return void
     */
    #[\Override]
    public function setInheritanceConfiguration(Inheritance $inheritance): void
    {
        $this->inheritanceConfiguration = $inheritance;
    }

    /**
     * Returns the child object we're operating on currently.
     *
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
     * Returns classpath to parent class.
     *
     * @return string|null
     */
    protected function getParentClasspath(): ?string
    {
        return $this->getInheritanceConfiguration()->getParentClassIdentifier()
            ?? $this->getObjectBuilder()->getClasspath();
    }

    /**
     * Returns classname of parent class.
     *
     * @return string|null
     */
    protected function getParentClassName(): ?string
    {
        return ClassTools::classname($this->getParentClasspath());
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
        if ($this->getBuildProperty('generator.objectModel.addClassLevelComment')) {
            $this->addClassComment($script);
        }

        $localParentClassName = $this->declareParentClass();
        $className = $this->getUnqualifiedClassName();

        $script .= "
class $className extends $localParentClassName
{\n";
    }

    /**
     * @return string
     */
    protected function declareParentClass(): string
    {
        $parentClassNameFromConfiguration = $this->getInheritanceConfiguration()->getParentClassIdentifier();
        if (!$parentClassNameFromConfiguration) {
            return $this->declareClassFromBuilder($this->getStubObjectBuilder());
        }

        $parentTable = $this->getDatabase()->getTableByPhpName($parentClassNameFromConfiguration);

        return $parentTable
            ? $this->declareClassFromBuilder($this->getStubObjectBuilder($parentTable))
            : $this->declareClassNamespace($parentClassNameFromConfiguration, $this->getNamespace());
    }

    /**
     * @param string $script
     *
     * @return void
     */
    protected function addClassComment(string &$script): void
    {
        $table = $this->getTable();
        $tableName = $table->getName();
        $tableDesc = $table->getDescription();
        $timeStampBlock = $this->generateTimestampBlock();

        $script .= "

/**
 * Skeleton subclass for representing a row from one of the subclasses of the '$tableName' table.
 *
 * $tableDesc
 *{$timeStampBlock}
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */";
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
        $child = $this->getInheritanceConfiguration();
        $column = $child->getColumn();
        $columnPhpName = $column->getPhpName();
        $classIdentifierConst = $this->getTableMapClassName() . '::CLASSKEY_' . $child->getConstantSuffix();
        $className = $this->getInheritanceConfiguration()->getClassName();
        $columnName = $column->getName();

        $script .= "
    /**
     * Constructs a new $className class, setting the $columnName column to $classIdentifierConst.
     */
    public function __construct()
    {
        parent::__construct();
        \$this->set$columnPhpName($classIdentifierConst);
    }\n";
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
        $script .= "
}
";
    }
}
