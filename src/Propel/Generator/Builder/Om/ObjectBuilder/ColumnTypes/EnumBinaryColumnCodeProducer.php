<?php

declare(strict_types = 1);

namespace Propel\Generator\Builder\Om\ObjectBuilder\ColumnTypes;

use Propel\Generator\Exception\EngineException;
use Propel\Runtime\Exception\PropelException;
use function array_search;
use function in_array;
use function sprintf;

class EnumBinaryColumnCodeProducer extends ColumnCodeProducer
{
    /**
     * @throws \Propel\Generator\Exception\EngineException
     *
     * @return string
     */
    #[\Override]
    public function getDefaultValueString(): string
    {
        $defaultValue = $this->column->getPhpDefaultValue();
        if ($defaultValue === null) {
            return 'null';
        }

        $valueSet = $this->column->getValueSet();
        if (!in_array($defaultValue, $valueSet)) {
            throw new EngineException(sprintf('Default Value "%s" is not among the enumerated values', $defaultValue));
        }

        return (string)array_search($defaultValue, $valueSet);
    }

    /**
     * @param string $script
     * @param string $additionalParam
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
     * @return string|null
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
        $this->declareClass(PropelException::class);

        $attribute = $this->getAttributeName();
        $columnConstant = $this->builder->getColumnConstant($this->column);
        $tableMapClassName = $this->getTableMapClassName();

        $script .= "
        if ($attribute === null) {
            return null;
        }
        \$valueSet = {$tableMapClassName}::getValueSet($columnConstant);
        if (!isset(\$valueSet[$attribute])) {
            throw new PropelException('Unknown stored enum key: ' . $attribute);
        }

        return \$valueSet[$attribute];";
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
     * @param string|null \$v new value
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
        $this->declareClass(PropelException::class);
        $this->declareGlobalFunction('array_search', 'is_int');
        $col = $this->column;
        $attribute = $this->getAttributeName();
        $tableMapClassName = $this->getTableMapClassName();
        $columnConstant = $this->builder->getColumnConstant($col);

        $script .= "
        if (\$v !== null) {
            \$valueSet = {$tableMapClassName}::getValueSet($columnConstant);
            \$keyId = array_search(\$v, \$valueSet);
            if (!is_int(\$keyId)) {
                throw new PropelException(\"Value '\$v' is not accepted in this enumerated column\");
            }
            \$v = \$keyId;
        }

        if ($attribute !== \$v) {
            $attribute = \$v;
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
        $tableMapClassName = $this->getTableMapClassName();
        $columnConstant = $this->builder->getColumnConstant($this->column);

        return "$tableMapClassName::getValueSet($columnConstant)[$valueExpression] ?? $valueExpression";
    }
}
