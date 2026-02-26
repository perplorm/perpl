<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Generator\Behavior\AggregateColumn;

use LogicException;
use Propel\Generator\Builder\Om\AbstractOMBuilder;
use Propel\Generator\Model\Behavior;
use Propel\Generator\Model\ForeignKey;
use Propel\Generator\Model\Table;

/**
 * Keeps an aggregate column updated with related table
 *
 * @author François Zaninotto
 */
class AggregateColumnRelationBehavior extends Behavior
{
    /**
     * Default parameters value
     *
     * @var array<string, mixed>
     */
    protected $parameters = [
        'foreign_table' => '',
        'update_method' => '',
        'aggregate_name' => '',
    ];

    /**
     * @return bool
     */
    #[\Override]
    public function allowMultiple(): bool
    {
        return true;
    }

    /**
     * @param \Propel\Generator\Builder\Om\AbstractOMBuilder $builder
     *
     * @return string
     */
    public function postSave(AbstractOMBuilder $builder): string
    {
        $relationName = $this->getRelationName($builder);
        $aggregateName = $this->getParameter('aggregate_name');

        return "\$this->updateRelated{$relationName}{$aggregateName}(\$con);";
    }

    // no need for a postDelete() hook, since delete() uses Query::delete(),
    // which already has a hook

    /**
     * @param \Propel\Generator\Builder\Om\AbstractOMBuilder $builder
     *
     * @return string
     */
    public function objectAttributes(AbstractOMBuilder $builder): string
    {
        $relationName = $this->getRelationName($builder);
        $relatedClass = $builder->getClassNameFromBuilder($builder->getNewStubObjectBuilder($this->getForeignTable()));
        $aggregateName = $this->getParameter('aggregate_name');

        return "/**
 * @var $relatedClass
 */
protected \$old{$relationName}{$aggregateName};
";
    }

    /**
     * @param \Propel\Generator\Builder\Om\AbstractOMBuilder $builder
     *
     * @return string
     */
    public function objectMethods(AbstractOMBuilder $builder): string
    {
        return $this->addObjectUpdateRelated($builder);
    }

    /**
     * @param \Propel\Generator\Builder\Om\AbstractOMBuilder $builder
     *
     * @return string
     */
    protected function addObjectUpdateRelated(AbstractOMBuilder $builder): string
    {
        $relationName = $this->getRelationName($builder);

        return $this->renderTemplate('objectUpdateRelated', [
            'relationName' => $relationName,
            'aggregateName' => $this->getParameter('aggregate_name'),
            'variableName' => lcfirst($relationName),
            'updateMethodName' => $this->getParameter('update_method'),
        ]);
    }

