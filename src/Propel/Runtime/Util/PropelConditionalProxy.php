<?php

declare(strict_types = 1);

namespace Propel\Runtime\Util;

use BadMethodCallException;
use Propel\Runtime\ActiveQuery\Criteria;
use function in_array;

/**
 * Proxy for conditional statements in a fluid interface.
 * This class replaces another class for wrong statements,
 * and silently catches all calls to non-conditional method calls
 *
 * @example
 * <code>
 * $c->_if(true) // returns $c
 *     ->doStuff() // executed
 *   ->_else() // returns a PropelConditionalProxy instance
 *     ->doOtherStuff() // not executed
 *   ->_endif(); // returns $c
 * $c->_if(false) // returns a PropelConditionalProxy instance
 *     ->doStuff() // not executed
 *   ->_else() // returns $c
 *     ->doOtherStuff() // executed
 *   ->_endif(); // returns $c
 * @see Criteria
 * @template QueryClass of \Propel\Runtime\ActiveQuery\Criteria
 */
class PropelConditionalProxy
{
    /**
     * @var QueryClass
     */
    protected $criteria;

    /**
     * @var \Propel\Runtime\Util\PropelConditionalProxy<QueryClass>|null
     */
    protected $parent;

    /**
     * @var bool
     */
    protected $state;

    /**
     * @var bool
     */
    protected $wasTrue;

    /**
     * @var bool
     */
    protected $parentState;

    /**
     * @param QueryClass $criteria
     * @param mixed $cond
     * @param \Propel\Runtime\Util\PropelConditionalProxy<QueryClass>|null $proxy
     */
    public function __construct(Criteria $criteria, $cond, ?PropelConditionalProxy $proxy = null)
    {
        $this->criteria = $criteria;
        $this->wasTrue = false;
        $this->setConditionalState($cond);
        $this->parent = $proxy;

        $this->parentState = $proxy?->getConditionalState() ?? true;
    }

    /**
     * Returns a new level PropelConditionalProxy instance.
     * Allows for conditional statements in a fluid interface.
     *
     * @param mixed $cond Casts to bool for variable evaluation
     *
     * @return QueryClass|\Propel\Runtime\Util\PropelConditionalProxy<QueryClass>
     */
    public function _if($cond)
    {
        return $this->criteria->_if($cond);
    }

    /**
     * Allows for conditional statements in a fluid interface.
     *
     * @param mixed $cond Casts to bool for variable evaluation
     *
     * @return $this|QueryClass
     */
    public function _elseif($cond)
    {
        $cond = (bool)$cond; // Intentionally not typing the param to allow for evaluation inside this function

        return $this->setConditionalState(!$this->wasTrue && $cond);
    }

    /**
     * Allows for conditional statements in a fluid interface.
     *
     * @return $this|QueryClass
     */
    public function _else()
    {
        return $this->setConditionalState(!$this->state && !$this->wasTrue);
    }

    /**
     * Returns the parent object
     * Allows for conditional statements in a fluid interface.
     *
     * @return QueryClass|\Propel\Runtime\Util\PropelConditionalProxy<QueryClass>
     */
    public function _endif()
    {
        return $this->criteria->_endif();
    }

    /**
     * return the current conditional status
     *
     * @return bool
     */
    protected function getConditionalState(): bool
    {
        return $this->state && $this->parentState;
    }

    /**
     * @param mixed $cond
     *
     * @return $this|QueryClass
     */
    protected function setConditionalState($cond)
    {
        $this->state = (bool)$cond;
        $this->wasTrue = $this->wasTrue || $this->state;

        return $this->getCriteriaOrProxy();
    }

    /**
     * @return self<QueryClass>|null
     */
    public function getParentProxy(): ?self
    {
        return $this->parent;
    }

    /**
     * @return $this|QueryClass
     */
    public function getCriteriaOrProxy()
    {
        return $this->state && $this->parentState
            ? $this->criteria
            : $this;
    }

    /**
     * Catches calls to non-conditional methods when the conditional state is false.
     *
     * Most method calls are silently skipped (the condition is false, so they
     * should not execute). However, calling a conditional flow method without
     * the underscore prefix (e.g. endif() instead of _endif()) is always a bug
     * that breaks the entire query chain — so we catch that specific mistake.
     *
     * @param string $name
     * @param array $arguments
     *
     * @throws \BadMethodCallException if the method name matches a conditional flow method without underscore prefix
     *
     * @return $this
     */
    public function __call(string $name, array $arguments)
    {
        if (in_array($name, ['if', 'elseif', 'else', 'endif', 'or', 'and'], true)) {
            $criteriaClass = $this->criteria::class;

            throw new BadMethodCallException(
                "Call to undefined method {$criteriaClass}::{$name}(). Did you mean '_{$name}' (with underscore prefix)?",
            );
        }

        return $this;
    }
}
