<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Propel\Runtime\ActiveQuery\FilterExpression;

use Propel\Runtime\ActiveQuery\Criteria;

class BinaryColumnFilter extends AbstractColumnFilter
{
 /**
  * Collects a Prepared Statement representation of the Criterion onto the buffer
  *
  * @param array<array> $paramCollector A list to which Prepared Statement parameters will be appended
  *
  * @return string
  */
    #[\Override]
    protected function buildFilterClause(array &$paramCollector): string
    {
        if ($this->value === null) {
            return $this->operator === Criteria::BINARY_ALL ? '1<>1' : '1=1';
        }

        $bindParam = $this->addParameter($paramCollector);

        // With ATTR_EMULATE_PREPARES => false, we can't have two identical params, so let's add another param
        // https://github.com/propelorm/Propel2/issues/1192
        $isBinary = ($this->operator === Criteria::BINARY_ALL);
        $compareTo = $isBinary ? $this->addParameter($paramCollector) : '0';

        $field = $this->getLocalColumnName(true);

        return "$field & $bindParam = $compareTo";
    }
}
