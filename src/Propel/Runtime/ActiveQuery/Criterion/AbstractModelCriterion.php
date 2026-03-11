<?php

declare(strict_types = 1);

namespace Propel\Runtime\ActiveQuery\Criterion;

use Propel\Runtime\ActiveQuery\Criteria;

/**
 * This is an "inner" class that describes an object in the criteria.
 */
abstract class AbstractModelCriterion extends AbstractCriterion
{
    /**
     * @var string
     */
    protected $clause = '';

    /**
     * Create a new instance.
     *
     * @param \Propel\Runtime\ActiveQuery\Criteria $outer The outer class (this is an "inner" class).
     * @param string $clause A simple pseudo-SQL clause, e.g. 'foo.BAR LIKE ?'
     * @param \Propel\Runtime\Map\ColumnMap|string $column A Column object to help escaping the value
     * @param mixed $value
     * @param string|null $tableAlias optional table alias
     */
    public function __construct(Criteria $outer, string $clause, $column, $value = null, ?string $tableAlias = null)
    {
        $this->query = $outer;
        $this->value = $value;
        $this->setColumn($column);
        if ($tableAlias) {
            $this->table = $tableAlias;
        }
        $this->clause = $clause;
        $this->init($outer);
    }

    /**
     * @return string
     */
    public function getClause(): string
    {
        return $this->clause;
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
        return parent::equals($filter)
            && $filter instanceof static
            && $this->comparison === $filter->comparison
            && $this->clause === $filter->clause;
    }
}
