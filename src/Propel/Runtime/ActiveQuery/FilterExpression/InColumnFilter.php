<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Propel\Runtime\ActiveQuery\FilterExpression;

use Propel\Runtime\ActiveQuery\Criteria;
use Traversable;
use function implode;
use function iterator_to_array;

class InColumnFilter extends AbstractColumnFilter
{
    /**
     * @param array<array> $paramCollector A list to which Prepared Statement parameters will be appended
     *
     * @return string
     */
    #[\Override]
    protected function buildFilterClause(array &$paramCollector): string
    {
        $values = ($this->value instanceof Traversable) ? iterator_to_array($this->value) : (array)$this->value;

        if (!$values) {
            return ($this->operator === Criteria::IN) ? '1<>1' : '1=1';
        }

        $bindParams = [];
        foreach ($values as $value) {
            $param = $this->buildParameterWithValue($value);
            $bindParams[] = $this->addParameter($paramCollector, $param);
        }
        $field = $this->getLocalColumnName(true);
        $paramList = implode(',', $bindParams);

        return "$field{$this->operator}($paramList)";
    }
}
