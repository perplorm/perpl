<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Propel\Runtime\ActiveQuery\SqlBuilder;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;
use function array_key_first;
use function array_keys;
use function count;
use function implode;
use function preg_filter;
use function range;

class InsertQuerySqlBuilder extends AbstractSqlQueryBuilder
{
    /**
     * Build an Sql Insert statment for the given criteria.
     *
     * @param \Propel\Runtime\ActiveQuery\Criteria $criteria
     *
     * @return \Propel\Runtime\ActiveQuery\SqlBuilder\PreparedStatementDto
     */
    public static function createInsertSql(Criteria $criteria): PreparedStatementDto
    {
        $builder = new self($criteria);

        return $builder->build();
    }

    /**
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return \Propel\Runtime\ActiveQuery\SqlBuilder\PreparedStatementDto
     */
    public function build(): PreparedStatementDto
    {
        $updateValuesByTable = $this->criteria->getUpdateValues()->groupUpdateValuesByTable();

        if (count($updateValuesByTable) > 1) {
            $message = 'Cannot insert into multiple tables in same query, but found tables: ' . implode(', ', array_keys($updateValuesByTable));

            throw new PropelException($message);
        }

        $tableName = array_key_first($updateValuesByTable);
        $updateValues = $updateValuesByTable[$tableName];
        if (count($updateValues) === 0) {
            throw new PropelException('Database insert attempted without anything specified to insert.');
        }
        $columnCsv = $this->buildSimpleColumnNamesCsv($updateValues);

        $numberOfColumns = count($updateValues);
        $parameterPlaceholdersCsv = $this->buildParameterPlaceholdersCsv($numberOfColumns);

        $quotedTableName = $this->quoteIdentifierTable($tableName);
        $insertStatement = "INSERT INTO $quotedTableName ($columnCsv) VALUES ($parameterPlaceholdersCsv)";
        $params = $this->buildParamsFromUpdateValues($updateValues);

        return new PreparedStatementDto($insertStatement, $params);
    }

    /**
     * @param array<\Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\UpdateColumn\AbstractUpdateColumn> $qualifiedColumnNames
     *
     * @return string
     */
    protected function buildSimpleColumnNamesCsv(array $qualifiedColumnNames): string
    {
        $columnNames = [];
        foreach ($qualifiedColumnNames as $updateColumn) {
            $columnNames[] = $updateColumn->getColumnExpressionInQuery(true, true);
        }

        return implode(',', $columnNames);
    }

    /**
     * Build a comma separated list of placeholders, i.e. ":p1,:p2,:p3" for 3 placeholders.
     *
     * @param int $numberOfPlaceholders
     *
     * @return string
     */
    protected function buildParameterPlaceholdersCsv(int $numberOfPlaceholders): string
    {
        $parameterIndexes = ($numberOfPlaceholders < 1) ? [] : range(1, $numberOfPlaceholders);
        $parameterPlaceholders = preg_filter('/^/', ':p', $parameterIndexes); // prefix each element with ':p'

        return implode(',', $parameterPlaceholders);
    }
}
