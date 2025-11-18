<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Generator\Builder\Om;

use Propel\Generator\Model\Inheritance;

/**
 * Shared methods of builders for child classes declared via inheritance in schema.
 */
interface ExtensionBuilderInterface
{
    /**
     * Sets the child object that we're operating on currently.
     *
     * @param \Propel\Generator\Model\Inheritance $child Inheritance
     *
     * @return void
     */
    public function setChild(Inheritance $child): void;

    /**
     * Returns the child object we're operating on currently.
     *
     * @throws \Propel\Generator\Exception\BuildException
     *
     * @return \Propel\Generator\Model\Inheritance
     */
    public function getChild(): Inheritance;

    /**
     * Builds the PHP source for current class and returns it as a string.
     *
     * This is the main entry point and defines a basic structure that classes should follow.
     * In most cases this method will not need to be overridden by subclasses. This method
     * does assume that the output language is PHP code, so it will need to be overridden if
     * this is not the case.
     *
     * @return string The resulting PHP sourcecode.
     */
    public function build(): string;
}
