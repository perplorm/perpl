<?php

declare(strict_types = 1);

namespace Propel\Generator\Builder\Om\ObjectBuilder\ColumnTypes;

abstract class AbstractDeserializableColumnCodeProducer extends ColumnCodeProducer
{
    /**
     * Default affix for attribute holding deserialized value.
     *
     * @see static::addColumnAttributeDeserialized()
     *
     * @var string
     */
    protected const DESERIALIZED_ATTRIBUTE_AFFIX = '_deserialized';

    /**
     * Get attribute types in order [database field type, deserialized type]
     *
     * @return array{string, string}
     */
    abstract protected function getQualifiedAttributeTypes(): array;

    /**
     * @param string $prefix
     *
     * @return string
     */
    protected function getDeserializedAttributeName(string $prefix = '$this->'): string
    {
        return $this->getAttributeName($prefix, static::DESERIALIZED_ATTRIBUTE_AFFIX);
    }

    /**
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    #[\Override]
    public function addColumnAttributes(string &$script): void
    {
        [$dbType, $deserializedValueType] = $this->getQualifiedAttributeTypes();
        $this->addDefaultColumnAttribute($script, $dbType);
        $this->addColumnAttributeDeserialized($script, $deserializedValueType);
    }

    /**
     * Adds attribute for deserialized value of array and object columns.
     *
     * @param string $script
     * @param string $typeHint
     *
     * @return void
     */
    protected function addColumnAttributeDeserialized(string &$script, string $typeHint): void
    {
        $valueColumnName = $this->column->getLowercasedName();
        $description = "Deserialized value of [$valueColumnName] field.";
        $columnName = $valueColumnName . static::DESERIALIZED_ATTRIBUTE_AFFIX;

        $script .= $this->buildDeclareColumnCode($columnName, $description, $typeHint);
    }

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
        $dbValueAttribute = $this->getAttributeName();
        $deserializedAttribute = $this->getDeserializedAttributeName();

        return "
            $dbValueAttribute = $valueVariable;
            $deserializedAttribute = null;";
    }

    /**
     * Build statement used in Model::clear()
     *
     * @see ObjectBuilder::addClear()}
     *
     * @return string
     */
    #[\Override]
    public function getClearValueStatement(): string
    {
        $deserializedAttribute = $this->getDeserializedAttributeName();

        return parent::getClearValueStatement() . "
        $deserializedAttribute = null;";
    }
}
