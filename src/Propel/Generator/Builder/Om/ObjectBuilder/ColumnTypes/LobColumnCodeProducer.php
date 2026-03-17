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
        $columnConstant = $this->objectBuilder->getColumnConstant($this->column);

        $script .= "
        // Because BLOB columns are streams in PDO we have to assume that they are
        // always modified when a new value is passed in.  For example, the contents
        // of the stream itself may have changed externally.
        $serializedAttribute = \$this->writeResource(\$v);

        \$this->modifiedColumns[$columnConstant] = true;\n";
    }
}
