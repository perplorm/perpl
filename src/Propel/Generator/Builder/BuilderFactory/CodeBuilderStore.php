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
use Propel\Generator\Builder\Om\MultiExtendObjectBuilder;
use Propel\Generator\Builder\Om\ObjectBuilder;
use Propel\Generator\Builder\Om\TableMapBuilder;
use Propel\Generator\Model\Inheritance;
use Propel\Generator\Model\Table;

/**
 * A BuilderFactory with a table. Keeps references to the builders once created.
 */
class CodeBuilderStore
{
    /**
     * The current table.
     *
     * @var \Propel\Generator\Model\Table
     */
    private Table $table;

    /**
     * @var \Propel\Generator\Builder\BuilderFactory\BuilderFactory
     */
    protected $builderFactory;

    /**
     * Object builder class for current table.
     *
     * @var \Propel\Generator\Builder\Om\ObjectBuilder|null
     */
    private ?ObjectBuilder $objectBuilder = null;

    /**
     * Stub Object builder class for current table.
     *
     * @var \Propel\Generator\Builder\Om\AbstractObjectBuilder|null
     */
    private ?AbstractObjectBuilder $stubObjectBuilder = null;

    /**
     * Query builder class for current table.
     *
     * @var \Propel\Generator\Builder\Om\AbstractOMBuilder|null
     */
    private ?AbstractOMBuilder $queryBuilder = null;

    /**
     * Stub Query builder class for current table.
     *
     * @var \Propel\Generator\Builder\Om\AbstractOMBuilder|null
     */
    private ?AbstractOMBuilder $stubQueryBuilder = null;

    /**
     * TableMap builder class for current table.
     *
     * @var \Propel\Generator\Builder\Om\TableMapBuilder|null
     */
    protected ?TableMapBuilder $tablemapBuilder = null;

    /**
     * Stub Interface builder class for current table.
     *
     * @var \Propel\Generator\Builder\Om\AbstractOMBuilder|null
     */
    private ?AbstractOMBuilder $interfaceBuilder = null;

    /**
     * Stub child object for current table.
     *
     * @var \Propel\Generator\Builder\Om\MultiExtendObjectBuilder|null
     */
    private ?MultiExtendObjectBuilder $multiExtendObjectBuilder = null;

    /**
     * @param \Propel\Generator\Model\Table $table
     * @param \Propel\Generator\Builder\BuilderFactory\BuilderFactory $builderFactory
     */
    public function __construct(Table $table, BuilderFactory $builderFactory)
    {
        $this->table = $table;
        $this->builderFactory = $builderFactory;
    }

    /**
     * Returns the current Table object.
     *
     * @return \Propel\Generator\Model\Table
     */
    public function getTable(): Table
    {
        return $this->table;
    }

    /**
     * Returns new or existing Object builder class for this table.
     *
     * @return \Propel\Generator\Builder\Om\ObjectBuilder
     */
    public function getObjectBuilder(): ObjectBuilder
    {
        if ($this->objectBuilder === null) {
            $this->objectBuilder = $this->builderFactory->createObjectBuilder($this->table);
        }

        return $this->objectBuilder;
    }

    /**
     * Returns new or existing stub Object builder class for this table.
     *
     * @return \Propel\Generator\Builder\Om\AbstractObjectBuilder
     */
    public function getStubObjectBuilder(): AbstractObjectBuilder
    {
        if ($this->stubObjectBuilder === null) {
            $this->stubObjectBuilder = $this->builderFactory->createStubObjectBuilder($this->table);
        }

        return $this->stubObjectBuilder;
    }

    /**
     * Returns new or existing Query builder class for this table.
     *
     * @return \Propel\Generator\Builder\Om\AbstractOMBuilder
     */
    public function getQueryBuilder(): AbstractOMBuilder
    {
        if ($this->queryBuilder === null) {
            $this->queryBuilder = $this->builderFactory->createQueryBuilder($this->table);
        }

        return $this->queryBuilder;
    }

    /**
     * Returns new or existing stub Query builder class for this table.
     *
     * @return \Propel\Generator\Builder\Om\AbstractOMBuilder
     */
    public function getStubQueryBuilder(): AbstractOMBuilder
    {
        if ($this->stubQueryBuilder === null) {
            $this->stubQueryBuilder = $this->builderFactory->createStubQueryBuilder($this->table);
        }

        return $this->stubQueryBuilder;
    }

    /**
     * Returns new or existing Object builder class for this table.
     *
     * @return \Propel\Generator\Builder\Om\TableMapBuilder
     */
    public function getTableMapBuilder(): TableMapBuilder
    {
        if ($this->tablemapBuilder === null) {
            $this->tablemapBuilder = $this->builderFactory->createTableMapBuilder($this->table);
        }

        return $this->tablemapBuilder;
    }

    /**
     * Returns new or existing stub Interface builder class for this table.
     *
     * @return \Propel\Generator\Builder\Om\AbstractOMBuilder
     */
    public function getInterfaceBuilder(): AbstractOMBuilder
    {
        if ($this->interfaceBuilder === null) {
            /** @var \Propel\Generator\Builder\Om\ObjectBuilder $builder */
            $builder = $this->builderFactory->createBuilderForTable($this->table, 'interface');
            $this->interfaceBuilder = $builder;
        }

        return $this->interfaceBuilder;
    }

    /**
     * Returns new or existing stub child object builder class for this table.
     *
     * @return \Propel\Generator\Builder\Om\MultiExtendObjectBuilder
     */
    public function getMultiExtendObjectBuilder(): MultiExtendObjectBuilder
    {
        if ($this->multiExtendObjectBuilder === null) {
            /** @var \Propel\Generator\Builder\Om\MultiExtendObjectBuilder $builder */
            $builder = $this->builderFactory->createBuilderForTable($this->table, 'objectmultiextend');
            $this->multiExtendObjectBuilder = $builder;
        }

        return $this->multiExtendObjectBuilder;
    }

    /**
     * Returns new Query Inheritance builder class for this table.
     *
     * @param \Propel\Generator\Model\Inheritance $child
     *
     * @return \Propel\Generator\Builder\Om\AbstractOMBuilder
     */
    public function createQueryInheritanceBuilder(Inheritance $child): AbstractOMBuilder
    {
        /** @var \Propel\Generator\Builder\Om\QueryInheritanceBuilder $queryInheritanceBuilder */
        $queryInheritanceBuilder = $this->builderFactory->createBuilderForTable($this->table, 'queryinheritance');
        $queryInheritanceBuilder->setChild($child);

        return $queryInheritanceBuilder;
    }

    /**
     * Returns new stub Query Inheritance builder class for this table.
     *
     * @param \Propel\Generator\Model\Inheritance $child
     *
     * @return \Propel\Generator\Builder\Om\AbstractOMBuilder
     */
    public function createStubQueryInheritanceBuilder(Inheritance $child): AbstractOMBuilder
    {
        /** @var \Propel\Generator\Builder\Om\QueryInheritanceBuilder $stubQueryInheritanceBuilder */
        $stubQueryInheritanceBuilder = $this->builderFactory->createBuilderForTable($this->table, 'queryinheritancestub');
        $stubQueryInheritanceBuilder->setChild($child);

        return $stubQueryInheritanceBuilder;
    }
}
