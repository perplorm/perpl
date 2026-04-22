<?php

declare(strict_types = 1);

namespace Propel\Generator\Builder\Om\ObjectBuilder\ColumnTypes;

use DateTime;
use DateTimeInterface;
use Exception;
use Propel\Generator\Exception\EngineException;
use Propel\Generator\Model\PropelTypes;
use Propel\Generator\Platform\MysqlPlatform;
use Propel\Runtime\Util\PropelDateTime;
use function date_default_timezone_set;
use function in_array;
use function is_subclass_of;
use function sprintf;
use function var_export;

class TemporalColumnCodeProducer extends AbstractDeserializableColumnCodeProducer
{
    /**
     * @var string
     */
    protected const DESERIALIZED_ATTRIBUTE_AFFIX = '_date_object';

    /**
     * @return string
     */
    #[\Override]
    protected function getQualifiedTypeString(): string
    {
        return $this->resolveColumnDateTimeClass($this->column);
    }

    /**
     * Get attribute types in order [database field type, deserialized type]
     *
     * @return array{string, string}
     */
    #[\Override]
    protected function getQualifiedAttributeTypes(): array
    {
        return ['string', $this->getQualifiedTypeString()];
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
        $stringAttribute = $this->getAttributeName();
        $objectAttribute = $this->getDeserializedAttributeName();

        $mysqlInvalidDateString = !$this->getPlatform() instanceof MysqlPlatform ? null
            : match ($this->column->getType()) {
                PropelTypes::TIMESTAMP, PropelTypes::DATETIME => '0000-00-00 00:00:00',
                PropelTypes::DATE => '0000-00-00',
                default => null
            };

        $stringAttributeValueStatement = $mysqlInvalidDateString
            ? "$valueVariable === '$mysqlInvalidDateString' ? null : $valueVariable"
            : $valueVariable;

        return "
            $objectAttribute = null;
            $stringAttribute = $stringAttributeValueStatement;";
    }

    /**
     * Build statement used in Model::applyDefaultValues()
     *
     * @return string
     */
    #[\Override]
    public function getApplyDefaultValueStatement(): string
    {
        $stringAttribute = $this->getAttributeName();
        $objectAttribute = $this->getDeserializedAttributeName();
        $defaultValue = $this->getDefaultValueString();

        return "
        $objectAttribute = $stringAttribute === $defaultValue ? $objectAttribute : null;
        $stringAttribute = $defaultValue;";
    }

    /**
     * Returns the type-casted and stringified default value for the specified
     * Column. This only works for scalar default values currently.
     *
     * @throws \Propel\Generator\Exception\EngineException
     *
     * @return string
     */
    #[\Override]
    public function getDefaultValueString(): string
    {
        $defaultValue = 'null';
        $val = $this->column->getPhpDefaultValue();
        if ($val === null) {
            return $defaultValue;
        }

        $fmt = $this->getPlatformOrFail()->getTemporalFormatter($this->column);
        try {
            if (
                !($this->getPlatform() instanceof MysqlPlatform &&
                    ($val === '0000-00-00 00:00:00' || $val === '0000-00-00'))
            ) {
                // while technically this is not a default value of NULL,
                // this seems to be closest in meaning.
                $defDt = new DateTime($val);
                $defaultValue = var_export($defDt->format((string)$fmt), true);
            }
        } catch (Exception $exception) {
            // prevent endless loop when timezone is undefined
            date_default_timezone_set('America/Los_Angeles');

            throw new EngineException(sprintf('Unable to parse default temporal value "%s" for column "%s"', $this->column->getDefaultValueString(), $this->column->getFullyQualifiedName()), 0, $exception);
        }

        return $defaultValue;
    }

    /**
     * @see ObjectBuilder::addBuildCriteriaBody()}
     * @see ObjectBuilder::addDoInsertBodyRaw()}
     *
     * @return string
     */
    #[\Override()]
    public function getAccessValueStatement(): string
    {
        $stringAttribute = $this->getAttributeName();
        $objectAttribute = $this->getDeserializedAttributeName();
        $format = $this->getPlatform()->getTemporalFormatter($this->column);

        return "{$objectAttribute}?->format('$format') ?? $stringAttribute"; // repeats old Propel behavior, changes to DateTime object propagate
    }

    /**
     * Build statement to check if current value is the default value.
     *
     * @see ObjectBuilder::addHasOnlyDefaultValuesBody()
     *
     * @return string
     */
    #[\Override]
    public function getIsDefaultValueStatement(): string
    {
        $stringAttribute = $this->getAttributeName();
        $defaultValueString = $this->getDefaultValueString();

        return "$stringAttribute === $defaultValueString";
    }

