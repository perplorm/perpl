<?php

declare(strict_types = 1);

namespace Propel\Common\Pluralizer;

/**
 * The Propel 1.6 default English pluralizer class
 * for compatibility only.
 */
class SimpleEnglishPluralizer implements PluralizerInterface
{
    /**
     * Generate a plural name based on the passed in root.
     *
     * @param string $root The root that needs to be pluralized (e.g. Author)
     *
     * @return string The plural form of $root (e.g. Authors).
     */
    #[\Override]
    public function getPluralForm(string $root): string
    {
        return $root . 's';
    }
}
