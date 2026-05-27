<?php

declare(strict_types = 1);

namespace Propel\Generator\Behavior\AutoAddPk;

use Propel\Generator\Model\Behavior;
use function array_merge;

/**
 * Adds a primary key to models defined without one
 */
class AutoAddPkBehavior extends Behavior
{
    public function __construct()
    {
        parent::__construct();

        $this->parameters = [
            'name' => 'id',
            'autoIncrement' => 'true',
            'type' => 'INTEGER',
        ];
    }

    /**
     * Copy the behavior to the database tables
     * Only for tables that have no Pk
     *
     * @return void
     */
    #[\Override]
    public function modifyDatabase(): void
    {
        foreach ($this->getDatabase()->getTables() as $table) {
            if (!$table->hasPrimaryKey()) {
                $b = clone $this;
                $table->addBehavior($b);
            }
        }
    }

    /**
     * Add the primary key to the current table
     *
     * @return void
     */
    #[\Override]
    public function modifyTable(): void
    {
        $table = $this->getTable();
        if (!$table->hasPrimaryKey() && !$table->hasBehavior('concrete_inheritance')) {
            $columnAttributes = array_merge(['primaryKey' => 'true'], $this->getParameters());
            $this->getTable()->addColumn($columnAttributes);
        }
    }
}
