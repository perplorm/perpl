<?php

declare(strict_types = 1);

namespace Propel\Generator\Builder\Om\ObjectBuilder\RelationCodeProducer;

use Propel\Generator\Builder\Om\AbstractSubsectionCodeProducer;

/**
 * Generates a database loader file, which is used to register all table maps with the DatabaseMap.
 *
 * @extends \Propel\Generator\Builder\Om\AbstractSubsectionCodeProducer<\Propel\Generator\Builder\Om\ObjectBuilder>
 */
abstract class AbstractRelationCodeProducer extends AbstractSubsectionCodeProducer
{
    /**
     * @param string $script
     *
     * @return void
     */
    abstract public function addMethods(string &$script): void;

    /**
     * Adds the class attributes that are needed to store fkey related objects.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    abstract public function addAttributes(string &$script): void;

    /**
     * @param string $script
     *
     * @return void
     */
    abstract public function addOnReloadCode(string &$script): void;

    /**
     * @param string $script
     *
     * @return void
     */
    abstract public function addDeleteScheduledItemsCode(string &$script): void;

    /**
     * @param string $script
     *
     * @return string Attribute name used by ObjectBuilder.
     */
    abstract public function addClearReferencesCode(string &$script): string;
}
