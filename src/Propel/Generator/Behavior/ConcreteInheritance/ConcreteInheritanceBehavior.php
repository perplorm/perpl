<?php

declare(strict_types = 1);

namespace Propel\Generator\Behavior\ConcreteInheritance;

use Propel\Generator\Builder\Om\ObjectBuilder;
use Propel\Generator\Builder\Om\QueryBuilder;
use Propel\Generator\Exception\InvalidArgumentException;
use Propel\Generator\Model\Behavior;
use Propel\Generator\Model\ForeignKey;
use Propel\Generator\Model\Table;
use Propel\Generator\Util\PhpParser;
use Propel\Runtime\Exception\PropelException;
use function array_flip;
use function explode;
use function get_class;
use function in_array;
use function sprintf;
use function str_replace;
use function strtolower;
use function ucfirst;

/**
 * Makes a model inherit another one. The model with this behavior gets a copy
 * of the structure of the parent model. In addition, both the ActiveRecord and
 * ActiveQuery classes will extend the related classes of the parent model.
 * Lastly (an optionally), the data from a model with this behavior is copied
 * to the parent model.
 */
class ConcreteInheritanceBehavior extends Behavior
{
    /**
     * @var string
     */
    protected const ATTRIBUTE_IS_PARENT_CHILD = 'is-parent-child';

    /**
     * @var \Propel\Generator\Builder\Om\ObjectBuilder
     */
    protected $builder;

    /**
     * Default parameters value
     *
     * @var array<string, mixed>
     */
    protected $parameters = [
        'extends' => '',
        'descendant_column' => 'descendant_class',
        'copy_data_to_parent' => 'true',
        'copy_data_to_child' => 'false',
        'schema' => '',
        'exclude_behaviors' => '',
    ];

    /**
     * @return void
     */
    #[\Override]
    public function modifyTable(): void
    {
        $table = $this->getTable();
        $parentTable = $this->getParentTable();

        // tell the parent table that it has a descendant
        if ($this->isCopyData() && !$parentTable->hasBehavior('concrete_inheritance_parent')) {
            $parentBehavior = new ConcreteInheritanceParentBehavior();
            $parentBehavior->setName('concrete_inheritance_parent');
            $parentBehavior->addParameter(['name' => 'descendant_column', 'value' => $this->getParameter('descendant_column')]);
            $parentTable->addBehavior($parentBehavior);
            // The parent table's behavior modifyTable() must be executed before this one
            $parentBehavior->getTableModifier()->modifyTable();
            $parentBehavior->setTableModified(true);
        }

        // Add the columns of the parent table
        foreach ($parentTable->getColumns() as $column) {
            if ($column->getName() == $this->getParameter('descendant_column')) {
                continue;
            }
            if ($table->hasColumn($column->getName())) {
                continue;
            }
            $copiedColumn = clone $column;
            if ($column->isAutoIncrement() && $this->isCopyData()) {
                $copiedColumn->setAutoIncrement(false);
            }
            $table->addColumn($copiedColumn);
            if ($column->isPrimaryKey() && $this->isCopyData()) {
                $fk = new ForeignKey();
                $table->addForeignKey($fk);
                $fk->loadMapping([static::ATTRIBUTE_IS_PARENT_CHILD => true]);
                $fk->setForeignTableCommonName($column->getTable()->getCommonName());
                if ($table->guessSchemaName() != $column->getTable()->guessSchemaName()) {
                    $fk->setForeignSchemaName($column->getTable()->guessSchemaName());
                }
                $fk->setOnDelete('CASCADE');
                $fk->setOnUpdate(null);
                $fk->addReference($copiedColumn, $column);
            }
        }

        // add the foreign keys of the parent table
        foreach ($parentTable->getForeignKeys() as $fk) {
            $copiedFk = clone $fk;
            $copiedFk->setName('');
            $copiedFk->setRefPhpName('');
            $this->getTable()->addForeignKey($copiedFk);
        }

        // add the indices of the parent table
        foreach ($parentTable->getIndices() as $index) {
            $copiedIndex = clone $index;
            $copiedIndex->setName('');
            $this->getTable()->addIndex($copiedIndex);
        }

        // add the unique indices of the parent table
        foreach ($parentTable->getUnices() as $unique) {
            $copiedUnique = clone $unique;
            $copiedUnique->setName('');
            $this->getTable()->addUnique($copiedUnique);
        }

        // list of Behaviors to be excluded in child table
        $excludeBehaviors = array_flip(explode(',', str_replace(' ', '', $this->getParameter('exclude_behaviors'))));

        // add the Behaviors of the parent table
        foreach ($parentTable->getBehaviors() as $behavior) {
            if (isset($excludeBehaviors[$behavior->getName()])) {
                continue;
            }

            if ($behavior->getName() === 'concrete_inheritance_parent' || $behavior->getName() === 'concrete_inheritance') {
                continue;
            }
            // validate behavior. If validate behavior already exists, clone only rules from parent
            if ($behavior->getName() === 'validate' && $table->hasBehavior('validate')) {
                /** @var \Propel\Generator\Behavior\Validate\ValidateBehavior $validateBehavior */
                $validateBehavior = $table->getBehavior('validate');
                $validateBehavior->mergeParameters($behavior->getParameters());

                continue;
            }
            $copiedBehavior = clone $behavior;
            $copiedBehavior->setTableModified(false);
            $this->getTable()->addBehavior($copiedBehavior);
        }
    }

