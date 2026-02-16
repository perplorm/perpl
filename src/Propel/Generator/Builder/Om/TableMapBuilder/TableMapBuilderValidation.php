<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Propel\Generator\Builder\Om\TableMapBuilder;

use Propel\Generator\Builder\Om\TableMapBuilder;
use Propel\Generator\Exception\DeprecatedUsageException;
use Propel\Runtime\Collection\Collection;
use ReflectionClass;

class TableMapBuilderValidation
{
    /**
     * @param \Propel\Generator\Builder\Om\TableMapBuilder $builder
     *
     * @throws \Propel\Generator\Exception\DeprecatedUsageException
     *
     * @return void
     */
    public static function validate(TableMapBuilder $builder): void
    {
        $userModelBuilder = $builder->getStubObjectBuilder();
        $modelClassName = $userModelBuilder->getFullyQualifiedClassName();
        $collectionClassName = "{$modelClassName}Collection";

        $isImplicitCollection = class_exists($collectionClassName)
            && is_subclass_of($collectionClassName, Collection::class)
            && $builder->getTable()->getCollectionClassNameFq() !== $collectionClassName;

        if (!$isImplicitCollection) {
            return;
        }

        $filePath = (new ReflectionClass($collectionClassName))->getFileName();

        $message = "Implicit coupling of model and collection is deprecated.\nFound collection associated by class name:\n"
        . " - model class:      $modelClassName \n"
        . " - collection class: $collectionClassName  in $filePath\n"
        . 'Register the collection in schema.xml '
        . "by setting the `collection-class` attribute on the table, i.e.\n"
        . "   `<table name=\"{$builder->getTable()->getName()}\" collection-class=\"$collectionClassName\">`";

        throw new DeprecatedUsageException($message);
    }
}
