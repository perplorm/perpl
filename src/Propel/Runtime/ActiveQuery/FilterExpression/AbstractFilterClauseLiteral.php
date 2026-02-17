<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Propel\Runtime\ActiveQuery\FilterExpression;

use Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\UnresolvedColumnExpression;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Criterion\Exception\InvalidClauseException;
use function array_map;
use function array_values;
use function count;
use function explode;
use function implode;
use function is_array;
use function str_replace;
use function substr_count;

/**
 * A full filter statement given as a string, i.e. `col = ?`, `col1 = col2`, `col IS NULL`, `1=1`
 *
 * Child classes have to figure out how to get PDO types for columns.
 */
abstract class AbstractFilterClauseLiteral extends AbstractFilter
{
    /**
     * All clauses need resolved columns, otherwise they cannot be added
     * correctly to the query.
     *
     * @see \Propel\Runtime\ActiveQuery\Criteria::addFilter()
     * @see self::getLocalColumnName()
     *
     * @var array<\Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression>
     */
    protected $resolvedColumns;

    /**
     * @var string
     */
    protected $inputClause;

    /**
     * @var string
     */
    protected $clause;

    /**
     * @param \Propel\Runtime\ActiveQuery\Criteria $query
     * @param string $clause
     * @param mixed $value
     */
    public function __construct(Criteria $query, string $clause, $value = null)
    {
        parent::__construct($query, $value);
        $this->inputClause = $clause;
        $this->setNormalizedClause();
    }

    /**
     * @return void
     */
    protected function setNormalizedClause(): void
    {
        $normalizedExpression = $this->query->normalizeFilterExpression($this->inputClause);
        $this->clause = $normalizedExpression->getNormalizedFilterExpression();
        $this->resolvedColumns = $normalizedExpression->getReplacedColumns();
    }

    /**
     * @param bool $useQuoteIfEnable
     *
     * @return string
     */
    #[\Override]
    public function getLocalColumnName(bool $useQuoteIfEnable): string
    {
        return $this->resolvedColumns ? $this->resolvedColumns[0]->getColumnExpressionInQuery($useQuoteIfEnable) : '';
    }

    /**
     * Column name without table prefix.
     *
     * Will be DB column name if column could be resolved.
     *
     * @return string|null
     */
    #[\Override]
    public function getColumnName(): ?string
    {
        return $this->resolvedColumns ? $this->resolvedColumns[0]->getColumnName() : null;
    }

    /**
     * Build parameter for Propel prepared statement.
     *
     * @param int $position
     * @param mixed $value
     *
     * @return array{table: null, column?: string|\Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression, type?: int, value: mixed}
     */
    abstract protected function buildParameterByPosition(int $position, $value): array;

    /**
     * @see AbstractFilter::buildStatement()
     *
     * @return void
     */
    #[\Override]
    protected function resolveUnresolved(): void
    {
        if ($this->hasUnresolvedColumns()) {
            $this->setNormalizedClause();
        }
    }

    /**
     * @param array<array> $paramCollector A list to which Prepared Statement parameters will be appended
     *
     * @throws \Propel\Runtime\ActiveQuery\Criterion\Exception\InvalidClauseException

     * @return string
     */
    #[\Override]
    protected function buildFilterClause(array &$paramCollector): string
    {
        $numberOfPlaceholders = substr_count($this->clause, '?');
        if ($numberOfPlaceholders === 0) {
            return $this->clause;
        }

        // single value

        if (!is_array($this->value)) {
            if ($numberOfPlaceholders > 1) {
                throw new InvalidClauseException($this->clause . ' - Not enough values provided for clause');
            }
            $parameter = $this->buildParameterByPosition(0, $this->value);
            $placeholder = $this->addParameter($paramCollector, $parameter);

            return str_replace('?', $placeholder, $this->clause);
        }

        // array of values

        if ($numberOfPlaceholders > 1 && $numberOfPlaceholders !== count($this->value)) {
            throw new InvalidClauseException($this->clause . ' - Number of placeholders does not match number of values in clause.');
        }

        $buildPlaceholderList = ($numberOfPlaceholders === 1); // single placeholder means we need a list ("col IN ?" => "col in (:p1, :p2)")
        $placeholderCollector = [];
        $clause = (string)$this->clause;
        foreach (array_values($this->value) as $columnIndex => $value) {
            $parameter = $this->buildParameterByPosition($columnIndex, $value);
            $placeholderCollector[] = $this->addParameter($paramCollector, $parameter);
        }

        if ($buildPlaceholderList) {
            $placeholderList = '(' . implode(',', $placeholderCollector) . ')';
            $clause = str_replace('?', $placeholderList, $clause);
        } else {
            $zipper = fn ($clause, $placeholder) => "$clause$placeholder";
            $clauseParts = explode('?', $clause);
            $zippedParts = array_map($zipper, $clauseParts, $placeholderCollector);
            $clause = implode('', $zippedParts);
        }

        return $clause;
    }

    /**
     * @return string
     */
    #[\Override]
    public function getOperator(): string
    {
        return $this->clause;
    }

    /**
     * @return string|null
     */
    #[\Override]
    public function getTableAlias(): ?string
    {
        return $this->resolvedColumns ? $this->resolvedColumns[0]->getTableAlias() : null;
    }

    /**
     * @return bool
     */
    protected function hasUnresolvedColumns(): bool
    {
        return $this->doHasUnresolvedColumns();
    }

    /**
     * @return bool
     */
    protected function hasResolvedColumns(): bool
    {
        return $this->doHasUnresolvedColumns(true);
    }

    /**
     * @param bool $hasResolved
     *
     * @return bool
     */
    private function doHasUnresolvedColumns(bool $hasResolved = false): bool
    {
        foreach ((array)$this->resolvedColumns as $resolvedColumn) {
            if ($resolvedColumn instanceof UnresolvedColumnExpression ^ $hasResolved) {
                return true;
            }
        }

        return false;
    }
}
