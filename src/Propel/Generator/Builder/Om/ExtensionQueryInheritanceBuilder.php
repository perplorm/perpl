<?php

declare(strict_types = 1);

namespace Propel\Generator\Builder\Om;

use Propel\Generator\Exception\BuildException;
use Propel\Generator\Model\Inheritance;

/**
 * Generates the empty stub query class for use with single table inheritance.
 *
 * This class produces the empty stub class that can be customized with
 * application business logic, custom behavior, etc.
 */
class ExtensionQueryInheritanceBuilder extends AbstractOMBuilder implements ExtensionBuilderInterface
{
    /**
     * @var \Propel\Generator\Builder\Om\BuilderType|null
     */
    public const BUILDER_TYPE = BuilderType::QueryInheritanceStub;

    /**
     * The current child "object" we are operating on.
     *
     * @var \Propel\Generator\Model\Inheritance|null
     */
    protected $child;

    /**
     * Returns the name of the current class being built.
     *
     * @return string
     */
    #[\Override]
    public function getUnprefixedClassName(): string
    {
        return $this->getInheritanceConfiguration()->getClassName() . 'Query';
    }

    /**
     * Gets the package for the [base] object classes.
     *
     * @return string|null
     */
    #[\Override]
    public function getPackage(): ?string
    {
        return ($this->getInheritanceConfiguration()->getPackage() ?: parent::getPackage());
    }

    /**
     * Set the child object that we're operating on currently.
     *
     * @param \Propel\Generator\Model\Inheritance $inheritance Inheritance
     *
     * @return void
     */
    #[\Override]
    public function setInheritanceConfiguration(Inheritance $inheritance): void
    {
        $this->child = $inheritance;
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
        if (!$this->child) {
            throw new BuildException('The MultiExtendObjectBuilder needs to be told which child class to build (via setChild() method) before it can build the stub class.');
        }

        return $this->child;
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
        $tableDesc = AbstractOMBuilder::formatOptionalDocblockContent($table->getDescription(), 2);

        $className = $this->getUnqualifiedClassName();
        $baseBuilder = $this->getQueryInheritanceBuilder($this->getInheritanceConfiguration());
        $baseClassName = $this->getClassNameFromBuilder($baseBuilder);
        $baseClassNameFq = $this->referencedClasses->getQualifiedNameForClassAlias($baseClassName);
        $timeStampBlock = $this->generateTimestampBlock();

        $classLevelComment = $this->getBuildProperty('generator.objectModel.addClassLevelComment')
            ? "
 * Skeleton subclass for representing a query for one of the subclasses of the '$tableName' table.
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *{$tableDesc}{$timeStampBlock}" : '';

        $script .= "
/**$classLevelComment
 * @template ParentQuery extends \Propel\Runtime\ActiveQuery\ModelCriteria|null = null
 * @extends \\$baseClassNameFq<ParentQuery>
 */
class $className extends $baseClassName
{\n";
    }

    /**
     * Specifies the methods that are added as part of the stub object class.
     *
     * By default there are no methods for the empty stub classes; override this method
     * if you want to change that behavior.
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
        $script .= "
}
";
    }
}
