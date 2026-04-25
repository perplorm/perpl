<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Platform;

use Propel\Generator\Model\Column;
use Propel\Generator\Model\PropelTypes;
use Propel\Generator\Platform\DefaultPlatform;
use Propel\Tests\TestCase;

class DefaultPlatformTest extends TestCase
{
    protected static DefaultPlatform|null $platform = null;

    /**
     * Get the Platform object for this class
     *
     * @return \Propel\Generator\Platform\DefaultPlatform
     */
    protected static function getPlatform(): DefaultPlatform
    {
        static::$platform ??= new DefaultPlatform();
        
        return static::$platform;
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        static::$platform = null;
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideValidBooleanValues')]
    public function testGetBooleanString($value)
    {
        $p = static::getPlatform();

        $this->assertEquals('1', $p->getBooleanString($value));
    }

    public static function provideValidBooleanValues()
    {
        return [
            [true],
            ['TRUE'],
            ['true'],
            ['1'],
            [1],
            ['y'],
            ['Y'],
            ['yes'],
            ['YES'],
        ];
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideInvalidBooleanValues')]
    public function testGetNonBooleanString($value)
    {
        $p = static::getPlatform();

        $this->assertEquals('0', $p->getBooleanString($value));
    }

    public static function provideInvalidBooleanValues()
    {
        return [
            [false],
            ['FALSE'],
            ['false'],
            ['0'],
            [0],
            ['n'],
            ['N'],
            ['no'],
            ['NO'],
            ['foo'],
        ];
    }

    /**
     * @return void
     */
    public function testQuote()
    {
        $p = static::getPlatform();

        $unquoted = 'Nice';
        $quoted = $p->quote($unquoted);

        $this->assertEquals("'$unquoted'", $quoted);

        $unquoted = "Naughty ' string";
        $quoted = $p->quote($unquoted);
        $expected = "'Naughty '' string'";
        $this->assertEquals($expected, $quoted);
    }

    protected static function createColumn($type, $defaultValue, $size = null)
    {
        $column = new Column('');
        $column->setType($type);
        $defaultValue !== null && $column->setDefaultValue($defaultValue);
        $column->getDomain()->setSize($size);

        return $column;
    }

    public static function createEnumeratedColumn(string $propelType, $defaultValues, $defaultValue)
    {
        $column = static::createColumn($propelType, $defaultValue);
        $column->setValueSet($defaultValues);

        return $column;
    }

    public static function getColumnDefaultValueDDLDataProvider(): array
    {
        return [
            [static::createColumn(PropelTypes::INTEGER, 0), 'DEFAULT 0'],
            [static::createColumn(PropelTypes::INTEGER, '0'), 'DEFAULT 0'],
            [static::createColumn(PropelTypes::VARCHAR, 'foo'), "DEFAULT 'foo'"],
            [static::createColumn(PropelTypes::VARCHAR, 0), "DEFAULT '0'"],
            [static::createColumn(PropelTypes::BOOLEAN, true), 'DEFAULT 1'],
            [static::createColumn(PropelTypes::BOOLEAN, false), 'DEFAULT 0'],
            [static::createColumn(PropelTypes::BOOLEAN, 'true'), 'DEFAULT 1'],
            [static::createColumn(PropelTypes::BOOLEAN, 'false'), 'DEFAULT 0'],
            [static::createColumn(PropelTypes::BOOLEAN, 'TRUE'), 'DEFAULT 1'],
            [static::createColumn(PropelTypes::BOOLEAN, 'FALSE'), 'DEFAULT 0'],
            [static::createEnumeratedColumn(PropelTypes::ENUM_BINARY, ['foo', 'bar', 'baz'], 'foo'), 'DEFAULT 0'],
            [static::createEnumeratedColumn(PropelTypes::ENUM_BINARY, ['foo', 'bar', 'baz'], 'bar'), 'DEFAULT 1'],
            [static::createEnumeratedColumn(PropelTypes::ENUM_BINARY, ['foo', 'bar', 'baz'], 'baz'), 'DEFAULT 2'],
            [static::createEnumeratedColumn(PropelTypes::SET_BINARY, ['foo', 'bar', 'baz'], 'foo'), 'DEFAULT 1'],
            [static::createEnumeratedColumn(PropelTypes::SET_BINARY, ['foo', 'bar', 'baz'], 'bar'), 'DEFAULT 2'],
            [static::createEnumeratedColumn(PropelTypes::SET_BINARY, ['foo', 'bar', 'baz'], 'baz'), 'DEFAULT 4'],
            [static::createEnumeratedColumn(PropelTypes::ENUM_NATIVE, ['foo', 'bar', 'baz'], 'bar'), 'DEFAULT \'bar\''],
            [static::createEnumeratedColumn(PropelTypes::SET_NATIVE, ['foo', 'bar', 'baz'], 'bar'), 'DEFAULT \'bar\''],

        ];
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getColumnDefaultValueDDLDataProvider')]
    public function testGetColumnDefaultValueDDL($column, $default)
    {
        $this->assertEquals($default, static::getPlatform()->getColumnDefaultValueDDL($column));
    }

    public static function getColumnBindingDataProvider(): array
    {
        return [
            [static::createColumn(PropelTypes::DATE, '2020-02-03'), '$stmt->bindValue(ID, ACCESSOR, PDO::PARAM_STR);'],
            [static::createColumn(PropelTypes::BLOB, 'BLOB'), '$stmt->bindValue(ID, ACCESSOR, PDO::PARAM_LOB);'],
        ];
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getColumnBindingDataProvider')]
    public function testGetColumnBindingPHP($column, $default)
    {
        $this->assertStringContainsString($default, static::getPlatform()->getColumnBindingPHP($column, 'ID', 'ACCESSOR'));
    }

    /**
     * @return void
     */
    public function testDoesNotSupportNativeOnDeleteTriggers()
    {
        $this->assertFalse(static::getPlatform()->supportsNativeDeleteTrigger());
    }

    public static function GetTemporalFormatterDataProvider(): array
    {
        return [
            [PropelTypes::DATE, null, 'Y-m-d'],
            [PropelTypes::TIME, null, 'H:i:s'],
            [PropelTypes::TIMESTAMP, null, 'Y-m-d H:i:s'],
            [PropelTypes::DATETIME, null, 'Y-m-d H:i:s'],
            [PropelTypes::DATE, 6, 'Y-m-d'],
            [PropelTypes::TIME, 6, 'H:i:s.u'],
            [PropelTypes::TIMESTAMP, 6, 'Y-m-d H:i:s.u'],
            [PropelTypes::DATETIME, 6, 'Y-m-d H:i:s.u'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('GetTemporalFormatterDataProvider')]
    public function testGetTemporalFormatter(string $columnType, int|null $size, string $expectedFormat): void
    {
        $column = static::createColumn($columnType, null, $size);
        $actual = static::getPlatform()->getTemporalFormatter($column);

        $this->assertSame($expectedFormat, $actual);
    }
}
