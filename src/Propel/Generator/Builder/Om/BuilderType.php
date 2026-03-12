<?php

declare(strict_types = 1);

namespace Propel\Generator\Builder\Om;

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
}
