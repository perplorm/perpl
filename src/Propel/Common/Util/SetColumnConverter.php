<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Propel\Common\Util;

use Propel\Common\Exception\SetColumnConverterException;

/**
 * Class converts SET column values between integer and string/array representation.
 *
 * @author Moritz Schroeder <moritz.schroeder@molabs.de>
 */
class SetColumnConverter
{
    /**
     * Converts set column values to the corresponding integer.
     *
     * @param array<string>|string|null $val
     * @param array<int, string> $valueSet
     *
     * @throws \Propel\Common\Exception\SetColumnConverterException
     *
     * @return int
     */
    public static function convertToInt($val, array $valueSet): int
    {
        if ($val === null) {
            return 0;
        }
        $setValues = array_intersect($valueSet, (array)$val);

        $missingValues = array_diff((array)$val, $setValues);
        if ($missingValues) {
            throw new SetColumnConverterException(sprintf('Value "%s" is not among the valueSet', $missingValues[0]), $missingValues[0]);
        }
        $keys = array_keys($setValues);

        return array_reduce($keys, fn (int $bitVector, int $ix): int => $bitVector | (1 << $ix), 0);
    }

    /**
     * Converts set column integer value to corresponding array.
     *
     * @param int|null $val
     * @param array<int, string> $valueSet
     *
     * @throws \Propel\Common\Exception\SetColumnConverterException
     *
     * @return list<string>
     */
    public static function convertIntToArray(?int $val, array $valueSet): array
    {
        if ($val === null) {
            return [];
        }
        $availableBits = (1 << count($valueSet)) - 1; // 00100 -1 = 00011
        $bitsOutOfRange = $val & ~$availableBits;
        if ($bitsOutOfRange) {
            throw new SetColumnConverterException("Unknown value key `$bitsOutOfRange` for value `$val`", $bitsOutOfRange);
        }

        return array_values(array_filter($valueSet, fn ($ix) => (bool)($val & (1 << $ix)), ARRAY_FILTER_USE_KEY));
    }
}
