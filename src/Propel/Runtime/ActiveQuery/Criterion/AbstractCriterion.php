<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Propel\Runtime\ActiveQuery\Criterion;

use Exception;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\FilterExpression\ClauseList;
use Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface;
use Propel\Runtime\Adapter\SqlAdapterInterface;
use Propel\Runtime\Map\ColumnMap;
use Propel\Runtime\Propel;

/**
 * This is an "inner" class that describes an object in the criteria.
 *
 * In Torque this is an inner class of the Criteria class.
 *
 * @author Hans Lellelid <hans@xmpl.org> (Propel)
 */
abstract class AbstractCriterion extends ClauseList implements ColumnFilterInterface
{
    /**
     * @var \Propel\Runtime\ActiveQuery\Criteria
     */
    protected $query;

    /**
     * @var mixed
     */
    protected $value;

    /**
     * Comparison value.
     *
     * @var string
     */
    protected $comparison;

    /**
     * Table name (as given in statement, could be alias)
     *
     * @var string|null
     */
    protected $table;

    /**
     * Real table name
     *
     * @var string
     */
    protected $realtable;

    /**
     * Column name
     *
     * @var string
     */
    protected $column;

    /**
     * The DBAdapter which might be used to get db specific
     * variations of sql.
     *
     * @var \Propel\Runtime\Adapter\SqlAdapterInterface
     */
    protected $db;

    /**
     * Create a new instance.
     *
     * @param \Propel\Runtime\ActiveQuery\Criteria $outer The outer class (this is an "inner" class).
     * @param \Propel\Runtime\Map\ColumnMap|string $column TABLE.COLUMN format.
     * @param mixed $value
     * @param string|null $comparison
     */
    public function __construct(Criteria $outer, $column, $value, ?string $comparison = null)
    {
        $this->query = $outer;
        $this->value = $value;
        $this->setColumn($column);
        $this->comparison = ($comparison === null) ? Criteria::EQUAL : $comparison;
        $this->init($outer);
    }

    /**
     * Init some properties with the help of outer class
     *
     * @param \Propel\Runtime\ActiveQuery\Criteria $criteria The outer class
     *
     * @return void
     */
    public function init(Criteria $criteria): void
    {
        try {
            $db = Propel::getServiceContainer()->getAdapter($criteria->getDbName());
            $this->setAdapter($db);
        } catch (Exception $e) {
            // we are only doing this to allow easier debugging, so
            // no need to throw up the exception, just make note of it.
            Propel::log('Could not get a AdapterInterface, sql may be wrong', Propel::LOG_ERR);
        }

        // init $this->realtable
        $realtable = $criteria->getTableForAlias((string)$this->table);
        $this->realtable = $realtable ?: $this->table;
    }

    /**
     * Set the $column and $table properties based on a column name or object
     *
     * @param \Propel\Runtime\Map\ColumnMap|string $column
     *
     * @return void
     */
    protected function setColumn($column): void
    {
        if ($column instanceof ColumnMap) {
            $this->column = $column->getName();
            $this->table = $column->getTableMap()->getName();
        } else {
            $dotPos = strrpos($column, '.');
            if ($dotPos === false) {
                // no dot => aliased column
                $this->table = null;
                $this->column = $column;
            } else {
                $this->table = substr($column, 0, $dotPos);
                $this->column = substr($column, $dotPos + 1, strlen($column));
            }
        }
    }

    /**
     * @deprecated use aptly named AbstractCriterion::getColumnName()
     * Get the column name.
     *
     * @return string|null A String with the column name.
     */
    public function getColumn(): ?string
    {
        return $this->getColumnName();
    }

    /**
     * Get the column name.
     *
     * @return string|null A String with the column name.
     */
    #[\Override]
    public function getColumnName(): ?string
    {
        return $this->column;
    }

    /**
     * @param bool $useQuoteIfEnable
     *
     * @return string
     */
    #[\Override]
    public function getLocalColumnName(bool $useQuoteIfEnable): string
    {
        return $useQuoteIfEnable
            ? $this->query->quoteColumnIdentifier($this->table, $this->column)
            : $this->table . '.' . $this->column;
    }

    /**
     * Set the table name.
     *
     * @param string $name A String with the table name.
     *
     * @return void
     */
    public function setTable(string $name): void
    {
        $this->table = $name;
    }

