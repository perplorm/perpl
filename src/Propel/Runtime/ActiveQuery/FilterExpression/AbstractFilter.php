<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Runtime\ActiveQuery\FilterExpression;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Adapter\SqlAdapterInterface;

/**
 * Filter statemtent on a query
 */
abstract class AbstractFilter extends ClauseList implements ColumnFilterInterface
{
    /**
     * The query specifying the column
     *
     * @var \Propel\Runtime\ActiveQuery\Criteria
     */
    protected $query;

    /**
     * @var mixed
     */
    protected $value;

    /**
     * Create a new instance.
     *
     * @param \Propel\Runtime\ActiveQuery\Criteria $query
     * @param mixed $value
     */
    public function __construct(Criteria $query, $value)
    {
        $this->query = $query;
        $this->value = $value;
    }

    /**
     * @return mixed
     */
    #[\Override]
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return bool
     */
    #[\Override]
    public function hasValue(): bool
    {
        return $this->value !== null;
    }

    /**
     * @return null
     */
    public function getTableName(): ?string
    {
        return null;
    }

    /**
     * The DBAdapter which might be used to get db specific
     * variations of sql.
     *
     * @var \Propel\Runtime\Adapter\AdapterInterface
     */
    protected $adapter;

    /**
     * Set the adapter.
     *
     * The AdapterInterface might be used to get db specific variations of sql.
     *
     * @param \Propel\Runtime\Adapter\SqlAdapterInterface $adapter Value to assign to db.
     *
     * @return void
     */
    #[\Override]
    public function setAdapter(SqlAdapterInterface $adapter): void
    {
        $this->adapter = $adapter;
        foreach ($this->clauses as $clause) {
            $clause->setAdapter($adapter);
        }
    }

    /**
     * @param array<array> $paramCollector A list to which Prepared Statement parameters will be appended
     * @param array $parameter
     *
     * @return string Positional variable in query for the added parameter
     */
    protected function addParameter(array &$paramCollector, array $parameter): string
    {
        $paramCollector[] = $parameter;

        return ':p' . count($paramCollector);
    }

    /**
     * @return void
     */
    abstract protected function resolveUnresolved(): void;

    /**
     * @param array<array{table?: ?string, column?: string, value: mixed, type?: int}> $paramCollector
     *
     * @return string
     */
    #[\Override]
    final public function buildStatement(array &$paramCollector): string
    {
        $this->resolveUnresolved();
        $ownClause = $this->buildFilterClause($paramCollector);
        if (!$this->clauses) {
            return $ownClause;
        }

        $openingParens = str_repeat('(', count($this->clauses));
        $statement = $openingParens . $ownClause;
        foreach ($this->clauses as $key => $clause) {
            $conjunction = $this->conjunctions[$key];
            $filterClause = $clause->buildStatement($paramCollector);
            $statement .= " $conjunction $filterClause)";
        }

        return $statement;
    }

    /**
     * @deprecated for BC, use AbstractFilter::buildStatement().
     *
     * @param string $sb $paramCollector
     * @param array<array{table?: ?string, column?: string, value: mixed, type?: int}> $paramCollector
     *
     * @return void
     */
    public function appendPsTo(string &$sb, array &$paramCollector): void
    {
        $sb .= $this->buildStatement($paramCollector);
    }

    /**
     * Collects a Prepared Statement representation of the Filter onto the buffer
     *
     * @param array<array{table?: ?string, column?: string, value: mixed, type?: int}> $paramCollector A list to which Prepared Statement parameters will be appended
     *
     * @return string
     */
    abstract protected function buildFilterClause(array &$paramCollector): string;

    /**
     * Build parameters, possibly without building statement.
     *
     * Used for example when using query-cache behavior.
     *
     * Basic implementation does not avoid building the statement.
     *
     * @param array $paramCollector
     *
     * @return void
     */
    #[\Override]
    public function collectParameters(array &$paramCollector): void
    {
        $this->buildFilterClause($paramCollector);
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        $params = [];

        $statement = $this->buildStatement($params);
        $i = 0;
        $replacer = function (array $match) use ($params, &$i) {
            return $params[$i++]['value'];
        };

        return preg_replace_callback('/:p\d+/', $replacer, $statement);
    }

    /**
     * This method checks another Criteria to see if they contain
     * the same attributes and hashtable entries.
     *
     * @param object|null $filter
     *
     * @return bool
     */
    #[\Override]
    public function equals(?object $filter): bool
    {
        if ($this === $filter) {
            return true;
        }

        if (
            !$filter instanceof static
            // || $this->query !== $filter->query
            || $this->value !== $filter->value
        ) {
            return false;
        }

        return parent::equals($filter);
    }

    /**
     * get an array of all filter attached to this
     * recursing through all sub filter
     *
     * @return array<\Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface>
     */
    #[\Override]
    public function getAttachedFilter(): array
    {
        $criterions = parent::getAttachedFilter();
        array_unshift($criterions, $this);

        return $criterions;
    }

    /**
     * @deprecated for BC, use getAttachedFilter()
     *
     * @return array<\Propel\Runtime\ActiveQuery\Criterion\AbstractCriterion|\Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface>
     */
    public function getAttachedCriterion(): array
    {
        return $this->getAttachedFilter();
    }
}
