<?php

declare(strict_types = 1);

namespace Propel\Generator\Builder\Om\ObjectBuilder;

use LogicException;
use Propel\Generator\Builder\DataModelBuilder;
use Propel\Generator\Builder\Om\ObjectBuilder;
use Propel\Generator\Model\Table;

class ObjectCodeProducer extends DataModelBuilder
{
    /**
     * @var \Propel\Generator\Builder\Om\ObjectBuilder
     */
    protected ObjectBuilder $objectBuilder;

    /**
     * @param \Propel\Generator\Model\Table $table
     * @param \Propel\Generator\Builder\Om\ObjectBuilder $parentBuilder
     *
     * @throws \LogicException
     */
    public function __construct(Table $table, ObjectBuilder $parentBuilder)
    {
        parent::__construct($table, $parentBuilder->referencedClasses);
        $this->objectBuilder = $parentBuilder;
        if (!$parentBuilder->getGeneratorConfig()) {
            throw new LogicException('CodeProducer should not be created before GeneratorConfig is available.');
        }
        $this->init($this->getTable(), $parentBuilder->getGeneratorConfig());
        $this->platform = $parentBuilder->platform;
    }
}
