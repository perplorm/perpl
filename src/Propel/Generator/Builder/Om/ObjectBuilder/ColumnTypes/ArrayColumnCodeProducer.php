<?php

declare(strict_types = 1);

namespace Propel\Generator\Builder\Om\ObjectBuilder\ColumnTypes;

use function var_export;

class ArrayColumnCodeProducer extends AbstractArrayColumnCodeProducer
{
    /**
     * @return string
     */
    #[\Override]
    protected function getQualifiedTypeString(): string
    {
        return 'array<string>';
    }

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
     * @return string
     */
    #[\Override]
    public function getDefaultValueString(): string
    {
        $defaultValue = $this->column->getPhpDefaultValue();

        return $defaultValue !== null
            ? var_export($defaultValue, true)
            : 'null';
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
            $this->addHasArrayElement($script, 'array');
        }
    }

    /**
     * Adds the function body for an array accessor method.
     *
     * @param string $script
     *
     * @return void
     */
    #[\Override]
    protected function addAccessorBody(string &$script): void
    {
        $stringAttribute = $this->getAttributeName();
        $arrayAttribute = $this->getDeserializedAttributeName();

        $script .= "
        if ($arrayAttribute === null) {
            $arrayAttribute = !$stringAttribute ? [] : static::unserializeArray($stringAttribute);
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
            $this->addAddArrayElement($script, 'array');
            $this->addRemoveArrayElement($script, 'array');
        }
    }

    /**
     * Adds a setter for Array columns.
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
        $stringAttribute = $this->getAttributeName();
        $arrayAttribute = $this->getDeserializedAttributeName();
        $columnConstant = $this->objectBuilder->getColumnConstant($this->getColumn());

        $script .= "
        if ($arrayAttribute !== \$v) {
            $arrayAttribute = \$v;
            $stringAttribute = static::serializeArray(\$v);
            \$this->modifiedColumns[$columnConstant] = true;
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
        $this->referencedClasses->registerFunction('is_array');

        return "is_array($valueExpression) ? $valueExpression : static::unserializeArray($valueExpression)";
    }
}
