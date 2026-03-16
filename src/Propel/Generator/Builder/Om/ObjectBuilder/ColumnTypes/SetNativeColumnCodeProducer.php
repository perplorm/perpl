<?php

declare(strict_types = 1);

namespace Propel\Generator\Builder\Om\ObjectBuilder\ColumnTypes;

use function str_contains;

class SetNativeColumnCodeProducer extends AbstractArrayColumnCodeProducer
{
    /**
     * @see AbstractDeserializableColumnCodeProducer::DESERIALIZED_ATTRIBUTE_AFFIX
     *
     * @var string
     */
    protected const DESERIALIZED_ATTRIBUTE_AFFIX = '_converted';

    /**
     * Get attribute types in order [database field type, deserialized type]
     *
     * @return array{string, string}
     */
    #[\Override]
    protected function getQualifiedAttributeTypes(): array
    {
        return ['string', 'array<string>'];
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
        $csvAttribute = $this->getAttributeName();
        $arrayAttribute = $this->getDeserializedAttributeName();

        $script .= "
        if ($arrayAttribute === null) {
            $arrayAttribute = [];
        }
        if (!$arrayAttribute && $csvAttribute !== null) {
            $arrayAttribute = explode(',', $csvAttribute);
        }

        return $arrayAttribute;";
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

        $script .= "
    /**
     * Set the value of [$clo] column.{$this->getColumnDescriptionDoc()}
     *
     * @param array<string>|null \$v new value
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
        $this->declareClass('\Propel\Common\Util\SetColumnConverter');

        $csvAttribute = $this->getAttributeName();
        $arrayAttribute = $this->getDeserializedAttributeName();
        $columnConstant = $this->objectBuilder->getColumnConstant($this->column);
        $tableMapClassName = $this->getTableMapClassName();

        $script .= "
        if ($arrayAttribute === null || array_diff($arrayAttribute, \$v) || array_diff(\$v, $arrayAttribute)) {
            \$v = array_map('trim', \$v);
            \$valueSet = {$tableMapClassName}::getValueSet($columnConstant);
            SetColumnConverter::requireValuesInSet(\$v, \$valueSet);
            
            if ($csvAttribute !== \$v) {
                $arrayAttribute = null;
                \$orderedValues = SetColumnConverter::getItemsInOrder(\$v, \$valueSet);
                $csvAttribute = !\$orderedValues ? null : implode(',', \$orderedValues);
                \$this->modifiedColumns[$columnConstant] = true;
            }
        }\n";
    }

    /**
     * @see \Propel\Generator\Builder\Om\ObjectBuilder::addCreateFromFilter()
     *
     * @param string $valueExpression The variable expression holding the value (i.e. '$value')
     *
     * @return string
     */
    #[\Override]
    public function buildCreateFromFilterValueExpression(string $valueExpression): string
    {
        $this->declareClasses('Propel\Common\Util\SetColumnConverter');
        $tableMapClassName = $this->getTableMapClassName();
        $columnConstant = $this->objectBuilder->getColumnConstant($this->column);

        return "SetColumnConverter::rawInputToSetItems($valueExpression, $tableMapClassName::getValueSet($columnConstant))";
    }
}
