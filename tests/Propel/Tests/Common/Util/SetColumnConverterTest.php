<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Common\Util;

use PHPUnit\Framework\TestCase;
use Propel\Common\Exception\SetColumnConverterException;
use Propel\Common\Util\SetColumnConverter;
use Propel\Runtime\Exception\PropelException;
use Propel\Tests\Helpers\ColorsBackedEnum;
use Propel\Tests\Helpers\ColorsBasicEnum;

/**
 * Tests for SetColumnConverter class.
 *
 * @author Moritz Schroeder <moritz.schroeder@molabs.de>
 */
class SetColumnConverterTest extends TestCase
{
    protected static $valueSet = ['a', 'b', 'c', 'd', 'e', 'f'];
    /**
     * @dataProvider convertValuesProvider
     *
     * @param array|null $values
     * @param string $validInteger
     *
     * @return void
     */
    public function testconvertToBitmaskValidValues(array|null $values, $validInteger)
    {
        $intValue = SetColumnConverter::convertToBitmask($values, static::$valueSet);
        $this->assertSame($validInteger, $intValue);
    }

    /**
     * @return void
     */
    public function testconvertToBitmaskNullValue()
    {
        $intValue = SetColumnConverter::convertToBitmask(null, static::$valueSet);
        $this->assertSame(0, $intValue);
    }

    /**
     * @return void
     */
    public function testconvertToBitmaskValueNotInSet()
    {
        $this->expectException(SetColumnConverterException::class);

        SetColumnConverter::convertToBitmask(['g'], static::$valueSet);
    }

    /**
     * @dataProvider convertValuesProvider
     *
     * @param array $validArray
     * @param string $intValue
     *
     * @return void
     */
    public function testconvertBitmaskToArrayValidValues(array $validArray, $intValue)
    {
        $arrayValue = SetColumnConverter::convertBitmaskToArray($intValue, static::$valueSet);
        $this->assertEquals($validArray, $arrayValue);
    }

    /**
     * @return void
     */
    public function testconvertBitmaskToArrayNullValue()
    {
        $arrayValue = SetColumnConverter::convertBitmaskToArray(null, static::$valueSet);
        $this->assertSame([], $arrayValue);
    }

    /**
     * @return void
     */
    public function testconvertBitmaskToArrayIntOutOfRange()
    {
        $this->expectException(SetColumnConverterException::class);

        SetColumnConverter::convertBitmaskToArray('65', static::$valueSet);
    }

    /**
     * @return array<array>
     */
    public function convertValuesProvider()
    {
        return [
            [['a'], 1],
            [['c'], 4],
            [['a', 'f'], 33],
            [['a', 'e', 'f'], 49],
            [['e', 'f'], 48],
        ];
    }

    /**
     * @return array<array>
     */
    public function GetItemsInOrderDataProvider(): array
    {
        return [
            [null, null],
            ['d', ['d']],
            [[], []],
            [['f', 'e', 'a', 'b'], ['a', 'b', 'e', 'f']],
        ];
    }

    /**
     * @dataProvider GetItemsInOrderDataProvider
     *
     * @param array|string|null $items
     * @param array|null $expected
     * 
     * @return void
     */
    public function testGetItemsInOrder(array|string|null $items, array|null $expected): void
    {
        $orderedItems = SetColumnConverter::getItemsInOrder($items, static::$valueSet);
        $this->assertEquals($expected, $orderedItems);
    }



    /**
     * @return array<array>
     */
    public function RequireValuesInSetDataProvider(): array
    {
        return [
            ['z', '', "Illegal value in SET : Set 'a,b,c,d,e,f' does not contain 'z'"],
            [['a', 'z', 'y'], '', "Illegal value in SET : Set 'a,b,c,d,e,f' does not contain 'z,y'"],
        ];
    }

    /**
     * @dataProvider RequireValuesInSetDataProvider
     *
     * @param array|string $items
     * @param string $locationDescription
     * @param string $expectedExeptionMessage
     * @return void
     */
    public function testRequireValuesInSetException(array|string $items, string $locationDescription, string $expectedExeptionMessage): void
    {
        $this->expectException(PropelException::class);
        $this->expectExceptionMessage($expectedExeptionMessage);

        SetColumnConverter::requireValuesInSet($items, static::$valueSet);
    }

    /**
     * @return void
     */
    public function testRequireValuesInSetPass(): void
    {
        SetColumnConverter::requireValuesInSet('a', static::$valueSet);
        SetColumnConverter::requireValuesInSet(static::$valueSet, static::$valueSet);
        SetColumnConverter::requireValuesInSet(array_reverse(static::$valueSet), static::$valueSet);
        SetColumnConverter::requireValuesInSet([], static::$valueSet);

        $this->assertTrue(true);
    }

    /**
     * @return array<array>
     */
    public function ItemsCsvToArrayDataProvider(): array
    {
        return [
            ['', []],
            ['       ', []],
            ['  a    ', ['a']],
            ['   a,null , (,ß,    a    ,   ', ['a', 'null', '(', 'ß', 'a']],
        ];
    }

    /**
     * @dataProvider ItemsCsvToArrayDataProvider
     * 
     * @param string $itemsCsv
     * @param array $expected
     *
     * @return void
     */
    public function testItemsCsvToArray(string $itemsCsv, array $expected): void
    {
        $items = SetColumnConverter::itemsCsvToArray($itemsCsv);
        $this->assertEquals($expected, $items);
    }

    /**
     * @return array<array>
     */
    public function RawInputToSetItemsDataProvider(): array
    {
        return [
            [0, []],
            [4, ['c']],
            [5, ['a', 'c']],
            ['', []],
            ['c', ['c']],
            ['f,c', ['c', 'f']],
            [[], []],
            [['c'], ['c']],
            [['f', 'c'], ['c', 'f']],
        ];
    }

    /**
     * @dataProvider RawInputToSetItemsDataProvider
     * 
     * @param array|string|int $value
     * @param array $expected
     *
     * @return void
     */
    public function testRawInputToSetItems(array|string|int $value, array $expected): void
    {
        $items = SetColumnConverter::rawInputToSetItems($value, static::$valueSet);
        $this->assertEquals($expected, $items);
    }

    /**
     * @return array<class-string<\UnitEnum, string[]>>[]
     */
    public function GetEnumItemsDataProvider(): array
    {
        return [
            [ColorsBasicEnum::class, ['Red', 'Blue', 'Yellow']],
            [ColorsBackedEnum::class, ['red', 'blue', 'yellow']],
        ];
    }

    /**
     * @dataProvider GetEnumItemsDataProvider
     */
    public function testGetItemsFromEnum(string $enumClass, array $expected): void
    {
        $actual = SetColumnConverter::getItemsFromEnum($enumClass);
        $this->assertEquals($expected, $actual);
    }
}
