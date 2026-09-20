<?php

declare(strict_types = 1);

namespace Propel\Generator\Model;

use Propel\Common\Util\SetColumnConverter;
use Propel\Generator\Exception\EngineException;
use Propel\Generator\Model\Datatype\ColumnType;
use Propel\Generator\Model\Datatype\PhpDatatype;
use function is_string;
use function strtoupper;

/**
 * Type mapping for a column
 */
class TypeMapping extends MappingModel
{
    private string|null $name = null;

    private string|null $description = null;

    private int|null $size = null;

    private int|null $scale = null;

    private ColumnType $columnType;

    private string|null $sqlType = null;

    private string|null $customPhpType = null;

    private ColumnDefaultValue|null $defaultValue = null;

    private Database|null $database = null;

    /**
     * @var array<string>
     */
    protected array $valueSet = [];

    /**
     * @param \Propel\Generator\Model\Datatype\ColumnType|null $type Propel type.
     * @param string|null $sqlType SQL type.
     * @param int|null $size
     * @param int|null $scale
     */
    public function __construct(ColumnType|null $type = null, ?string $sqlType = null, ?int $size = null, ?int $scale = null)
    {
        $this->columnType = $type ?? ColumnType::VARCHAR;

        if ($size !== null) {
            $this->setSize($size);
        }

        if ($scale !== null) {
            $this->setScale($scale);
        }

        if ($sqlType) {
            $this->setSqlType($sqlType);
        }
    }

    /**
     * Copies the values from current object into passed-in mapping.
     *
     * @param \Propel\Generator\Model\TypeMapping $mapping Mapping to copy values into.
     *
     * @return void
     */
    public function copy(TypeMapping $mapping): void
    {
        $this->defaultValue = $mapping->getDefaultValue();
        $this->description = $mapping->getDescription();
        $this->name = $mapping->getName();
        $this->scale = $mapping->getScale();
        $this->size = $mapping->getSize();
        $this->sqlType = $mapping->getSqlType();
        $this->columnType = $mapping->getColumnType();
    }

    /**
     * @return void
     */
    #[\Override]
    protected function setupObject(): void
    {
        $type = $this->getAttribute('type');
        if ($type) {
            $type = strtoupper($type);
            $mappingType = ColumnType::fromLiteral($type);

            $this->copy($this->database->getPlatform()->getColumnTypeMapping($mappingType));
        }

        $this->name = $this->getAttribute('name');

        // Default value
        $defval = $this->getAttribute('defaultValue', $this->getAttribute('default'));
        if ($defval !== null) {
            $this->setDefaultValue(new ColumnDefaultValue($defval, ColumnDefaultValue::TYPE_VALUE));
        } elseif ($this->getAttribute('defaultExpr') !== null) {
            $this->setDefaultValue(new ColumnDefaultValue($this->getAttribute('defaultExpr'), ColumnDefaultValue::TYPE_EXPR));
        }

        $this->size = $this->getAttribute('size') ? (int)$this->getAttribute('size') : null;
        $this->scale = $this->getAttribute('scale') ? (int)$this->getAttribute('scale') : null;
        $this->description = $this->getAttribute('description');
    }

    /**
     * Sets the owning database object (if setup via XML).
     *
     * @param \Propel\Generator\Model\Database $database
     *
     * @return void
     */
    public function setDatabase(Database $database): void
    {
        $this->database = $database;
    }

    /**
     * Returns the owning database object (if setup via XML).
     *
     * @return \Propel\Generator\Model\Database|null
     */
    public function getDatabase(): ?Database
    {
        return $this->database;
    }

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @param string|null $description
     *
     * @return void
     */
    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string|null $name
     *
     * @return void
     */
    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return int|null
     */
    public function getScale(): ?int
    {
        return $this->scale;
    }

    /**
     * @param int|null $scale
     *
     * @return void
     */
    public function setScale(?int $scale): void
    {
        $this->scale = $scale;
    }

    /**
     * Set scale if the new value is not null.
     *
     * @param int|null $scale
     *
     * @return void
     */
    public function setScaleToValueIfNotNull(?int $scale): void
    {
        if ($scale !== null) {
            $this->scale = $scale;
        }
    }

    /**
     * @deprecated Use aptly named {@see static::setScaleToValueIfNotNull()}
     *
     * @param int|null $scale
     *
     * @return void
     */
    public function replaceScale(?int $scale): void
    {
        $this->setScaleToValueIfNotNull($scale);
    }

    /**
     * @return int|null
     */
    public function getSize(): ?int
    {
        return $this->size;
    }

    /**
     * @param int|null $size
     *
     * @return void
     */
    public function setSize(?int $size): void
    {
        $this->size = $size;
    }

    /**
     * Set size if the new value is not null.
     *
     * @param int|null $size
     *
     * @return void
     */
    public function setSizeToValueIfNotNull(?int $size): void
    {
        if ($size !== null) {
            $this->size = $size;
        }
    }

    /**
     * @deprecated Use aptly named {@see static::setSizeToValueIfNotNull()}
     *
     * @param int|null $size
     *
     * @return void
     */
    public function replaceSize(?int $size): void
    {
        $this->setSizeToValueIfNotNull($size);
    }

