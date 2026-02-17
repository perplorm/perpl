<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Propel\Runtime\ActiveQuery\FilterExpression;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Criterion\Exception\InvalidValueException;
use Propel\Runtime\Adapter\SqlAdapterInterface;
use function sprintf;

/**
 * Filter expressions like "<column> <op> <value>".
 */
class ColumnFilter extends AbstractColumnFilter
{
    /**
     * @var bool|null
     */
    protected $ignoreStringCase;

    /**
     * Sets ignore case.
     *
     * @param bool|null $b True if case should be ignored.
     *
     * @return $this A modified Criterion object.
     */
    public function setIgnoreCase(?bool $b)
    {
        $this->ignoreStringCase = $b;

        return $this;
    }

    /**
     * @return bool True if case is ignored.
     */
    public function isIgnoreCase(): bool
    {
        if ($this->ignoreStringCase !== null) {
            return $this->ignoreStringCase;
        }

        $columnMap = $this->getColumnMap();
        if (!$columnMap) {
            return false;
        }

        return $columnMap->isText() && $this->query->isIgnoreCase();
    }

    /**
     * Collects a Prepared Statement representation of the Criterion onto the buffer
     *
     * @param array $paramCollector A list to which Prepared Statement parameters will be appended
     *
     * @throws \Propel\Runtime\ActiveQuery\Criterion\Exception\InvalidValueException
     *
     * @return string
     */
    #[\Override]
    protected function buildFilterClause(array &$paramCollector): string
    {
        $field = $this->queryColumn->getColumnExpressionInQuery(true);

        // NULL VALUES need special treatment because the SQL syntax is different
        // i.e. table.column IS NULL rather than table.column = null
        if ($this->value === null) {
            if ($this->operator === Criteria::EQUAL || $this->operator === Criteria::ISNULL) {
                $checkNull = Criteria::ISNULL;
            } elseif ($this->operator === Criteria::NOT_EQUAL || $this->operator === Criteria::ISNOTNULL) {
                $checkNull = Criteria::ISNOTNULL;
            } else {
                throw new InvalidValueException(sprintf('Could not build SQL for expression: `%s %s NULL`', $field, $this->operator));
            }

            return "$field$checkNull"; // Criteria::ISNULL and Criteria::ISNOTNULL are padded with spaces
        }
        if ($this->value === Criteria::CURRENT_DATE || $this->value === Criteria::CURRENT_TIME || $this->value === Criteria::CURRENT_TIMESTAMP) {
            return "$field{$this->operator}{$this->value}";
        }

        // default case, it is a normal col = value expression; value
        // will be replaced w/ '?' and will be inserted later using PDO bindValue()

        $paramVariable = $this->addParameter($paramCollector);
        if ($this->isIgnoreCase() && $this->adapter instanceof SqlAdapterInterface) {
            $paramVariable = $this->adapter->ignoreCase($paramVariable);
            $field = $this->adapter->ignoreCase($field);
        }

        return "$field{$this->operator}$paramVariable";
    }
}
