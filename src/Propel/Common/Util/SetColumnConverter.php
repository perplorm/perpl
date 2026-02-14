<?php

declare(strict_types = 1);

namespace Propel\Common\Util;

use BackedEnum;
use Propel\Common\Exception\SetColumnConverterException;
use Propel\Runtime\Exception\PropelException;
use UnitEnum;
use function array_diff;
use function array_filter;
use function array_intersect;
use function array_keys;
use function array_reduce;
use function array_values;
use function count;
use function sprintf;
use const ARRAY_FILTER_USE_KEY;

/**
 * Class converts SET column values between integer and string/array representation.
 */
class SetColumnConverter
{
    /**
     * Converts set column values to the corresponding integer.
     *
     * @param array<string>|string|null $items
     * @param array<int, string> $valueSet
     *
     * @throws \Propel\Common\Exception\SetColumnConverterException
     *
     * @return int
     */
    public static function convertToBitmask($items, array $valueSet): int
    {
        if ($items === null) {
            return 0;
        }
        $setValues = array_intersect($valueSet, (array)$items);

        $missingValues = array_diff((array)$items, $setValues);
        if ($missingValues) {
            throw new SetColumnConverterException(sprintf('Value "%s" is not among the valueSet', $missingValues[0]), $missingValues[0]);
        }
        $keys = array_keys($setValues);

        return array_reduce($keys, fn (int $bitVector, int $ix): int => $bitVector | (1 << $ix), 0);
    }

    /**
     * @deprecated Use aptly named {@see static::convertToBitmask()}.
     *
     * @param array<string>|string|null $val
     * @param array<int, string> $valueSet
     *
     * @return int
     */
    public static function convertToInt($val, array $valueSet): int
    {
        return static::convertToBitmask($val, $valueSet);
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
    public static function convertBitmaskToArray(?int $val, array $valueSet): array
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

    /**
     * @deprecated Use aptly named {@see static::convertBitmaskToArray()}
     *
     * @param int|null $val
     * @param array<int, string> $valueSet
     *
     * @return list<string>
     */
    public static function convertIntToArray(?int $val, array $valueSet): array
    {
        return static::convertBitmaskToArray($val, $valueSet);
    }

    /**
     * @psalm-return ($items is null ? null : array)
     *
     * @param array|string $items
     * @param array $setValues
     *
     * @return array|null
     */
    public static function getItemsInOrder(array|string|null $items, array $setValues): array|null
    {
        if ($items === null) {
            return null;
        }
        $items = (array)$items;
        static::requireValuesInSet($items, $setValues);

        return array_values(array_intersect($setValues, $items));
    }

    /**
     * @param array|string $items
     * @param array $setValues
     * @param string $locationDescription
     *
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return void
     */
    public static function requireValuesInSet(array|string $items, array $setValues, string $locationDescription = ''): void
    {
        $unknownValues = array_diff((array)$items, $setValues);
        if (!$unknownValues) {
            return;
        }

        $unknownValuesCsv = implode(',', $unknownValues);
        $allowedValuesCsv = implode(',', $setValues);

        throw new PropelException("Illegal value in SET $locationDescription: Set '$allowedValuesCsv' does not contain '$unknownValuesCsv'");
    }

    /**
     * @param string $itemsCsv
     *
     * @return array<string>
     */
    public static function itemsCsvToArray(string $itemsCsv): array
    {
        if (!trim($itemsCsv)) {
            return [];
        }
        $items = explode(',', $itemsCsv);

        return array_filter(array_map('trim', $items));
    }

    /**
     * Tries to resolve set items for unknown input type.
     *
     * @param array<string>|string|int $value
     * @param array<string> $valueSet
     *
     * @return array<string>
     */
    public static function rawInputToSetItems(array|string|int $value, array $valueSet): array
    {
        $items = match (gettype($value)) {
            'string' => self::itemsCsvToArray($value),
            'integer' => self::convertBitmaskToArray($value, $valueSet),
            default => (array)$value,
        };

        return self::getItemsInOrder($items, $valueSet);
    }

    /**
     * @param class-string<\UnitEnum> $enumClass
     *
     * @return array<string>
     */
    public static function getItemsFromEnum(string $enumClass): array
    {
        return array_map(fn (UnitEnum $case) => (string)($case instanceof BackedEnum ? $case->value : $case->name), $enumClass::cases());
    }
}
