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
use function count;

/**
 * Filter statement with given PDO type
 */
class FilterClauseLiteralWithPdoTypes extends AbstractFilterClauseLiteral
{
    /**
     * @var array<int>
     */
    protected $pdoTypes;

    /**
     * @param \Propel\Runtime\ActiveQuery\Criteria $query
     * @param string $clause
     * @param mixed $value
     * @param array<int>|int $pdoType
     *
     * @throws \Propel\Runtime\ActiveQuery\Criterion\Exception\InvalidValueException
     */
    public function __construct(Criteria $query, string $clause, $value, $pdoType)
    {
        parent::__construct($query, $clause, $value);
        $this->pdoTypes = (array)$pdoType;

        if (count($this->pdoTypes) !== count((array)$this->value)) {
            throw new InvalidValueException($clause . ' - Number of values does not match number of types.');
        }
    }

    /**
     * @see FilterClauseLiteral::buildParameterByPosition()
     *
     * @param int $position
     * @param mixed $value
     *
     * @return array{table: null, type: int, value: mixed}
     */
    #[\Override]
    protected function buildParameterByPosition(int $position, $value): array
    {
        $typeIndex = count($this->pdoTypes) === 1 ? 0 : $position;

        return [
            'table' => null,
            'type' => $this->pdoTypes[$typeIndex],
            'value' => $value,
        ];
    }
}
