<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Runtime\ActiveQuery\FilterExpression;

use Propel\Runtime\Adapter\SqlAdapterInterface;

interface ColumnFilterInterface
{
    /**
     * Builds the filter expression literal and adds its parameter data to the input buffer
     *
     * @param array<string, array{table?: string, column?: string, value: mixed}> $paramCollector A list to which Prepared Statement parameters will be appended
     *
     * @return string
     */
    public function buildStatement(array &$paramCollector): string;

    /**
     * Build parameters, possibly without building statement.
     *
     * Used for example when using query-cache behavior.
     *
     * Basic implementation does not avoid building the statement.
     *
     * @param array<string, array{table: string, column: string, value: mixed}> $paramCollector A list to which Prepared Statement parameters will be appended
     *
     * @return void
     */
    public function collectParameters(array &$paramCollector): void;

    /**
     * The full column identifier as used in query (with quotes if configured in ServiceContainer or table)
     *
     * @param bool $useQuoteIfEnable
     *
     * @return string
     */
    public function getLocalColumnName(bool $useQuoteIfEnable): string;

    /**
     * Column name without table prefix.
     *
     * Will be DB column name if column could be resolved.
     *
     * @return string|null
     */
    public function getColumnName(): ?string;

    /**
     * @return string|null
     */
    public function getTableAlias(): ?string;

    /**
     * @return string
     */
    public function getOperator(): string;

    /**
     * @return bool
     */
    public function hasValue(): bool;

    /**
     * @return mixed
     */
    public function getValue();

    /**
     * @return string
     */
    public function __toString(): string;

    /**
     * @param \Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface $filter
     *
     * @return bool
     */
    public function equals(ColumnFilterInterface $filter): bool;

    /**
     * Append filter with operator.
     *
     * @param \Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface $filter
     * @param string $conjunction
     *
     * @return static
     */
    public function addFilter(ColumnFilterInterface $filter, string $conjunction = ClauseList::AND_OPERATOR_LITERAL);

    /**
     * @param \Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface $filter
     *
     * @return static
     */
    public function addAnd(ColumnFilterInterface $filter);

    /**
     * @param \Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface $filter
     *
     * @return static
     */
    public function addOr(ColumnFilterInterface $filter);

    /**
     * @return array<\Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface>
     */
    public function getAttachedFilter(): array;

    /**
     * Get the number of clauses in the list
     *
     * @return int
     */
    public function count(): int;

    /**
     * @param \Propel\Runtime\Adapter\SqlAdapterInterface $adapter
     *
     * @return void
     */
    public function setAdapter(SqlAdapterInterface $adapter): void;

    /**
     * Check if this or any of the attached filters is a Criterion
     *
     * @return bool
     */
    public function containsCriterion(): bool;
}
