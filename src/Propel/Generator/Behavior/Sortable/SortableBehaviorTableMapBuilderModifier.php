<?php

declare(strict_types = 1);

namespace Propel\Generator\Behavior\Sortable;

use Propel\Generator\Builder\Om\AbstractOMBuilder;
use function json_encode;
use function strtoupper;

/**
 * Behavior to add sortable methods
 */
class SortableBehaviorTableMapBuilderModifier
{
    /**
     * @var \Propel\Generator\Behavior\Sortable\SortableBehavior
     */
    protected $behavior;

    /**
     * @var \Propel\Generator\Model\Table
     */
    protected $table;

    /**
     * @param \Propel\Generator\Behavior\Sortable\SortableBehavior $behavior
     */
    public function __construct(SortableBehavior $behavior)
    {
        $this->behavior = $behavior;
        $this->table = $behavior->getTable();
    }

    /**
     * @param \Propel\Generator\Builder\Om\AbstractOMBuilder $builder
     *
     * @return string
     */
    public function staticAttributes(AbstractOMBuilder $builder): string
    {
        $tableName = $this->table->getName();
        $col = '';

        if ($this->behavior->useScope()) {
            if ($this->behavior->hasMultipleScopes()) {
                $columns = [];
                foreach ($this->behavior->getScopes() as $scope) {
                    $columns[] = "$tableName." . strtoupper($scope);
                }
                $col = json_encode($columns);
                $col = "'$col'";
            } else {
                $colNames = $this->getColumnConstant('scope_column');
                $col = "'$tableName.$colNames'";
            }
        }

        return $this->behavior->renderTemplate('tableMapSortable', [
            'rankColumn' => $this->getColumnConstant('rank_column'),
            'multiScope' => $this->behavior->hasMultipleScopes(),
            'scope' => $col,
            'tableName' => $this->table->getName(),
            'useScope' => $this->behavior->useScope(),
        ]);
    }

    /**
     * @param string $name
     *
     * @return string
     */
    protected function getColumnConstant(string $name): string
    {
        return $this->behavior->getColumnForParameter($name)->getName();
    }
}
