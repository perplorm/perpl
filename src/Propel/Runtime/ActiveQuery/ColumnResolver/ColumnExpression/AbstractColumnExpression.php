<?php

declare(strict_types = 1);

namespace Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression;

use LogicException;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Map\ColumnMap;

/**
 * Base class for various types of column expressions:
 *  - column comes from a table scan or join -> LocalColumnExpression
 *  - column comes from a subquery or parent query -> RemoteColumnExpression
 *  - column could not be resolved, possibly because it was not yet added to the query -> UnresolvedColumnExpression
 */
abstract class AbstractColumnExpression
{
    /**
     * The query object where the expression is used.
     *
     * @var \Propel\Runtime\ActiveQuery\Criteria
     */
    protected Criteria $sourceQuery;

    /**
     * @var string|null
     */
    protected $tableAlias;

    /**
     * Name of column without table alias in format used in query,
     * i.e. 'id' or 'Id'
     *
     * @var string
     */
    protected $columnName;

    /**
     * @param \Propel\Runtime\ActiveQuery\Criteria $sourceQuery
     * @param string|null $tableAlias
     * @param string $columnName The column name without table alias.
     */
    public function __construct(Criteria $sourceQuery, ?string $tableAlias, string $columnName)
    {
        $this->sourceQuery = $sourceQuery;
        $this->tableAlias = $tableAlias;
        $this->columnName = $columnName;
    }

    /**
     * @param bool $useQuotesIfEnabled
     * @param bool $columnNameOnly
     *
     * @return string
     */
    public function getColumnExpressionInQuery(bool $useQuotesIfEnabled = false, bool $columnNameOnly = false): string
    {
        $tableAlias = $columnNameOnly ? null : $this->getTableAlias();

        if (!$useQuotesIfEnabled) {
            return $tableAlias ? "{$tableAlias}.{$this->columnName}" : $this->columnName;
        }
        $tableMap = $this->hasColumnMap() ? $this->getColumnMap()->getTableMap() : null;

        return $this->sourceQuery->quoteColumnIdentifier($tableAlias, $this->columnName, $tableMap);
    }

    /**
     * @param \Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression $otherColumn
     *
     * @return bool
     */
    public function equals(AbstractColumnExpression $otherColumn): bool
    {
        return $otherColumn instanceof static
            && $this->columnName === $otherColumn->columnName
            && $this->tableAlias === $otherColumn->tableAlias;
    }

    /**
     * @return \Propel\Runtime\ActiveQuery\Criteria|null
     */
    public function getSourceQuery(): ?Criteria
    {
        return $this->sourceQuery;
    }

    /**
     * The raw column name, i.e. 'author_id' for 'Book.AuthorId'
     *
     * @return string
     */
    public function getColumnName(): string
    {
        return $this->hasColumnMap() ? $this->getColumnMap()->getName() : $this->columnName;
    }

    /**
     * @return string|null
     */
    public function getTableAlias(): ?string
    {
        return $this->tableAlias;
    }

    /**
     * @return bool
     */
    public function hasColumnMap(): bool
    {
        return false;
    }

    /**
     * @throws \LogicException
     *
     * @return \Propel\Runtime\Map\ColumnMap|null
     */
    public function getColumnMap(): ?ColumnMap
    {
        throw new LogicException('should never be called, guard by checking AbstractColumnExpression::hasColumnMap() first.');
    }

    /**
     * @param array<\Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression>|null $columnExpressions
     *
     * @return \Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression|null
     */
    public static function findFirstColumnExpressionWithColumnMap(?array $columnExpressions): ?AbstractColumnExpression
    {
        foreach ($columnExpressions ?? [] as $col) {
            if ($col->hasColumnMap()) {
                return $col;
            }
        }

        return null;
    }

    /**
     * @see \Propel\Runtime\Adapter\Pdo\PdoAdapter::bindValues()
     *
     * @param mixed $value
     *
     * @return array{table?: ?string, column?: string, value: mixed, type?: int}
     */
    public function buildPdoParam($value): array
    {
        return [
            'table' => $this->getTableAlias(),
            'column' => $this->getColumnName(),
            'value' => $value,
        ];
    }
}
