<?php

declare(strict_types = 1);

namespace Propel\Generator\Builder\Om\ObjectBuilder\ColumnTypes;

use function var_export;

class ObjectColumnCodeProducer extends AbstractDeserializableColumnCodeProducer
{
    /**
     * Get attribute types in order [database field type, deserialized type]
     *
     * @return array{string, string}
     */
    #[\Override]
    protected function getQualifiedAttributeTypes(): array
    {
        return ['resource', $this->getQualifiedTypeString() ?: 'object'];
    }

    /**
     * @return string
     */
    #[\Override]
    public function getDefaultValueString(): string
    {
        $defaultValue = $this->column->getPhpDefaultValue();
        if ($defaultValue === null) {
            return 'null';
        }
        $constructor = $this->declareClass($this->column->getPhpType());
        $defaultValueString = var_export($this->column->getPhpDefaultValue(), true);

        return "new $constructor($defaultValueString)";
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
        $resourceAttribute = $this->getAttributeName();
        $processValueStatement = $this->getPlatform()->hasStreamBlobImpl()
            ? $valueVariable
            : "\$this->writeResource($valueVariable)";

        return "
            $resourceAttribute = $processValueStatement;";
    }

    /**
     * Adds the function body for an object accessor method.
     *
     * @param string $script
     *
     * @return void
     */
    #[\Override]
    protected function addAccessorBody(string &$script): void
    {
        $this->declareGlobalFunction('is_resource', 'stream_get_contents', 'unserialize');
        $resourceAttribute = $this->getAttributeName();
        $objectAttribute = $this->getDeserializedAttributeName();

        $typeHint = $this->column->getTypeHint();
        $docHint = !$typeHint ? '' : "
                /** @var $typeHint \$deserializedString */";

        $script .= "
        if (!$objectAttribute && is_resource($resourceAttribute)) {
            \$serializedString = stream_get_contents($resourceAttribute);
            if (\$serializedString) {{$docHint}
                \$deserializedString = unserialize(\$serializedString);
                $objectAttribute = \$deserializedString;
            }
        }

        return $objectAttribute;";
    }

    /**
     * Adds a setter for Object columns.
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
        $this->declareGlobalFunction('serialize', 'stream_get_contents');
        $resourceAttribute = $this->getAttributeName();
        $objectAttribute = $this->getDeserializedAttributeName();
        $columnConstant = $this->objectBuilder->getColumnConstant($this->column);

        $script .= "
        \$serializedValue = serialize(\$v);
        if ($resourceAttribute === null || stream_get_contents($resourceAttribute) !== \$serializedValue) {
            $objectAttribute = \$v;
            $resourceAttribute = \$this->writeResource(\$serializedValue);
            \$this->modifiedColumns[$columnConstant] = true;
        }\n";
    }
}
