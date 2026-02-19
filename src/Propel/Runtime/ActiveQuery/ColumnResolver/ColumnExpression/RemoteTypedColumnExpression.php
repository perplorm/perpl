<?php

declare(strict_types = 1);

namespace Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Map\ColumnMap;

/**
 * A column that comes from a subquery or parent query with PDO type information available.
 */
class RemoteTypedColumnExpression extends RemoteColumnExpression
{
    /**
     * @var int
     */
    protected int $pdoType;

    /**
     * @var \Propel\Runtime\Map\ColumnMap|null
     */
    protected ?ColumnMap $columnMap;

    /**
     * A column that comes from a subquery or parent query with PDO type information available.
     *
     * @param \Propel\Runtime\ActiveQuery\Criteria $sourceQuery
     * @param string|null $tableAlias
     * @param string $columnName
     * @param int $pdoType
     * @param \Propel\Runtime\Map\ColumnMap|null $columnMap
     */
    public function __construct(Criteria $sourceQuery, ?string $tableAlias, string $columnName, int $pdoType, ?ColumnMap $columnMap = null)
    {
        parent::__construct($sourceQuery, $tableAlias, $columnName);
        $this->pdoType = $pdoType;
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
            && parent::equals($otherColumn)
            && $this->pdoType === $otherColumn->pdoType
            && $this->columnMap === $otherColumn->columnMap;
    }

    /**
     * @return bool
     */
    #[\Override]
    public function hasColumnMap(): bool
    {
        return (bool)$this->columnMap;
    }

    /**
     * @see \Propel\Runtime\Adapter\Pdo\PdoAdapter::bindValues()
     *
     * @param mixed $value
     *
     * @return array{type: int, value: mixed}| array{column: string, table: string|null, value: mixed}
     */
    #[\Override]
    public function buildPdoParam($value): array
    {
        if ($this->columnMap) {
            // always use columnMap if available, it can unpack values
            return [
                'column' => $this->columnMap->getName(),
                'table' => $this->columnMap->getTableName(),
                'value' => $value,
            ];
        }

        return [
            'type' => $this->pdoType,
            'value' => $value,
        ];
    }
}