    /**
     * Adds the comment for a temporal accessor.
     *
     * @param string $script
     * @param string $additionalParam injected from outer class (lazy load)
     *
     * @return void
     */
    #[\Override]
    protected function addAccessorComment(string &$script, string $additionalParam = ''): void
    {
        $column = $this->column;
        $clo = $column->getLowercasedName();

        $dateTimeClass = $this->resolveColumnDateTimeClass($this->column);
        $orDateTimeInterface = is_subclass_of($dateTimeClass, DateTimeInterface::class) ? '|\DateTimeInterface' : '';

        $handleMysqlDate = false;
        $mysqlInvalidDateString = '';
        if ($this->getPlatform() instanceof MysqlPlatform) {
            if (in_array($column->getType(), [PropelTypes::TIMESTAMP, PropelTypes::DATETIME], true)) {
                $handleMysqlDate = true;
                $mysqlInvalidDateString = '0000-00-00 00:00:00';
            } elseif ($column->getType() === PropelTypes::DATE) {
                $handleMysqlDate = true;
                $mysqlInvalidDateString = '0000-00-00';
            }
            // 00:00:00 is a valid time, so no need to check for that.
        }

        $descriptionReturnValueNull = $column->isNotNull() ? '' : ', null if column is null';
        $descriptionReturnMysqlInvalidDate = $handleMysqlDate ? ", and 0 if column value is $mysqlInvalidDateString" : '';
        $format = $this->getPlatform()->getTemporalFormatter($this->column);

        $script .= "
    /**
     * Get the temporal [$clo] column value.{$this->getColumnDescriptionDoc()}
     *
     * @psalm-return (\$format is null ? {$dateTimeClass}|\DateTimeInterface|null : string|null)
     *
     * @param string|null \$format Datetime format string. If null, the method returns a $dateTimeClass object.
     *                      For efficiency, storage format '$format' is returned without internal transformation.{$additionalParam}
     *
     * @return {$dateTimeClass}{$orDateTimeInterface}|string|null Formatted date/time value as string or $dateTimeClass
     *                  object (if format is null){$descriptionReturnValueNull}{$descriptionReturnMysqlInvalidDate}.
     */";
    }

    /**
     * Gets the default format for a temporal column from the configuration
     *
     * @return string|null
     */
    protected function getTemporalTypeDefaultFormat(): ?string
    {
        $configKey = $this->getTemporalTypeDefaultFormatConfigKey();

        return $configKey ? $this->getBuildPropertyString($configKey) : null;
    }

    /**
     * Knows which key in the configuration holds the default format for a
     * temporal type column.
     *
     * @return string|null
     */
    protected function getTemporalTypeDefaultFormatConfigKey(): ?string
    {
        return match ($this->column->getType()) {
            PropelTypes::DATE => 'generator.dateTime.defaultDateFormat',
            PropelTypes::TIME => 'generator.dateTime.defaultTimeFormat',
            PropelTypes::TIMESTAMP, PropelTypes::DATETIME => 'generator.dateTime.defaultTimeStampFormat',
            default => null,
        };
    }

    /**
     * Adds the function declaration for a temporal accessor.
     *
     * @param string $script
     * @param string $additionalParam injected from outer class (lazy load)
     *
     * @return void
     */
    #[\Override]
    protected function addAccessorOpen(string &$script, string $additionalParam = ''): void
    {
        $cfc = $this->column->getPhpName();

        $defaultfmt = $this->getTemporalTypeDefaultFormat();
        $visibility = $this->column->getAccessorVisibility();

        $format = $defaultfmt === null ? 'null' : var_export($defaultfmt, true);

        $maybeCon = $additionalParam ? ", $additionalParam" : '';

        $script .= "
    $visibility function get$cfc(\$format = {$format}{$maybeCon})
    {";
    }

    /**
     * Adds the body of the temporal accessor.
     *
     * @param string $script
     *
     * @return void
     */
    #[\Override]
    protected function addAccessorBody(string &$script): void
    {
        $this->declareClass('DateTimeInterface');
        $stringAttribute = $this->getAttributeName();
        $objectAttribute = $this->getDeserializedAttributeName();
        $createInstanceStatement = $this->buildCreateInstanceStatement();
        $format = $this->getPlatform()->getTemporalFormatter($this->column);

        $script .= "
        if (\$format === '$format') {
            return $stringAttribute;
        }

        if (!$objectAttribute && $stringAttribute) {
            $objectAttribute = $createInstanceStatement;
        }

        return \$format !== null ? {$objectAttribute}?->format(\$format) : $objectAttribute;";
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
     * Sets the value of [$clo] column to a normalized version of the date/time value specified.{$this->getColumnDescriptionDoc()}
     *
     * @param \DateTimeInterface|string|int|null \$v Empty strings are treated as null.
     *
     * @return \$this
     */";
    }

    /**
     * Adds a setter method for date/time/timestamp columns.
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
        $this->declareClasses(DateTimeInterface::class);

        $col = $this->column;
        $stringAttribute = $this->getAttributeName();
        $objectAttribute = $this->getDeserializedAttributeName();
        $columnConstant = $this->builder->getColumnConstant($col);
        $createInstanceStatement = $this->buildCreateInstanceStatement('$v');
        $dateFormat = $this->getPlatform()->getTemporalFormatter($col);

        $additionalConditions = '';
        $def = $col->getDefaultValue();
        if ($def !== null && !$def->isExpression()) {
            // special case: mark modified when default value is provided
            $defaultValue = $this->getDefaultValueString();
            $additionalConditions = " || \$newDateString === $defaultValue";
        }

        $script .= "
        \$newDateObject = \$v instanceof DateTimeInterface ? \$v : $createInstanceStatement;
        \$newDateString = \$newDateObject?->format('$dateFormat');
        if ($stringAttribute !== \$newDateString{$additionalConditions}) {
            $objectAttribute = \$newDateObject;
            $stringAttribute = \$newDateString;
            \$this->modifiedColumns[$columnConstant] = true;
        }\n";
    }

    /**
     * @param string|null $var
     *
     * @return string
     */
    protected function buildCreateInstanceStatement(string|null $var = null): string
    {
        $this->declareClasses(PropelDateTime::class);
        $dateTimeClass = $this->resolveColumnDateTimeClass($this->column);
        $var ??= $this->getAttributeName();

        return "PropelDateTime::newInstance($var, null, '$dateTimeClass')";
    }
}
