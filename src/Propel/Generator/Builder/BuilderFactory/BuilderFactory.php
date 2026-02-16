<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Propel\Generator\Builder\BuilderFactory;

use Propel\Generator\Builder\Om\AbstractObjectBuilder;
use Propel\Generator\Builder\Om\AbstractOMBuilder;
use Propel\Generator\Builder\Om\ObjectBuilder;
use Propel\Generator\Builder\Om\ObjectCollectionBuilder;
use Propel\Generator\Builder\Om\QueryBuilder;
use Propel\Generator\Builder\Om\TableMapBuilder;
use Propel\Generator\Config\GeneratorConfig;
use Propel\Generator\Config\GeneratorConfigInterface;
use Propel\Generator\Model\Table;

class BuilderFactory
{
    /**
     * @var \Propel\Generator\Config\GeneratorConfigInterface|null
     */
    protected ?GeneratorConfigInterface $generatorConfig = null;

    /**
     * @param \Propel\Generator\Config\GeneratorConfigInterface $v
     *
     * @return void
     */
    public function setGeneratorConfig(GeneratorConfigInterface $v): void
    {
        $this->generatorConfig = $v;
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     * @param string $type
     *
     * @return \Propel\Generator\Builder\Om\AbstractOMBuilder
     */
    public function createBuilderForTable(Table $table, string $type): AbstractOMBuilder
    {
        return $this->generatorConfig->getConfiguredBuilder($table, $type);
    }

    /**
     * Convenience method to return a NEW Object class builder instance.
     *
     * This is used very frequently from the tableMap and object builders to get
     * an object builder for a RELATED table.
     *
     * @param \Propel\Generator\Model\Table $table
     *
     * @return \Propel\Generator\Builder\Om\ObjectBuilder
     */
    public function createObjectBuilder(Table $table): ObjectBuilder
    {
        /** @var \Propel\Generator\Builder\Om\ObjectBuilder $builder */
        $builder = $this->createBuilderForTable($table, GeneratorConfig::KEY_OBJECT_BASE);

        return $builder;
    }

    /**
     * Convenience method to return a NEW Object stub class builder instance.
     *
     * This is used from the query builders to get
     * an object builder for a RELATED table.
     *
     * @param \Propel\Generator\Model\Table $table
     *
     * @return \Propel\Generator\Builder\Om\AbstractObjectBuilder
     */
    public function createStubObjectBuilder(Table $table): AbstractObjectBuilder
    {
        /** @var \Propel\Generator\Builder\Om\AbstractObjectBuilder $builder */
        $builder = $this->createBuilderForTable($table, GeneratorConfig::KEY_OBJECT_STUB);

        return $builder;
    }

    /**
     * Convenience method to return a NEW query class builder instance.
     *
     * This is used from the query builders to get
     * a query builder for a RELATED table.
     *
     * @param \Propel\Generator\Model\Table $table
     *
     * @return \Propel\Generator\Builder\Om\QueryBuilder
     */
    public function createQueryBuilder(Table $table): QueryBuilder
    {
        /** @var \Propel\Generator\Builder\Om\QueryBuilder $builder */
        $builder = $this->createBuilderForTable($table, GeneratorConfig::KEY_QUERY_BASE);

        return $builder;
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return \Propel\Generator\Builder\Om\ObjectCollectionBuilder
     */
    public function createObjectCollectionBuilder(Table $table): ObjectCollectionBuilder
    {
        /** @var \Propel\Generator\Builder\Om\ObjectCollectionBuilder $builder */
        $builder = $this->createBuilderForTable($table, GeneratorConfig::KEY_COLLECTION);

        return $builder;
    }

    /**
     * Convenience method to return a NEW query stub class builder instance.
     *
     * This is used from the query builders to get
     * a query builder for a RELATED table.
     *
     * @param \Propel\Generator\Model\Table $table
     *
     * @return \Propel\Generator\Builder\Om\AbstractOMBuilder
     */
    public function createStubQueryBuilder(Table $table): AbstractOMBuilder
    {
        $builder = $this->createBuilderForTable($table, GeneratorConfig::KEY_QUERY_STUB);

        return $builder;
    }

    /**
     * Returns new stub Query Inheritance builder class for this table.
     *
     * @param \Propel\Generator\Model\Table $table
     *
     * @return \Propel\Generator\Builder\Om\TableMapBuilder
     */
    public function createTableMapBuilder(Table $table): TableMapBuilder
    {
        /** @var \Propel\Generator\Builder\Om\TableMapBuilder $builder */
        $builder = $this->createBuilderForTable($table, GeneratorConfig::KEY_TABLEMAP);

        return $builder;
    }
}
