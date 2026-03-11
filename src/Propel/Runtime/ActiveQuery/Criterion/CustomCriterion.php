<?php

declare(strict_types = 1);

namespace Propel\Runtime\ActiveQuery\Criterion;

use Propel\Runtime\ActiveQuery\Criteria;

/**
 * Specialized Criterion used for custom expressions with no binding, e.g. 'NOW() = 1'
 */
class CustomCriterion extends AbstractCriterion
{
    /**
     * Create a new instance.
     *
     * @param \Propel\Runtime\ActiveQuery\Criteria $outer The outer class (this is an "inner" class).
     * @param string $value The condition to be added to the query string
     */
    public function __construct(Criteria $outer, string $value)
    {
        $this->query = $outer;
        $this->value = $value;
        $this->init($outer);
    }

    /**
     * Appends a Prepared Statement representation of the Criterion onto the buffer
     *
     * @param string $sb The string that will receive the Prepared Statement
     * @param array $params A list to which Prepared Statement parameters will be appended
     *
     * @return void
     */
    #[\Override]
    protected function appendPsForUniqueClauseTo(string &$sb, array &$params): void
    {
        if ($this->value !== '') {
            $sb .= $this->value;
        }
    }
}