    /**
     * @throws \Propel\Generator\Exception\InvalidArgumentException
     *
     * @return \Propel\Generator\Model\Table
     */
    protected function getParentTable(): Table
    {
        $database = $this->getTable()->getDatabase();
        $tableName = $database->getTablePrefix() . $this->getParameter('extends');
        if ($database->getPlatform()->supportsSchemas() && $this->getParameter('schema')) {
            $tableName = $this->getParameter('schema') . $database->getPlatform()->getSchemaDelimiter() . $tableName;
        }

        $table = $database->getTable($tableName);
        if (!$table) {
            throw new InvalidArgumentException(sprintf('Table "%s" used in the concrete_inheritance behavior at table "%s" not exist.', $tableName, $this->getTable()->getName()));
        }

        return $table;
    }

    /**
     * @return bool
     */
    protected function isCopyData(): bool
    {
        return $this->getParameter('copy_data_to_parent') === 'true';
    }

    /**
     * @return array<string>|bool
     */
    protected function getCopyToChild()
    {
        $parameterValue = $this->getParameter('copy_data_to_child');
        if (in_array(strtolower($parameterValue), ['true', 'false'], true)) {
            return strtolower($parameterValue) === 'true';
        }

        return explode(',', str_replace(' ', '', $parameterValue));
    }

    /**
     * @param \Propel\Generator\Builder\Om\ObjectBuilder|\Propel\Generator\Builder\Om\QueryBuilder $builder
     *
     * @return string|null
     */
    public function parentClass($builder): ?string
    {
        $parentTable = $this->getParentTable();

        $parentBuilder = match (get_class($builder)) {
            'Propel\Generator\Builder\Om\ObjectBuilder' => $builder->getStubObjectBuilder($parentTable),
            'Propel\Generator\Builder\Om\QueryBuilder' => $builder->getStubQueryBuilder($parentTable),
            default => null
        };

        return $parentBuilder
            ? $builder->declareClassFromBuilder($parentBuilder, true)
            : null;
    }

    /**
     * @return string
     */
    public function preSave(): string
    {
        if (!$this->isCopyData()) {
            return '';
        }

        $script = "\$parent = \$this->getSyncParent(\$con);
\$parent->save(\$con);
\$this->setPrimaryKey(\$parent->getPrimaryKey());
";
        if ($this->getCopyToChild()) {
            $script .= "\$this->syncParentToChild(\$parent);\n";
        }

        return $script;
    }

    /**
     * @param \Propel\Generator\Builder\Om\ObjectBuilder $builder
     *
     * @return string
     */
    public function postDelete(ObjectBuilder $builder): string
    {
        return $this->isCopyData()
            ? "\$this->getParentOrCreate(\$con)->delete(\$con);\n"
            : '';
    }

    /**
     * @param \Propel\Generator\Builder\Om\ObjectBuilder $builder
     *
     * @return string
     */
    public function objectMethods(ObjectBuilder $builder): string
    {
        $script = '';
        $this->builder = $builder;

        if ($this->isCopyData()) {
            $this->addObjectGetParentOrCreate($script);
            $this->addObjectGetSyncParent($script);
        }

        if ($this->getCopyToChild()) {
            $this->addSyncParentToChild($script);
        }

        return $script;
    }

    /**
     * Hook method called when QueryBuilder is finished.
     *
     * @see \Propel\Generator\Builder\Om\QueryBuilder::addClassClose()
     *
     * @param string $script
     * @param \Propel\Generator\Builder\Om\QueryBuilder $builder
     *
     * @return void
     */
    public function queryFilter(string &$script, QueryBuilder $builder)
    {
        $script = $this->removeInheritedMethod($script, $builder);
    }

    /**
     * @param string $script
     * @param \Propel\Generator\Builder\Om\QueryBuilder $builder
     *
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return string
     */
    protected function removeInheritedMethod(string $script, QueryBuilder $builder): string
    {
        $parser = new PhpParser($script, true);
        $foundPrune = $parser->removeMethod('prune');
        if ($foundPrune === false) {
            throw new PropelException("Could not remove method prune() from child class {$builder->getQualifiedClassName()}.");
        }

        return $parser->getCode();
    }

