<?php

declare(strict_types = 1);

namespace Propel\Generator\Builder\Om;

use function in_array;

/**
 * Ids for Om Builder classes.
 *
 * These are used in two ways:
 *  - In PropelConfiguration, these are keys in section 'generator.objectModel.builders' and return the classes
 *  - Whenever builders are used internally, they are used to request the corresponding builder instance
 *      {@see \Propel\Generator\Config\AbstractGeneratorConfig::getConfiguredBuilder()} these are used to
 */
enum BuilderType: string
{
   /**
    * Base model
    */
    case ObjectBase = 'object';

    /**
     * User stub model
     */
    case ObjectStub = 'objectstub';

    /**
     * Base query
     */
    case QueryBase = 'query';

    /**
     * User stub query
     */
    case QueryStub = 'querystub';

    /**
     * Table map
     */
    case TableMap = 'tablemap';

    /**
     * Object collection
     */
    case Collection = 'collection';

    /**
     * Interface stub
     */
    case Interface = 'interface';

    /**
     * Base query with single table inheritance
     */
    case QueryInheritance = 'queryinheritance';

    /**
     * Query stub with single table inheritance
     */
    case QueryInheritanceStub = 'queryinheritancestub';

    /**
     * User stub model with single table inheritance code
     */
    case ObjectInheritanceStub = 'objectmultiextend';

    /**
     * Custom builders registered on table
     *
     * @see \Propel\Generator\Model\Table::getAdditionalBuilders()
     * @see \Propel\Generator\Util\QuickBuilder::buildCodeBuilders()
     */
    case Custom = 'custom';

    /**
     * Check if builder creates a class specified through single table inheritance.
     *
     * @return bool
     */
    public function isInheritanceBuilder(): bool
    {
        return in_array($this, [BuilderType::ObjectInheritanceStub, BuilderType::QueryInheritance, BuilderType::QueryInheritanceStub]);
    }
}
