<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\UpdateColumn;

use InvalidArgumentException;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Map\ColumnMap;

class UpdateExpression extends AbstractUpdateColumn
{
    /**
     * @var string
     */
    protected $expression;

    /**
     * @var array<int>|null
     */
    protected $pdoTypes;

    /**
     * Build update column expression with a value.
     *
     * @param \Propel\Runtime\ActiveQuery\Criteria $sourceQuery
     * @param \Propel\Runtime\Map\ColumnMap|string $columnIdentifierOrMap
     * @param string $expression
     * @param mixed $value
     * @param int|null $pdoTypes
     *
     * @return self
     */
    public static function build(Criteria $sourceQuery, $columnIdentifierOrMap, string $expression, $value = null, $pdoTypes = null)
    {
        if ($columnIdentifierOrMap instanceof ColumnMap) {
            $columnMap = $columnIdentifierOrMap;
        } else {
            $resolvedColumn = $sourceQuery->resolveColumn($columnIdentifierOrMap);
            if (!$resolvedColumn->hasColumnMap()) {
                return new self($expression, $value, $sourceQuery, $resolvedColumn->getTableAlias(), $resolvedColumn->getColumnName(), $pdoTypes);
            }
            $columnMap = $resolvedColumn->getColumnMap();
        }

        return new self($expression, $value, $sourceQuery, $columnMap->getTableName(), $columnMap->getName(), $columnMap->getPdoType(), $columnMap);
    }

    /**
     * @param string $expression
     * @param mixed $value
     * @param \Propel\Runtime\ActiveQuery\Criteria $sourceQuery
     * @param string|null $tableAlias
     * @param string $columnName
     * @param array<int>|int|null $pdoType
     * @param \Propel\Runtime\Map\ColumnMap|null $columnMap
     */
    protected function __construct(
        string $expression,
        $value,
        Criteria $sourceQuery,
        ?string $tableAlias,
        string $columnName,
        $pdoType,
        ?ColumnMap $columnMap = null
    ) {
        $numberOfParameters = self::validateNumberOfParameters($expression, $value, $pdoType, $columnMap);

        $pdoType = $pdoType === null ? null : (array)$pdoType;
        $value = $numberOfParameters === 0 ? [] : (array)$value;

        parent::__construct($value, $sourceQuery, $tableAlias, $columnName, $columnMap ? $columnMap->getPdoType() : -1, $columnMap);
        $this->expression = $expression;
        $this->pdoTypes = $pdoType;
    }

    /**
     * @param string $expression
     * @param mixed $value
     * @param array<int>|int|null $pdoType
     * @param \Propel\Runtime\Map\ColumnMap|null $columnMap
     *
     * @throws \InvalidArgumentException
     *
     * @return int
     */
    protected static function validateNumberOfParameters(string $expression, $value, $pdoType, ?ColumnMap $columnMap): int
    {
        $numberOfParameters = substr_count($expression, '?');
        $numberOfValues = is_array($value) ? count($value) : 1;
        if ($numberOfParameters != $numberOfValues) {
            throw new InvalidArgumentException("Update expression '{$expression}' has $numberOfParameters parameters, but number of values is $numberOfValues");
        }

        if ($numberOfValues > 0 && $pdoType === null && !$columnMap) {
            throw new InvalidArgumentException("Could not resolve column for expression '$expression', PDO type has to be set manually");
        }

        $numberOfPdoTypes = $pdoType === null ? 0 : count((array)$pdoType);
        if (!$columnMap && $numberOfParameters !== $numberOfPdoTypes) {
            throw new InvalidArgumentException("Update expression '{$expression}' has $numberOfParameters parameters, but number of pdo types is $numberOfPdoTypes");
        }

        return $numberOfParameters;
    }

    /**
     * Replaces question mark symbols with positional parameter placeholders (i.e. ':p2' for the second update parameter)
     *
     * @param int $positionIndex
     *
     * @return string
     */
    #[\Override]
    public function buildExpression(int &$positionIndex): string
    {
        return preg_replace_callback('/\?/', function (array $match) use (&$positionIndex) {
            return ':p' . $positionIndex++;
        }, $this->expression);
    }

    /**
     * @see \Propel\Runtime\Adapter\Pdo\PdoAdapter::bindValues()
     *
     * @param array<array{table?: ?string, column?: string, value: mixed, type?: int}> $paramCollector
     *
     * @return void
     */
    #[\Override]
    public function collectParam(array &$paramCollector): void
    {
        foreach ($this->value as $index => $value) {
            $paramCollector[] = !$this->pdoTypes
                ? $this->buildPdoParam($value)
                : [
                    'type' => $this->pdoTypes[$index],
                    'value' => $value,
                ];
        }
    }
}
