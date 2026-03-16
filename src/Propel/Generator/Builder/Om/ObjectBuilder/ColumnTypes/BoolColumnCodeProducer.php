<?php

declare(strict_types = 1);

namespace Propel\Generator\Builder\Om\ObjectBuilder\ColumnTypes;

use Propel\Generator\Builder\Om\ClassTools;
use function in_array;
use function preg_match;
use function ucfirst;

class BoolColumnCodeProducer extends ColumnCodeProducer
{
    /**
     * @param string $script
     *
     * @return void
     */
    #[\Override]
    public function addAccessorAddition(string &$script): void
    {
        $name = $this->getBooleanAccessorName();
        $isReservedName = in_array($name, ClassTools::getPropelReservedMethods(), true);
        if (!$isReservedName) {
            $this->addGetterAlias($script);
        }
    }

    /**
     * Returns the name to be used as boolean accessor name
     *
     * @return string
     */
    protected function getBooleanAccessorName(): string
    {
        $name = $this->column->getCamelCaseName();
        if (!preg_match('/^(?:is|has)(?=[A-Z])/', $name)) {
            $name = 'is' . ucfirst($name);
        }

        return $name;
    }

    /**
     * Adds the function declaration for a boolean accessor.
     *
     * @param string $script
     *
     * @return void
     */
    protected function addGetterAlias(string &$script): void
    {
        $columnName = $this->column->getPhpName();
        $clo = $this->column->getLowercasedName();
        $name = $this->getBooleanAccessorName();
        $visibility = $this->column->getAccessorVisibility();

        if (!$this->column->isLazyLoad()) {
            $script .= "
    /**
     * Get the [$clo] column value.{$this->getColumnDescriptionDoc()}
     *
     * @return bool|null
     */
    $visibility function $name()
    {
        return \$this->get$columnName();
    }\n";
        } else {
            $script .= "
    /**
     * Get the [$clo] column value.{$this->getColumnDescriptionDoc()}
     *
     * @param ConnectionInterface|null \$con
     *
     * @return bool|null
     */
    $visibility function $name(?ConnectionInterface \$con = null)
    {
        return \$this->get$columnName(\$con);
    }\n";
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
        $col = $this->column;
        $clo = $col->getLowercasedName();

        $script .= "
    /**
     * Sets the value of the [$clo] column.
     *
     * Non-boolean arguments are converted using the following rules:
     * - 1, '1', 'true', 'on', 'yes' are converted to boolean true
     * - 0, '0', 'false', 'off', 'no' are converted to boolean false
     * Check on string values is case insensitive (so 'FaLsE' is seen as 'false').{$this->getColumnDescriptionDoc()}
     *
     * @param string|int|bool|null \$v The new value
     *
     * @return \$this
     */";
    }

    /**
     * Adds setter method for boolean columns.
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
        $this->declareGlobalFunction('in_array', 'is_string', 'strtolower');
        $attribute = $this->getAttributeName();
        $columnConstant = $this->builder->getColumnConstant($this->column);

        $script .= "
        if (\$v !== null) {
            \$v = is_string(\$v)
                ? !in_array(strtolower(\$v), ['false', 'off', '-', 'no', 'n', '0', ''])
                : (bool)\$v;
        }

        if ($attribute !== \$v) {
            $attribute = \$v;
            \$this->modifiedColumns[$columnConstant] = true;
        }\n";
    }
}
