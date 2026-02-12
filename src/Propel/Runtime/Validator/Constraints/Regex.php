<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Runtime\Validator\Constraints;

use Symfony\Component\Validator\Constraints\Regex as SymfonyRegex;

/**
 * BC wrapper for Symfony's Regex constraint supporting both Symfony < 8 and 8+
 */
class Regex extends SymfonyRegex
{
    /**
     * @param array|string|null $options Options array (Symfony < 8) or pattern (Symfony 8+)
     * @param string|null $pattern Pattern for Symfony 8+
     * @param bool|null $match Match flag for Symfony 8+
     * @param string|null $message Error message for Symfony 8+
     * @param array|null $groups Validation groups
     * @param mixed $payload Additional payload
     */
    public function __construct(
        array|string|null $options = null,
        ?string $pattern = null,
        ?bool $match = null,
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null
    ) {
        // Handle Symfony < 8 array syntax
        if (is_array($options)) {
            parent::__construct($options);

            return;
        }

        // Handle Symfony 8+ named parameters - build options array
        $constructorOptions = [];
        if ($pattern !== null) {
            $constructorOptions['pattern'] = $pattern;
        } elseif (is_string($options)) {
            $constructorOptions['pattern'] = $options;
        }
        if ($match !== null) {
            $constructorOptions['match'] = $match;
        }
        if ($message !== null) {
            $constructorOptions['message'] = $message;
        }
        if ($groups !== null) {
            $constructorOptions['groups'] = $groups;
        }
        if ($payload !== null) {
            $constructorOptions['payload'] = $payload;
        }

        parent::__construct($constructorOptions);
    }
}