    /**
     * @return \Propel\Generator\Model\Datatype\ColumnType
     */
    public function getColumnType(): ColumnType
    {
        return $this->columnType;
    }

    /**
     * @param \Propel\Generator\Model\Datatype\ColumnType $columnType
     *
     * @return void
     */
    public function setColumnType(ColumnType $columnType): void
    {
        $this->columnType = $columnType;
    }

    /**
     * @return \Propel\Generator\Model\ColumnDefaultValue|null
     */
    public function getDefaultValue(): ?ColumnDefaultValue
    {
        return $this->defaultValue;
    }

    /**
     * Returns the default value, type-casted for use in PHP OM.
     *
     * @throws \Propel\Generator\Exception\EngineException
     *
     * @return array|string|int|bool|null
     */
    public function getPhpDefaultValue()
    {
        if ($this->defaultValue === null) {
            return null;
        }

        if ($this->defaultValue->isExpression()) {
            throw new EngineException('Cannot get PHP version of default value for default value EXPRESSION.');
        }

        $value = $this->defaultValue->getValue();

        return match ($this->columnType) {
            ColumnType::BOOLEAN,
            ColumnType::BOOLEAN_EMU => $this->booleanValue($value),
            ColumnType::ARRAY => MappingModel::buildDefaultValueExpressionForArray((string)$value),
            ColumnType::SET_BINARY => $this->buildDefaultValueExpressionForSet((string)$value),
            default => $value
        };
    }

    /**
     * Sets the default value.
     *
     * @param \Propel\Generator\Model\ColumnDefaultValue $value
     *
     * @return void
     */
    public function setDefaultValue(ColumnDefaultValue $value): void
    {
        $this->defaultValue = $value;
    }

    /**
     * Put a default value on the column
     *
     * @param string|int $value
     * @param bool $isExpression
     *
     * @return void
     */
    public function createDefaultValue(string|int|null $value, bool $isExpression = false): void
    {
        $type = $isExpression ? ColumnDefaultValue::TYPE_EXPR : ColumnDefaultValue::TYPE_VALUE;
        $this->defaultValue = new ColumnDefaultValue($value, $type);
    }

    /**
     * @return string
     */
    public function resolveSqlTypeName(): string
    {
        return $this->getSqlType() ?? $this->columnType->name;
    }

    /**
     * @return string|null
     */
    public function getSqlType(): ?string
    {
        return $this->sqlType;
    }

    /**
     * Sets the SQL type.
     *
     * @param string|null $sqlType
     *
     * @return void
     */
    public function setSqlType(?string $sqlType): void
    {
        $this->sqlType = $sqlType;
    }

    /**
     * @deprecated Use aptly named {@see static::setSizeToValueIfNotNull()}
     *
     * @param string|null $sqlType
     *
     * @return void
     */
    public function replaceSqlType(?string $sqlType): void
    {
        $sqlType !== null && $this->setSqlType($sqlType);
    }

    /**
     * @return string|null
     */
    public function getCustomPhpType(): string|null
    {
        return $this->customPhpType;
    }

    /**
     * @param string|null $customPhpType
     *
     * @return void
     */
    public function setCustomPhpType(string|null $customPhpType): void
    {
        $this->customPhpType = $customPhpType;
    }

    /**
     * @return string
     */
    public function resolvePhpType(): string
    {
        return $this->customPhpType
            ?: $this->columnType->toPhpTypeName();
    }

    /**
     * Returns the size and scale in brackets for use in an sql schema.
     *
     * @return string
     */
    public function getSizeDefinition(): string
    {
        return match (true) {
            $this->size === null => '',
            $this->scale !== null => "($this->size,$this->scale)",
            default => "($this->size)",
        };
    }

    /**
     * @param array<string>|string $valueSet
     *
     * @return void
     */
    public function setValueSet($valueSet): void
    {
        $this->valueSet = is_string($valueSet)
            ? SetColumnConverter::itemsCsvToArray($valueSet)
            : $valueSet;
    }

    /**
     * @return array<string>
     */
    public function getValueSet(): array
    {
        return $this->valueSet;
    }

    /**
     * @return void
     */
    public function __clone()
    {
        if ($this->defaultValue) {
            $this->defaultValue = clone $this->defaultValue;
        }
    }

    /**
     * @param \Propel\Generator\Model\Datatype\ColumnType $type
     *
     * @return static
     */
    public function cloneAs(ColumnType $type): static
    {
        $clonedMapping = clone $this;
        $clonedMapping->setColumnType($type);

        return $clonedMapping;
    }

    /**
     * @return bool
     */
    public function isPhpEnumType(): bool
    {
        return $this->isPhpUnitEnumType() || $this->isPhpBackedEnumType();
    }

    /**
     * @return bool
     */
    public function isPhpBackedEnumType(): bool
    {
        return $this->customPhpType && PhpDatatype::isPhpBackedEnumType($this->customPhpType);
    }

    /**
     * Returns whether this column's phpType is a UnitEnum (non-backed).
     *
     * @return bool
     */
    public function isPhpUnitEnumType(): bool
    {
        return $this->customPhpType && PhpDatatype::isPhpUnitEnumType($this->customPhpType);
    }
}
