<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Map\ColumnMap;

/**
 * A local column is used in context where the DB column is available
 */
class LocalColumnExpression extends AbstractColumnExpression
{
    /**
     * @var \Propel\Runtime\Map\ColumnMap
     */
    protected $columnMap;

    /**
     * A local column is used in context where the DB column is available.
     *
     * @param \Propel\Runtime\ActiveQuery\Criteria $sourceQuery
     * @param string $tableAlias
     * @param \Propel\Runtime\Map\ColumnMap $columnMap
     */
    public function __construct(Criteria $sourceQuery, string $tableAlias, ColumnMap $columnMap)
    {
        parent::__construct($sourceQuery, $tableAlias, $columnMap->getName());
        $this->columnMap = $columnMap;
    }

    /**
     * @return \Propel\Runtime\Map\ColumnMap|null
     */
    #[\Override]
    public function getColumnMap(): ?ColumnMap
    {
        return $this->columnMap;
    }

    /**
     * @param \Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression $otherColumn
     *
     * @return bool
     */
    #[\Override]
    public function equals(AbstractColumnExpression $otherColumn): bool
    {
        return $otherColumn instanceof static
            && $this->columnMap === $otherColumn->columnMap
            && parent::equals($otherColumn);
    }

    /**
     * @return bool
     */
    #[\Override]
    public function hasColumnMap(): bool
    {
        return true;
    }

    /**
     * @see \Propel\Runtime\Adapter\Pdo\PdoAdapter::bindValues()
     *
     * @param mixed $value
     *
     * @return array{table: ?string, column: string, value: mixed}
     */
    #[\Override]
    public function buildPdoParam($value): array
    {
        return [
            'table' => $this->columnMap->getTableName(),
            'column' => $this->columnMap->getName(),
            'value' => $value,
        ];
    }

    /**
     * @return void
     */
    public function resolveTableAlias(): void
    {
    }
}
