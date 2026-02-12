<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Runtime\Validator\Constraints;

use Symfony\Component\Validator\Constraints\Type as SymfonyType;

/**
 * BC wrapper for Symfony's Type constraint supporting both Symfony < 8 and 8+
 */
class Type extends SymfonyType
{
    /**
     * @param array|string|null $options Options array (Symfony < 8) or type (Symfony 8+)
     * @param string|null $type Type for Symfony 8+
     * @param string|null $message Error message for Symfony 8+
     * @param array|null $groups Validation groups
     * @param mixed $payload Additional payload
     */
    public function __construct(
        array|string|null $options = null,
        ?string $type = null,
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null
    ) {
        // Handle array syntax (works for both Symfony < 8 and 8+)
        if (is_array($options)) {
            parent::__construct(options: $options);

            return;
        }

        // Handle Symfony 8+ named parameters - build options array
        $constructorOptions = [];
        if ($type !== null) {
            $constructorOptions['type'] = $type;
        } elseif (is_string($options)) {
            $constructorOptions['type'] = $options;
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
