<?php

declare(strict_types = 1);

namespace Propel\Generator\Builder\Om\InstancePoolCodeProducer;

use Propel\Common\Util\SetColumnConverter;
use Propel\Generator\Builder\Om\AbstractSubsectionCodeProducer;
use Propel\Generator\Exception\LogicException;
use Propel\Generator\Model\Column;
use Propel\Runtime\Util\UuidConverter;
use function array_combine;
use function array_keys;
use function array_map;
use function count;
use function implode;
use function range;
use function reset;

/**
 * @template Builder of \Propel\Generator\Builder\Om\AbstractOMBuilder
 * @extends \Propel\Generator\Builder\Om\AbstractSubsectionCodeProducer<\Propel\Generator\Builder\Om\AbstractOMBuilder>
 */
class InstancePoolCodeProducer extends AbstractSubsectionCodeProducer
{
    /**
     * @param string $varName
     * @param array<\Propel\Generator\Model\Column>|null $columns
     *
     * @return string
     */
    public function buildPoolKeyFromObjectVariable(string $varName, array|null $columns = null): string
    {
        $columns ??= $this->getTable()->getPrimaryKey();
        $variableToColumn = $this->mapObjectGetterToColumn($varName, $columns);

        return $this->buildPoolKeyFromAccessorMap($variableToColumn, false);
    }

    /**
     * @param string $varName
     * @param bool $mightBeDbFormat
     * @param array<\Propel\Generator\Model\Column>|null $columns
     * @param bool $unwrapSingleElement
     *
     * @return string
     */
    public function buildPoolKeyFromArrayAccess(string $varName, bool $mightBeDbFormat, array|null $columns = null, bool $unwrapSingleElement = true): string
    {
        $columns ??= $this->getTable()->getPrimaryKey();
        $variableToColumn = $this->mapArrayValuesToColumn($varName, $columns, $unwrapSingleElement);

        return $this->buildPoolKeyFromAccessorMap($variableToColumn, $mightBeDbFormat);
    }

    /**
     * Get statement to build pool key according to column information
     *
     * i.e. <code>`serialize([$obj->getFooDate()->format('...'), (string)$obj->getBarId()])`</code>
     *
     * @param array $variableToColumn
     * @param bool $mightBeDbFormat Value might be in DB format, i.e. Date could be \DateTimeInterface or string
     *
     * @throws \Propel\Generator\Exception\LogicException
     *
     * @return string
     */
    public function buildPoolKeyFromAccessorMap(array $variableToColumn, bool $mightBeDbFormat): string
    {
        if (!$variableToColumn) {
            throw new LogicException('Cannot build pool key on table without PK');
        }
        $statementBuilder = $mightBeDbFormat
            ? [$this, 'buildPossiblyUnconvertedValueToStringExpression']
            : [$this, 'buildColumnValueToStringExpression'];
        $statements = array_map($statementBuilder, array_keys($variableToColumn), $variableToColumn);

        if (count($statements) === 1) {
            return $statements[0];
        }
        $this->declareGlobalFunction('serialize');

        $statementsCsv = implode(', ', $statements);

        return "serialize([$statementsCsv])";
    }

    /**
     * Access variable value as string according to column information
     *
     * i.e. <code>$obj->getFooDate()->format('...')`</code>
     *
     * @param string $varName
     * @param \Propel\Generator\Model\Column $col
     *
     * @return string
     */
    protected function buildColumnValueToStringExpression(string $varName, Column $col): string
    {
        return match (true) {
            $col->isBinaryEnumType() => "(string)array_search($varName, static::getValueSet(static::" . $col->getConstantName() . '))',
            $col->isBinarySetType() => '(string)' . $this->declareClass(SetColumnConverter::class) . "::convertToBitmask($varName, static::getValueSet(static::" . $col->getConstantName() . '))',
            $col->isLobType(),
            $col->isPhpObjectType() => "is_callable([$varName, '__toString']) ? (string)$varName : $varName",
            $col->isNumericType(),
            $col->isPhpPrimitiveNumericType() => "(string)$varName",
            $col->isPhpArrayType() => $this->getObjectClassName() . "::serializeArray($varName)",
            $col->isTemporalType() => "{$varName}->format('" . $this->getPlatformOrFail()->getTemporalFormatter($col) . "')",
            $col->isUuidBinaryType() => $this->declareClass(UuidConverter::class) . "::uuidToBin($varName, " . $this->builder->getUuidSwapFlagLiteral() . ')',
            default => $varName,
        };
    }

    /**
     * Access possibly stringified variable value as string according to column information
     *
     * i.e. <code>is_string($var) ? $var : $var->getFooDate()->format('...')`</code>
     *
     * @param string $varName
     * @param \Propel\Generator\Model\Column $col
     *
     * @return string
     */
    protected function buildPossiblyUnconvertedValueToStringExpression(string $varName, Column $col): string
    {
        $columnToString = $this->buildColumnValueToStringExpression($varName, $col);
        if ($col->isBinaryEnumType() || $col->isBinarySetType()) {
            $this->declareGlobalFunction('is_numeric');

            return "(is_numeric($varName) ? $varName : $columnToString)";
        }

        if ($col->isPhpArrayType() || $col->isTemporalType()) {
            $this->declareGlobalFunction('is_string');

            return "(is_string($varName) ? $varName : $columnToString)";
        }

        return $columnToString;
    }

    /**
     * Build getter expressions for columns and return as map between getter statement and Column:
     *
     * <code>
     * ['$obj->getId()' => id-Column, '$obj->getFoo()' => foo-Column]
     * </code>
     *
     * @param string $varName
     * @param array<\Propel\Generator\Model\Column> $columns
     *
     * @return array<string, \Propel\Generator\Model\Column>
     */
    protected function mapObjectGetterToColumn(string $varName, array $columns): array
    {
        $pkGetters = array_map(fn (Column $col) => "{$varName}->get" . $col->getPhpName() . '()', $columns);

        return array_combine($pkGetters, $columns);
    }

    /**
     * Build array access expressions and map to Column:
     *
     * <code>
     * ['$row[0]' => id-Column, '$row[1]' => foo-Column]
     * </code>
     *
     * @param string $varName
     * @param array<\Propel\Generator\Model\Column> $columns
     * @param bool $unwrapSingleElement
     *
     * @return array<string, \Propel\Generator\Model\Column>
     */
    protected function mapArrayValuesToColumn(string $varName, array $columns, bool $unwrapSingleElement = true): array
    {
        if (count($columns) === 1 && $unwrapSingleElement) {
            return [$varName => reset($columns)];
        }
        $rowAccess = array_map(fn (int $i) => "{$varName}[$i]", range(0, count($columns) - 1));

        return array_combine($rowAccess, $columns);
    }
}
