<?php

declare(strict_types = 1);

namespace Propel\Runtime\ActiveQuery\FilterExpression;

use Propel\Runtime\ActiveQuery\Criteria;
use function array_pop;
use function end;

class CombineOperatorManager
{
    /**
     * @see addUsingOperator()
     *
     * @var string Criteria::LOGICAL_AND or Criteria::LOGICAL_OR
     */
    protected string $operator = Criteria::LOGICAL_AND;

    /**
     * Operator to go back when {@see static::resetOperator()} is called.
     *
     * @var array<string>
     */
    protected array $usedOperatorStack = [];

    /**
     * @var bool
     */
    protected bool $resetOperatorAfterUse = false;

    /**
     * @return string
     */
    public function getOperator(): string
    {
        $op = $this->operator;
        if ($this->resetOperatorAfterUse) {
            $this->resetOperator();
        }

        return $op;
    }

    /**
     * Get operator but ignore possible one-time operator.
     *
     * @return string
     */
    public function getCurrentPermanentOperator(): string
    {
        if (!$this->resetOperatorAfterUse) {
            return $this->operator;
        }

        return end($this->usedOperatorStack) ?: Criteria::LOGICAL_AND;
    }

    /**
     * @param string $operator
     * @param bool $resetAfterUse
     *
     * @return void
     */
    public function setOperator(string $operator, bool $resetAfterUse = false): void
    {
        if (!$this->resetOperatorAfterUse) {
            $this->usedOperatorStack[] = $this->operator;
        }
        $this->operator = $operator;
        $this->resetOperatorAfterUse = $resetAfterUse;
    }

    /**
     * @return void
     */
    public function resetOperator(): void
    {
        $this->operator = $this->usedOperatorStack ? array_pop($this->usedOperatorStack) : Criteria::LOGICAL_AND;
        $this->resetOperatorAfterUse = false;
    }
}
