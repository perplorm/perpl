<?php

declare(strict_types = 1);

namespace Propel\Generator\Builder\Om;

use LogicException;
use Propel\Generator\Builder\DataModelBuilder;
use Propel\Generator\Model\Table;

/**
 * Unified setup for subsection code builders, which extract interconnected
 * parts out of top level builders.
 *
 * NOTE: AbstractSubsectionCodeProducer have to be created during (or after)
 * the builders {@see AbstractOMBuilder::init()} phase, when config is
 * available.
 *
 * @template Builder of \Propel\Generator\Builder\Om\AbstractOMBuilder
 */
class AbstractSubsectionCodeProducer extends DataModelBuilder
{
    /**
     * @var Builder
     */
    protected AbstractOMBuilder $builder;

    /**
     * @param \Propel\Generator\Model\Table $table
     * @param Builder $parentBuilder
     *
     * @throws \LogicException
     */
    public function __construct(Table $table, AbstractOMBuilder $parentBuilder)
    {
        parent::__construct($table, $parentBuilder->referencedClasses);
        $this->builder = $parentBuilder;
        if (!$parentBuilder->getGeneratorConfig()) {
            throw new LogicException('CodeProducer should not be created before GeneratorConfig is available.');
        }
        $this->init($this->getTable(), $parentBuilder->getGeneratorConfig());
        $this->platform = $parentBuilder->platform;
    }
}