    /**
     * @param string $script
     * @param \Propel\Generator\Builder\Om\AbstractOMBuilder $builder
     *
     * @throws \LogicException
     *
     * @return void
     */
    public function objectFilter(string &$script, AbstractOMBuilder $builder): void
    {
        $relationName = $this->getRelationName($builder);
        $varName = '$' . lcfirst($relationName);
        $aggregateName = $this->getParameter('aggregate_name');
        $relatedClass = $builder->getClassNameFromBuilder($builder->getNewStubObjectBuilder($this->getForeignTable()));
        $search = "    public function set{$relationName}(?{$relatedClass} $varName = null)
    {";
        $replace = $search . "
        // aggregate_column_relation behavior
        if (\$this->a{$relationName} !== null && $varName !== \$this->a{$relationName}) {
            \$this->old{$relationName}{$aggregateName} = \$this->a{$relationName};
        }";
        $count = 0;
        $script = str_replace($search, $replace, $script, $count);

        if ($count === 0) {
            throw new LogicException("Failed to inject aggregation code into setter 'set{$relationName}'.");
        }
    }

    /**
     * @param \Propel\Generator\Builder\Om\AbstractOMBuilder $builder
     *
     * @return string
     */
    public function preUpdateQuery(AbstractOMBuilder $builder): string
    {
        return $this->getFindRelated($builder);
    }

    /**
     * @param \Propel\Generator\Builder\Om\AbstractOMBuilder $builder
     *
     * @return string
     */
    public function preDeleteQuery(AbstractOMBuilder $builder): string
    {
        return $this->getFindRelated($builder);
    }

    /**
     * @param \Propel\Generator\Builder\Om\AbstractOMBuilder $builder
     *
     * @return string
     */
    protected function getFindRelated(AbstractOMBuilder $builder): string
    {
        $relationName = $this->getRelationName($builder);
        $aggregateName = $this->getParameter('aggregate_name');

        return "\$this->findRelated{$relationName}{$aggregateName}s(\$con);";
    }

    /**
     * @param \Propel\Generator\Builder\Om\AbstractOMBuilder $builder
     *
     * @return string
     */
    public function postUpdateQuery(AbstractOMBuilder $builder): string
    {
        return $this->getUpdateRelated($builder);
    }

    /**
     * @param \Propel\Generator\Builder\Om\AbstractOMBuilder $builder
     *
     * @return string
     */
    public function postDeleteQuery(AbstractOMBuilder $builder): string
    {
        return $this->getUpdateRelated($builder);
    }

    /**
     * @param \Propel\Generator\Builder\Om\AbstractOMBuilder $builder
     *
     * @return string
     */
    protected function getUpdateRelated(AbstractOMBuilder $builder): string
    {
        $relationName = $this->getRelationName($builder);
        $aggregateName = $this->getParameter('aggregate_name');

        return "\$this->updateRelated{$relationName}{$aggregateName}s(\$con);";
    }

    /**
     * @param \Propel\Generator\Builder\Om\AbstractOMBuilder $builder
     *
     * @return string
     */
    public function queryAttributes(AbstractOMBuilder $builder): string
    {
        $relationName = $this->getRelationName($builder);
        $variableName = '$' . lcfirst($relationName . $this->getParameter('aggregate_name'));

        $foreignKey = $this->getForeignKey();
        $aggregatedObjectCollectionBuilder = $builder->getNewObjectCollectionBuilder($foreignKey->getForeignTable());
        $aggregatedObjectCollectionClassName = $builder->declareClassFromBuilder($aggregatedObjectCollectionBuilder);
        $aggregatedObjectCollectionClassNameFq = '\\' . $aggregatedObjectCollectionBuilder->getQualifiedClassName();

        $script = "
/**
 * @var $aggregatedObjectCollectionClassNameFq|null
 */
protected ?$aggregatedObjectCollectionClassName {$variableName}s = null;\n";

        return $script;
    }

    /**
     * @param \Propel\Generator\Builder\Om\AbstractOMBuilder $builder
     *
     * @return string
     */
    public function queryMethods(AbstractOMBuilder $builder): string
    {
        $script = $this->addQueryFindRelated($builder);
        $script .= $this->addQueryUpdateRelated($builder);

        return $script;
    }

    /**
     * @param \Propel\Generator\Builder\Om\AbstractOMBuilder $builder
     *
     * @return string
     */
    protected function addQueryFindRelated(AbstractOMBuilder $builder): string
    {
        $foreignKey = $this->getForeignKey();
        $foreignQueryBuilder = $builder->getNewStubQueryBuilder($foreignKey->getForeignTable());
        $relationName = $this->getRelationName($builder);
        $foreignQueryName = $builder->declareClassFromBuilder($foreignQueryBuilder);

        $builder->declareClassNamespace(
            $foreignKey->getForeignTable()->getPhpName() . 'Query',
            $foreignKey->getForeignTable()->getNamespace(),
        );

        return $this->renderTemplate('queryFindRelated', [
            'foreignTable' => $this->getForeignTable(),
            'relationName' => $relationName,
            'aggregateName' => $this->getParameter('aggregate_name'),
            'variableName' => lcfirst($relationName . $this->getParameter('aggregate_name')),
            'foreignQueryName' => $foreignQueryName,
            'refRelationName' => $builder->getRefFKPhpNameAffix($foreignKey),
        ]);
    }

    /**
     * @param \Propel\Generator\Builder\Om\AbstractOMBuilder $builder
     *
     * @return string
     */
    protected function addQueryUpdateRelated(AbstractOMBuilder $builder): string
    {
        $relationName = $this->getRelationName($builder);

        return $this->renderTemplate('queryUpdateRelated', [
            'relationName' => $relationName,
            'aggregateName' => $this->getParameter('aggregate_name'),
            'variableName' => lcfirst($relationName . $this->getParameter('aggregate_name')),
            'updateMethodName' => $this->getParameter('update_method'),
        ]);
    }

    /**
     * @return \Propel\Generator\Model\Table|null
     */
    protected function getForeignTable(): ?Table
    {
        return $this->getTable()->getDatabase()->getTable($this->getParameter('foreign_table'));
    }

    /**
     * @return \Propel\Generator\Model\ForeignKey|null
     */
    protected function getForeignKey(): ?ForeignKey
    {
        $foreignTable = $this->getForeignTable();
        $fks = $this->getTable()->getForeignKeysReferencingTable($foreignTable->getName());

        // FIXME doesn't work when more than one fk to the same table
        return array_shift($fks);
    }

    /**
     * @param \Propel\Generator\Builder\Om\AbstractOMBuilder $builder
     *
     * @return string
     */
    protected function getRelationName(AbstractOMBuilder $builder): string
    {
        return $builder->getFKPhpNameAffix($this->getForeignKey());
    }
}
