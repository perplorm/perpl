<?php

declare(strict_types = 1);

namespace Propel\Generator\Builder\Om\ObjectBuilder\ColumnTypes;

class LobColumnCodeProducer extends ColumnCodeProducer
{
    /**
     * Build statement used in Model::hydrate()
     *
     * @see ObjectBuilder::addHydrateBody()}
     *
     * @param string $valueVariable
     *
     * @return string
     */
    #[\Override]
    public function getHydrateStatement(string $valueVariable): string
    {
        $serializedAttribute = $this->getAttributeName();

        return $this->getPlatform()->hasStreamBlobImpl()
        ? parent::getHydrateStatement($valueVariable)
        : "
            $serializedAttribute = \$this->writeResource($valueVariable);";
    }

    /**
     * Adds a setter for BLOB columns.
     *
     * @see parent::addColumnMutators()
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    #[\Override]
    protected function addMutatorBody(string &$script): void
    {
        $serializedAttribute = $this->getAttributeName();
        $columnConstant = $this->builder->getColumnConstant($this->column);

        $script .= "
        $serializedAttribute = \$this->writeResource(\$v);
        \$this->modifiedColumns[$columnConstant] = true;\n";
    }
}
