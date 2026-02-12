<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Runtime\Validator\Constraints;

use Symfony\Component\Validator\Constraints\Length as SymfonyLength;

/**
 * BC wrapper for Symfony's Length constraint supporting both Symfony < 8 and 8+
 */
class Length extends SymfonyLength
{
    /**
     * @param array|int|null $options Options array (Symfony < 8) or exact length (Symfony 8+)
     * @param int|null $min Minimum length for Symfony 8+
     * @param int|null $max Maximum length for Symfony 8+
     * @param int|null $exactly Exact length for Symfony 8+
     * @param string|null $charset Character set for Symfony 8+
     * @param string|null $message Error message for Symfony 8+
     * @param array|null $groups Validation groups
     * @param mixed $payload Additional payload
     */
    public function __construct(
        array|int|null $options = null,
        ?int $min = null,
        ?int $max = null,
        ?int $exactly = null,
        ?string $charset = null,
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
        if ($exactly !== null) {
            $constructorOptions['exactly'] = $exactly;
        } elseif (is_int($options)) {
            $constructorOptions['exactly'] = $options;
        }
        if ($min !== null) {
            $constructorOptions['min'] = $min;
        }
        if ($max !== null) {
            $constructorOptions['max'] = $max;
        }
        if ($charset !== null) {
            $constructorOptions['charset'] = $charset;
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