    /**
     * @deprecated use AbstractCriterion::getTableAlias()
     *
     * @return string|null A String with the table name.
     */
    public function getTable(): ?string
    {
        return $this->getTableAlias();
    }

    /**
     * Get the table name.
     *
     * @return string|null A String with the table name.
     */
    #[\Override]
    public function getTableAlias(): ?string
    {
        return $this->table;
    }

    /**
     * Get the comparison.
     *
     * @return string A String with the comparison.
     */
    public function getComparison(): string
    {
        return $this->comparison;
    }

    /**
     * @return string A String with the comparison.
     */
    #[\Override]
    public function getOperator(): string
    {
        return $this->comparison;
    }

    /**
     * Get the value.
     *
     * @return mixed An Object with the value.
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
     * Get the adapter.
     *
     * The AdapterInterface which might be used to get db specific
     * variations of sql.
     *
     * @return \Propel\Runtime\Adapter\SqlAdapterInterface value of db.
     */
    public function getAdapter(): SqlAdapterInterface
    {
        return $this->db;
    }

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
        $this->db = $adapter;
        foreach ($this->clauses as $clause) {
            $clause->setAdapter($adapter);
        }
    }

    /**
     * @param array $paramCollector
     *
     * @return string
     */
    #[\Override]
    public function buildStatement(array &$paramCollector): string
    {
        $rawExpression = $this->buildCriterionExpression($paramCollector);
        $expression = $this->query->replaceColumnNames($rawExpression);
        if (!$this->clauses) {
            return $expression;
        }

        // if there are sub criterions, they must be combined to this criterion
        $statement = str_repeat('(', count($this->clauses)) . $expression;
        foreach ($this->clauses as $key => $clause) {
            $conjunction = $this->conjunctions[$key];
            $clause = $clause->buildStatement($paramCollector);
            $statement .= " $conjunction $clause)";
        }

        return $statement;
    }

    /**
     * @param array $paramCollector
     *
     * @return void
     */
    #[\Override]
    public function collectParameters(array &$paramCollector): void
    {
        $this->buildStatement($paramCollector);
    }

    /**
     * @deprecated use AbstractCriterion::buildStatement
     *
     * Appends a Prepared Statement representation of the Criterion
     * onto the buffer.
     *
     * @param string $sb The string that will receive the Prepared Statement
     * @param array $params A list to which Prepared Statement parameters will be appended
     *
     * @return void
     *
     *                                expression into proper SQL.
     */
    public function appendPsTo(string &$sb, array &$params): void
    {
        $sb .= $this->buildStatement($params);
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        $params = [];
        $sb = $this->buildStatement($params);

        return $sb;
    }

    /**
     * Appends a Prepared Statement representation of the Criterion onto the buffer
     *
     * @param array $params A list to which Prepared Statement parameters will be appended
     *
     * @return string
     */
    protected function buildCriterionExpression(array &$params): string
    {
        $expression = '';
        $this->appendPsForUniqueClauseTo($expression, $params);

        return $expression;
    }

    /**
     * Appends a Prepared Statement representation of the Criterion onto the buffer
     *
     * @param string $sb The string that will receive the Prepared Statement
     * @param array $params A list to which Prepared Statement parameters will be appended
     *
     * @return void
     */
    abstract protected function appendPsForUniqueClauseTo(string &$sb, array &$params): void;

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

        if (!$filter instanceof static) {
            return false;
        }

        if (
            $this->table !== $filter->getTableAlias()
            && $this->column !== $filter->getColumnName()
            && $this->comparison !== $filter->getComparison()
        ) {
            return false;
        }

        return parent::equals($filter);
    }

    /**
     * @deprecated use getAttachedFilter()
     *
     * @return array<\Propel\Runtime\ActiveQuery\Criterion\AbstractCriterion|\Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface>
     */
    public function getAttachedCriterion(): array
    {
        return $this->getAttachedFilter();
    }

    /**
     * get an array of all criterion attached to this
     * recursing through all sub criterion
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
     * Check if this or any of the attached filters is a Criterion
     *
     * @return bool
     */
    #[\Override]
    public function containsCriterion(): bool
    {
        return true;
    }
}
