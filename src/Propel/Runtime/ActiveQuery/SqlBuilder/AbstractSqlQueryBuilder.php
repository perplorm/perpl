<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Propel\Runtime\ActiveQuery\SqlBuilder;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface;
use Propel\Runtime\Propel;
use function strrpos;
use function substr;

/**
 * This class produces the base object class (e.g. BaseMyTable) which contains
 * all the custom-built accessor and setter methods.
 */
abstract class AbstractSqlQueryBuilder
{
    /**
     * @var \Propel\Runtime\ActiveQuery\Criteria
     */
    protected $criteria;

    /**
     * @var \Propel\Runtime\Adapter\SqlAdapterInterface
     */
    protected $adapter;

    /**
     * @var \Propel\Runtime\Map\DatabaseMap
     */
    protected $dbMap;

    /**
     * @param \Propel\Runtime\ActiveQuery\Criteria $criteria
     */
    public function __construct(Criteria $criteria)
    {
        $this->criteria = $criteria;

        $dbName = $criteria->getDbName();
        $serviceContainer = Propel::getServiceContainer();

        /** @var \Propel\Runtime\Adapter\SqlAdapterInterface $adapter */
        $adapter = $serviceContainer->getAdapter($dbName);
        $this->adapter = $adapter;

        $this->dbMap = $serviceContainer->getDatabaseMap($dbName);
    }

    /**
     * @psalm-return array{null|string, null|string}
     *
     * @param string|null $tableName
     *
     * @return array<string>
     */
    protected function getTableNameWithAlias(?string $tableName): array
    {
        $realTableName = $this->criteria->getTableForAlias($tableName);
        if (!$realTableName) {
            return [$tableName, $tableName];
        }
        $aliasedTableName = "$realTableName $tableName";

        return [$realTableName, $aliasedTableName];
    }

    /**
     * @param string $rawTableName
     *
     * @return string
     */
    public function quoteIdentifierTable(string $rawTableName): string
    {
        if ($this->criteria->isIdentifierQuotingEnabled()) {
            return $this->adapter->quoteIdentifierTable($rawTableName);
        }

        $realTableName = $rawTableName;
        $spacePos = strrpos($rawTableName, ' ');
        if ($spacePos !== false) {
            $realTableName = substr($rawTableName, 0, $spacePos);
        }

        if ($this->dbMap->hasTable($realTableName)) {
            $tableMap = $this->dbMap->getTable($realTableName);
            if ($tableMap->isIdentifierQuotingEnabled()) {
                return $this->adapter->quoteIdentifierTable($rawTableName);
            }
        }

        return $rawTableName;
    }

    /**
     * @param array<\Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\UpdateColumn\AbstractUpdateColumn> $updateColumns
     *
     * @return array
     */
    public function buildParamsFromUpdateValues(array $updateColumns): array
    {
        $params = [];
        foreach ($updateColumns as $updateColumn) {
            $updateColumn->collectParam($params);
        }

        return $params;
    }

    /**
     * Build sql statement from a criteria and add it to the given statement collector.
     *
     * @param \Propel\Runtime\ActiveQuery\FilterExpression\ColumnFilterInterface $criterion
     * @param array<mixed>|null $params
     *
     * @return string
     */
    protected function buildStatementFromCriterion(ColumnFilterInterface $criterion, ?array &$params): string
    {
        return $criterion->buildStatement($params);
    }
}
