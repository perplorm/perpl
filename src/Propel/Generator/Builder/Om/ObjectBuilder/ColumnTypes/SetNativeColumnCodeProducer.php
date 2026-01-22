<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Generator\Builder\Om\ObjectBuilder\ColumnTypes;

class SetNativeColumnCodeProducer extends AbstractArrayColumnCodeProducer
{
    /**
     * @param string $script
     *
     * @return void
     */
    #[\Override]
    public function addColumnAttributes(string &$script): void
    {
        $this->addDefaultColumnAttribute($script, 'string');
        $this->addColumnAttributeConvertedDeclaration($script);
    }

    /**
     * @param string $script
     *
     * @return void
     */
    protected function addColumnAttributeConvertedDeclaration(string &$script): void
    {
        $attributeName = '$' . $this->column->getLowercasedName() . '_converted';
        $script .= "
    /**
     * @var array<string>|null
     */
    protected array|null $attributeName = null;\n";
    }

    /**
     * Build statement used in Model::applyDefaultValues()
     *
     * @return string
     */
    #[\Override]
    public function getApplyDefaultValueStatement(): string
    {
        $defaultValue = $this->getDefaultValueString();
        $statement = parent::getApplyDefaultValueStatement();
        if (!str_contains($defaultValue, ',')) {
            return $statement;
        }
        // MySQL does not support multiple default values, write values as regular modification
        $columnIdentifier = $this->objectBuilder->getColumnConstant($this->column);

        return "$statement
        \$this->modifiedColumns[$columnIdentifier] = true;";
    }

    /**
     * @param string $script
     *
     * @return void
     */
    #[\Override]
    public function addAccessorAddition(string &$script): void
    {
        if ($this->column->isNamePlural()) {
            $this->addHasArrayElement($script, 'set');
        }
    }

    /**
     * @param string $script
     * @param string $additionalParam injected from outer class (lazy load)
     *
     * @return void
     */
    #[\Override]
    protected function addAccessorComment(string &$script, string $additionalParam = ''): void
    {
        $clo = $this->column->getLowercasedName();

        $script .= "
    /**
     * Get the [$clo] column value.{$this->getColumnDescriptionDoc()}{$additionalParam}
     *
     * @return array|null
     */";
    }

    /**
     * @param string $script
     *
     * @return void
     */
    #[\Override]
    protected function addAccessorBody(string &$script): void
    {
        $clo = $this->column->getLowercasedName();
        $cloConverted = $clo . '_converted';

        $script .= "
        if (\$this->$cloConverted === null) {
            \$this->$cloConverted = [];
        }
        if (!\$this->$cloConverted && \$this->$clo !== null) {
            \$this->$cloConverted = explode(',', \$this->$clo);
        }

        return \$this->$cloConverted;";
    }

    /**
     * @param string $script
     *
     * @return void
     */
    #[\Override]
    public function addMutatorAddition(string &$script): void
    {
        if ($this->column->isNamePlural()) {
            $this->addAddArrayElement($script, 'set');
            $this->addRemoveArrayElement($script, 'set');
        }
    }

    /**
     * @param string $script
     *
     * @return void
     */
    #[\Override]
    public function addMutatorComment(string &$script): void
    {
        $clo = $this->column->getLowercasedName();

        $orNull = $this->column->isNotNull() ? '' : '|null';

        $script .= "
    /**
     * Set the value of [$clo] column.{$this->getColumnDescriptionDoc()}
     *
     * @param array{$orNull} \$v new value
     *
     * @throws \\Propel\\Runtime\\Exception\\PropelException
     *
     * @return \$this
     */";
    }

    /**
     * @see parent::addColumnMutators()
     *
     * @param string $script
     *
     * @return void
     */
    #[\Override]
    protected function addMutatorBody(string &$script): void
    {
        $col = $this->column;
        $clo = $col->getLowercasedName();
        $cloConverted = $clo . '_converted';
        $columnConstant = $this->objectBuilder->getColumnConstant($col);
        $tableMapClassName = $this->getTableMapClassName();
        $this->declareClass('\Propel\Common\Util\SetColumnConverter');

        $script .= "
        if (\$this->$cloConverted === null || array_diff(\$this->$cloConverted, \$v) || array_diff(\$v, \$this->$cloConverted)) {
            \$v = array_map('trim', \$v);
            \$valueSet = {$tableMapClassName}::getValueSet($columnConstant);
            SetColumnConverter::requireValuesInSet(\$v, \$valueSet);
            
            if (\$this->$clo !== \$v) {
                \$this->$cloConverted = null;
                \$orderedValues = SetColumnConverter::getItemsInOrder(\$v, \$valueSet);
                \$this->$clo = !\$orderedValues ? null : implode(',', \$orderedValues);
                \$this->modifiedColumns[$columnConstant] = true;
            }
        }\n";
    }
}
