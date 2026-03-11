<?php

declare(strict_types = 1);

namespace Propel\Runtime\ActiveQuery\FilterExpression;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Adapter\Pdo\PgsqlAdapter;

class LikeColumnFilter extends ColumnFilter
{
    /**
     * @param array $paramCollector A list to which Prepared Statement parameters will be appended
     *
     * @return string
     */
    #[\Override]
    protected function buildFilterClause(array &$paramCollector): string
    {
        $field = $this->getLocalColumnName(true);
        $bindParam = $this->addParameter($paramCollector);

        if ($this->isIgnoreCase()) {
            // If selection is case insensitive use ILIKE for PostgreSQL or SQL
            // UPPER() function on column name for other databases.

            /** @var \Propel\Runtime\Adapter\SqlAdapterInterface $adapter */
            $adapter = $this->query->getAdapter();
            if ($adapter instanceof PgsqlAdapter) {
                $this->operator = ($this->operator === Criteria::LIKE || $this->operator === Criteria::ILIKE) ? Criteria::ILIKE : Criteria::NOT_ILIKE;
            } else {
                // UPPER function needs to be set on param and field
                $bindParam = $adapter->ignoreCase($bindParam);
                $field = $adapter->ignoreCase($field);
            }
        }

        return "$field{$this->operator}$bindParam";
    }
}
