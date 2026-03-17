<?php

declare(strict_types = 1);

namespace Propel\Generator\Builder\Om\ObjectBuilder\ColumnTypes;

use Propel\Common\Exception\SetColumnConverterException;
use Propel\Common\Util\SetColumnConverter;

class SetBinaryColumnCodeProducer extends AbstractArrayColumnCodeProducer
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
        return ['int', 'array<string>'];
    }

    /**
     * @return string
     */
    #[\Override]
    public function getDefaultValueString(): string
    {
        $defaultValue = $this->column->getPhpDefaultValue();

        return $defaultValue === null
            ? 'null'
            : (string)SetColumnConverter::convertToBitmask($defaultValue, $this->column->getValueSet());
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
     * @throws \\Propel\\Runtime\\Exception\\PropelException
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
        $this->declareClasses(
            SetColumnConverter::class,
            SetColumnConverterException::class,
        );

        $intAttribute = $this->getAttributeName();
        $arrayAttribute = $this->getDeserializedAttributeName();
        $tableMapClassName = $this->getTableMapClassName();
        $columnConstantExpression = $this->objectBuilder->getColumnConstant($this->column);

        $script .= "
        if ($arrayAttribute === null) {
            $arrayAttribute = [];
        }
        if (!$arrayAttribute && $intAttribute !== null) {
            \$valueSet = {$tableMapClassName}::getValueSet($columnConstantExpression);
            try {
                $arrayAttribute = SetColumnConverter::convertBitmaskToArray($intAttribute, \$valueSet);
            } catch (SetColumnConverterException \$e) {
                throw new PropelException('Unknown stored set key: ' . \$e->getValue(), \$e->getCode(), \$e);
            }
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
     * @param array|null \$v new value
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
        $this->declareClasses(
            SetColumnConverter::class,
            SetColumnConverterException::class,
        );
        $this->declareGlobalFunction('array_diff', 'count', 'sprintf');

        $intAttribute = $this->getAttributeName();
        $arrayAttribute = $this->getDeserializedAttributeName();
        $tableMapClassName = $this->getTableMapClassName();
        $columnConstant = $this->objectBuilder->getColumnConstant($this->getColumn());

        $script .= "
        if ($arrayAttribute === null || count(array_diff($arrayAttribute, \$v)) > 0 || count(array_diff(\$v, $arrayAttribute)) > 0) {
            \$valueSet = {$tableMapClassName}::getValueSet($columnConstant);
            try {
                \$v = SetColumnConverter::convertToBitmask(\$v, \$valueSet);
            } catch (SetColumnConverterException \$e) {
                throw new PropelException(sprintf('Value \"%s\" is not accepted in this set column', \$e->getValue()), \$e->getCode(), \$e);
            }
            if ($intAttribute !== \$v) {
                $arrayAttribute = null;
                $intAttribute = \$v;
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
        $this->declareClasses(SetColumnConverter::class);
        $tableMapClassName = $this->getTableMapClassName();
        $columnConstant = $this->objectBuilder->getColumnConstant($this->column);

        return "SetColumnConverter::convertBitmaskToArray($valueExpression, $tableMapClassName::getValueSet($columnConstant))";
    }
}
