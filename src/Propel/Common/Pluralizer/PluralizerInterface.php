<?php

declare(strict_types = 1);

namespace Propel\Common\Pluralizer;

/**
 * The generic interface to create a plural form of a name.
 */
interface PluralizerInterface
{
    /**
     * Generate a plural name based on the passed in root.
     *
     * @param string $root The root that needs to be pluralized (e.g. Author)
     *
     * @return string The plural form of $root.
     */
    public function getPluralForm(string $root): string;
}
