<?php

declare(strict_types = 1);

namespace Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\UpdateColumn;

use Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression;
use function array_keys;
use function array_merge;
use function count;
use function strrpos;
use function substr;

/**
 * A column used in an insert or update, including value expression.
 */
class UpdateColumnCollector
{
    /**
     * @var array<string, \Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\UpdateColumn\AbstractUpdateColumn|scalar>
     */
    protected $updateValues = [];

    /**
     * @return void
     */
    public function __clone()
    {
        foreach ($this->updateValues as $key => $value) {
            $this->updateValues[$key] = $value instanceof AbstractColumnExpression ? clone $value : $value;
        }
    }

    /**
     * @param \Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\UpdateColumn\AbstractUpdateColumn $updateColumn
     *
     * @return void
     */
    public function setUpdateColumn(AbstractUpdateColumn $updateColumn): void
    {
        $key = $updateColumn->getColumnExpressionInQuery();
        $this->updateValues[$key] = $updateColumn;
    }

    /**
     * @param string $name A String with the name of the key.
     *
     * @return mixed The value of object at key.
     */
    public function getUpdateValue(string $name)
    {
        if (!isset($this->updateValues[$name])) {
            return null;
        }

        return $this->updateValues[$name] instanceof AbstractUpdateColumn
            ? $this->updateValues[$name]->getValue()
            : $this->updateValues[$name];
    }

    /**
     * @param string $columnIdentifier [table.]column
     *
     * @return bool
     */
    public function hasUpdateValue(string $columnIdentifier): bool
    {
        // must use array_key_exists() because the key could
        // exist but have a NULL value (that'd be valid).
        return isset($this->updateValues[$columnIdentifier]);
    }

    /**
     * @param string $name A String with the key.
     *
     * @return \Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\UpdateColumn\AbstractUpdateColumn|null
     */
    public function getUpdateColumn(string $name): ?AbstractUpdateColumn
    {
        return isset($this->updateValues[$name]) && $this->updateValues[$name] instanceof AbstractUpdateColumn
            ? $this->updateValues[$name]
            : null;
    }

    /**
     * Shortcut method to get an array of columns indexed by table.
     * <code>
     * print_r($c->getTablesColumns());
     *  => array(
     *       'book' => array('book.price', 'book.title'),
     *       'author' => array('author.first_name')
     *     )
     * </code>
     *
     * @return array<string, array<\Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\UpdateColumn\AbstractUpdateColumn|mixed>> array(table => array(table.column1, table.column2))
     */
    public function groupUpdateValuesByTable(): array
    {
        $tables = [];
        foreach ($this->updateValues as $key => $values) {
            $tableName = substr($key, 0, strrpos($key, '.') ?: null);
            $tables[$tableName][] = $values;
        }

        return $tables;
    }

    /**
     * @return array<\Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\UpdateColumn\AbstractUpdateColumn|mixed>
     */
    public function getUpdateValues(): array
    {
        return $this->updateValues;
    }

    /**
     * @param \Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\UpdateColumn\UpdateColumnCollector $collector
     *
     * @return void
     */
    public function merge(UpdateColumnCollector $collector): void
    {
        $this->updateValues = array_merge($this->updateValues, $collector->updateValues);
    }

    /**
     * @param \Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\UpdateColumn\UpdateColumnCollector $collector
     *
     * @return bool
     */
    public function equals(UpdateColumnCollector $collector): bool
    {
        if (count($this->updateValues) !== count($collector->updateValues)) {
            return false;
        }

        foreach ($this->updateValues as $columnName => $value) {
            if (
                !$collector->hasUpdateValue($columnName)
                || $collector->getUpdateValue($columnName) !== $this->getUpdateValue($columnName)
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        $this->updateValues = [];
    }

    /**
     * @return int
     */
    public function countUpdateValues(): int
    {
        return count($this->updateValues);
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return !$this->updateValues;
    }

    /**
     * @return array<string>
     */
    public function getColumnExpressionsInQuery(): array
    {
        return array_keys($this->updateValues);
    }

    /**
     * @deprecated should not be necessary.
     *
     * @param string $key A string with the key to be removed.
     *
     * @return mixed|null The removed value.
     */
    public function removeUpdateValue(string $key)
    {
        if (!isset($this->updateValues[$key])) {
            return null;
        }

        $removed = $this->updateValues[$key];
        unset($this->updateValues[$key]);

        return $removed instanceof AbstractUpdateColumn ? $removed->getValue() : $removed;
    }
}
