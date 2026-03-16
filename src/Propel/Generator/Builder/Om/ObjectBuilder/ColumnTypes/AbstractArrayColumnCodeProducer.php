<?php

declare(strict_types = 1);

namespace Propel\Generator\Builder\Om\ObjectBuilder\ColumnTypes;

/**
 * Base class for array-based column types (array and set).
 */
abstract class AbstractArrayColumnCodeProducer extends AbstractDeserializableColumnCodeProducer
{
    /**
     * Get template strings for when this is a lazy-loaded column
     *
     * @return array{string, string, string}
     */
    protected function getLazyLoadConnectionInterfaceDeclarations(): array
    {
        return !$this->column->isLazyLoad() ? ['', '', ''] : [
            '
     * @param \Propel\Runtime\Connection\ConnectionInterface $con Optional ConnectionInterface connection fetch lazy-loaded column.',
            ', ?ConnectionInterface $con = null',
            '$con',
        ];
    }

    /**
     * Adds a tester method for an array column.
     *
     * @param string $script
     * @param string $typeDescription
     *
     * @return void
     */
    protected function addHasArrayElement(string &$script, string $typeDescription): void
    {
        $this->declareGlobalFunction('in_array');
        $clo = $this->column->getLowercasedName();
        $cfc = $this->column->getPhpName();
        $visibility = $this->column->getAccessorVisibility();
        $singularPhpName = $this->column->getPhpSingularName();

        [$conDoc, $conParam, $conVar] = $this->getLazyLoadConnectionInterfaceDeclarations();

        $script .= "
    /**
     * Test the presence of a value in the [$clo] $typeDescription column value.
     *
     * @param mixed \$value{$conDoc}
     *
     * @return bool
     */
    $visibility function has$singularPhpName(\$value{$conParam}): bool
    {
        return in_array(\$value, \$this->get$cfc($conVar));
    }\n";
    }

    /**
     * Adds a push method for an array column.
     *
     * @param string $script
     * @param string $typeDescription
     *
     * @return void
     */
    protected function addAddArrayElement(string &$script, string $typeDescription): void
    {
        $col = $this->column;
        $clo = $col->getLowercasedName();
        $cfc = $col->getPhpName();
        $visibility = $col->getAccessorVisibility();
        $singularPhpName = $col->getPhpSingularName();
        [$conDoc, $conParam, $conVar] = $this->getLazyLoadConnectionInterfaceDeclarations();

        $script .= "
    /**
     * Adds a value to the [$clo] $typeDescription column value.{$this->getColumnDescriptionDoc()}
     *
     * @param mixed \$value{$conDoc}
     *
     * @return \$this
     */
    $visibility function add$singularPhpName(\$value{$conParam})
    {
        \$currentArray = \$this->get$cfc($conVar);
        \$currentArray[] = \$value;
        \$this->set$cfc(\$currentArray);

        return \$this;
    }\n";
    }

    /**
     * Adds a remove method for an array column.
     *
     * @param string $script
     * @param string $typeDescription
     *
     * @return void
     */
    protected function addRemoveArrayElement(string &$script, string $typeDescription): void
    {
        $col = $this->column;
        $clo = $col->getLowercasedName();
        $cfc = $col->getPhpName();
        $visibility = $col->getAccessorVisibility();
        $singularPhpName = $col->getPhpSingularName();
        [$conDoc, $conParam, $conVar] = $this->getLazyLoadConnectionInterfaceDeclarations();

        $script .= "
    /**
     * Removes a value from the [$clo] $typeDescription column value.{$this->getColumnDescriptionDoc()}
     *
     * @param mixed \$value{$conDoc}
     *
     * @return \$this
     */
    $visibility function remove$singularPhpName(\$value{$conParam})
    {
        \$targetArray = [];
        foreach (\$this->get$cfc($conVar) as \$element) {
            if (\$element != \$value) {
                \$targetArray[] = \$element;
            }
        }
        \$this->set$cfc(\$targetArray);

        return \$this;
    }\n";
    }
}
