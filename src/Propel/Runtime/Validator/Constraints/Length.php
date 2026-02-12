<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

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
            $exactlyValue = $options['exactly'] ?? null;
            $minValue = $options['min'] ?? null;
            $maxValue = $options['max'] ?? null;
            $genericMessage = $options['message'] ?? null;

            // Map generic message to specific messages, preferring explicit specific messages
            $messages = $this->mapGenericMessage(
                $genericMessage,
                $exactlyValue,
                $minValue,
                $maxValue,
                $options['minMessage'] ?? null,
                $options['maxMessage'] ?? null,
                $options['exactMessage'] ?? null,
            );

            parent::__construct(
                exactly: $exactlyValue,
                min: $minValue,
                max: $maxValue,
                charset: $options['charset'] ?? null,
                normalizer: $options['normalizer'] ?? null,
                minMessage: $messages['minMessage'],
                maxMessage: $messages['maxMessage'],
                exactMessage: $messages['exactMessage'],
                charsetMessage: $options['charsetMessage'] ?? null,
                groups: $options['groups'] ?? null,
                payload: $options['payload'] ?? null,
            );

            return;
        }

        // Handle Symfony 8+ named parameters
        $exactlyValue = is_int($options) ? $options : $exactly;
        $messages = $this->mapGenericMessage($message, $exactlyValue, $min, $max);

        parent::__construct(
            exactly: $exactlyValue,
            min: $min,
            max: $max,
            charset: $charset,
            minMessage: $messages['minMessage'],
            maxMessage: $messages['maxMessage'],
            exactMessage: $messages['exactMessage'],
            groups: $groups,
            payload: $payload,
        );
    }

    /**
     * Maps a generic message to specific message parameters based on which constraints are set
     *
     * @param string|null $genericMessage Generic message to map
     * @param int|null $exactly Exact length constraint value
     * @param int|null $min Minimum length constraint value
     * @param int|null $max Maximum length constraint value
     * @param string|null $explicitMinMessage Explicit minMessage override
     * @param string|null $explicitMaxMessage Explicit maxMessage override
     * @param string|null $explicitExactMessage Explicit exactMessage override
     *
     * @return array<string, string|null> Array with minMessage, maxMessage, and exactMessage keys
     */
    private function mapGenericMessage(
        ?string $genericMessage,
        ?int $exactly,
        ?int $min,
        ?int $max,
        ?string $explicitMinMessage = null,
        ?string $explicitMaxMessage = null,
        ?string $explicitExactMessage = null
    ): array {
        // If no generic message or all explicit messages provided, use explicit values
        if ($genericMessage === null) {
            return [
                'minMessage' => $explicitMinMessage,
                'maxMessage' => $explicitMaxMessage,
                'exactMessage' => $explicitExactMessage,
            ];
        }

        // Map generic message based on which constraints are set, preferring explicit messages
        if ($exactly !== null) {
            return [
                'minMessage' => $explicitMinMessage,
                'maxMessage' => $explicitMaxMessage,
                'exactMessage' => $explicitExactMessage ?? $genericMessage,
            ];
        }

        if ($min !== null && $max === null) {
            return [
                'minMessage' => $explicitMinMessage ?? $genericMessage,
                'maxMessage' => $explicitMaxMessage,
                'exactMessage' => $explicitExactMessage,
            ];
        }

        if ($max !== null && $min === null) {
            return [
                'minMessage' => $explicitMinMessage,
                'maxMessage' => $explicitMaxMessage ?? $genericMessage,
                'exactMessage' => $explicitExactMessage,
            ];
        }

        if ($min !== null && $max !== null) {
            return [
                'minMessage' => $explicitMinMessage ?? $genericMessage,
                'maxMessage' => $explicitMaxMessage ?? $genericMessage,
                'exactMessage' => $explicitExactMessage,
            ];
        }

        // No constraints set, return explicit messages
        return [
            'minMessage' => $explicitMinMessage,
            'maxMessage' => $explicitMaxMessage,
            'exactMessage' => $explicitExactMessage,
        ];
    }
}
