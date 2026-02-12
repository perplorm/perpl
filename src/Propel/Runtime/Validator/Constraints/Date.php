<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Propel\Runtime\Validator\Constraints;

use Symfony\Component\Validator\Constraints\Date as SymfonyDateConstraint;

/**
 * BC wrapper for Symfony's Date constraint supporting both Symfony < 8 and 8+
 */
class Date extends SymfonyDateConstraint
{
    /**
     * @var string
     */
    public $column = '';

    /**
     * @param array|string|null $options Options array (Symfony < 8) or message (Symfony 8+)
     * @param string|null $message Error message for Symfony 8+
     * @param array|null $groups Validation groups
     * @param mixed $payload Additional payload
     */
    public function __construct(
        array|string|null $options = null,
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null
    ) {
        // Handle array syntax - Date accepts array as first param in Symfony 8
        if (is_array($options)) {
            parent::__construct($options);

            return;
        }

        // Handle Symfony 8+ named parameters - pass directly
        parent::__construct(
            options: null,
            message: is_string($options) ? $options : $message,
            groups: $groups,
            payload: $payload,
        );
    }
}
