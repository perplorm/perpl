<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Propel\Runtime\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

class Unique extends Constraint
{
    /**
     * @var string
     */
    public $message = 'This value is already stored in your database';

    /**
     * @var string
     */
    public $column = '';

    /**
     * Constructor supporting both Symfony < 8 (array options) and Symfony 8+ (named parameters)
     *
     * @param array|string|null $options Legacy array of options or message string (Symfony < 8)
     * @param string|null $message The validation message (Symfony 8+)
     * @param string|null $column The column name (Symfony 8+)
     * @param array|null $groups Validation groups
     * @param mixed $payload Additional payload
     */
    public function __construct(
        array|string|null $options = null,
        ?string $message = null,
        ?string $column = null,
        ?array $groups = null,
        mixed $payload = null
    ) {
        // Handle array syntax - Unique extends base Constraint which accepts array
        if (is_array($options)) {
            parent::__construct($options);

            return;
        }

        // Handle Symfony 8+ named parameters - build options array
        $constructorOptions = [];
        if ($message !== null) {
            $constructorOptions['message'] = $message;
        }
        if ($column !== null) {
            $constructorOptions['column'] = $column;
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
