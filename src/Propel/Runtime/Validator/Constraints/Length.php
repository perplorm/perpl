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
        // Handle array syntax - extract parameters for Symfony 8 compatibility
        if (is_array($options)) {
            parent::__construct(
                exactly: $options['exactly'] ?? null,
                min: $options['min'] ?? null,
                max: $options['max'] ?? null,
                charset: $options['charset'] ?? null,
                normalizer: $options['normalizer'] ?? null,
                minMessage: $options['minMessage'] ?? null,
                maxMessage: $options['maxMessage'] ?? null,
                exactMessage: $options['exactMessage'] ?? null,
                charsetMessage: $options['charsetMessage'] ?? null,
                groups: $options['groups'] ?? null,
                payload: $options['payload'] ?? null
            );

            return;
        }

        // Handle Symfony 8+ named parameters - pass directly
        parent::__construct(
            exactly: is_int($options) ? $options : $exactly,
            min: $min,
            max: $max,
            charset: $charset,
            message: $message,
            groups: $groups,
            payload: $payload
        );
    }
}