    /**
     * @param string $script
     *
     * @return void
     */
    protected function addSyncParentToChild(string &$script): void
    {
        $parentTable = $this->getParentTable();
        $parentClass = $this->builder->getClassNameFromBuilder($this->builder->getStubObjectBuilder($parentTable));

        $columns = $this->getCopyToChild();
        if ($columns === true) {
            $columns = $parentTable->getColumns();
        } else {
            $columnNames = $columns ?: [];
            $columns = [];
            foreach ($columnNames as $columnName) {
                $column = $this->getTable()->getColumn($columnName);
                $columns[] = $column;
            }
        }
        $nonPkColumnNamesPascalCase = [];
        foreach ($columns as $column) {
            if ($column->isPrimaryKey()) {
                // exclude primary keys, because they are already synced to child
                continue;
            }
            $nonPkColumnNamesPascalCase[] = ucfirst($column->getPhpName());
        }

        $script .= "
/**
 * This method syncs additional columns from parent to child, defined by
 * ConcreteBehavior's `copy_data_to_child` parameter.
 *
 * This method is called in preSave of child, but postSave of parent, so you
 * have basically access to generated IDs (or generated columns by triggers if you have
 * `reloadoninsert` at the parent table activated).
 *
 * @param $parentClass \$parent The parent object
 */
public function syncParentToChild($parentClass \$parent): void
{";
        foreach ($nonPkColumnNamesPascalCase as $columnNamePascalCase) {
            $script .= "
    \$this->set{$columnNamePascalCase}(\$parent->get{$columnNamePascalCase}());";
        }

        $script .= "
}\n";
    }

    /**
     * @param string $script
     *
     * @return void
     */
    protected function addObjectGetParentOrCreate(string &$script): void
    {
        $parentTable = $this->getParentTable();
        $parentClass = $this->builder->getClassNameFromBuilder($this->builder->getStubObjectBuilder($parentTable));

        $descendantColumnName = $this->getParameter('descendant_column');
        $descendantColumnPhpName = $parentTable->getColumn($descendantColumnName)->getPhpName();
        $stubObjectClassNameFq = $this->builder->getStubObjectBuilder()->getQualifiedClassName();
        $setDescendantClassExpression = "set{$descendantColumnPhpName}('$stubObjectClassNameFq')";
        $parentTableStubQueryBuilder = $this->builder->getStubQueryBuilder($parentTable);
        $parentTableStubQueryClassName = $this->builder->getClassNameFromBuilder($parentTableStubQueryBuilder);

        $script .= "
/**
 * Get or Create the parent $parentClass object of the current object
 *
 * @return $parentClass The parent object
 */
public function getParentOrCreate(?ConnectionInterface \$con = null)
{
    if (!\$this->isNew()) {
        return {$parentTableStubQueryClassName}::create()->findPk(\$this->getPrimaryKey(), \$con);
    }

    if (\$this->isPrimaryKeyNull()) {
        \$parent = new $parentClass();
        \$parent->$setDescendantClassExpression;

        return \$parent;
    }

    \$parent = {$parentTableStubQueryClassName}::create()->findPk(\$this->getPrimaryKey(), \$con);
    if (!\$parent || \$parent->getDescendantClass() !== null) {
        \$parent = new $parentClass();
        \$parent->setPrimaryKey(\$this->getPrimaryKey());
        \$parent->$setDescendantClassExpression;
    }

    return \$parent;
}\n";
    }

    /**
     * @param string $script
     *
     * @return void
     */
    protected function addObjectGetSyncParent(string &$script): void
    {
        $parentTable = $this->getParentTable();
        $parentTablePhpName = $parentTable->getPhpName();

        $script .= "
/**
 * Create or Update the parent $parentTablePhpName object.
 *
 * @return $parentTablePhpName The parent object
 */
public function getSyncParent(?ConnectionInterface \$con = null)
{
    \$parent = \$this->getParentOrCreate(\$con);";
        foreach ($parentTable->getColumns() as $column) {
            if ($column->isPrimaryKey() || $column->getName() === $this->getParameter('descendant_column')) {
                continue;
            }
            $phpName = $column->getPhpName();
            $script .= "
    \$parent->set{$phpName}(\$this->get{$phpName}());";
        }
        foreach ($parentTable->getForeignKeys() as $fk) {
            if ($fk->getAttribute(static::ATTRIBUTE_IS_PARENT_CHILD, false)) {
                continue;
            }
            $relationIdentifier = $fk->getIdentifier();
            $script .= "
    if (\$this->get{$relationIdentifier}() && \$this->get{$relationIdentifier}()->isNew()) {
        \$parent->set{$relationIdentifier}(\$this->get{$relationIdentifier}());
    }";
        }
        $script .= "

    return \$parent;
}\n";
    }
}
