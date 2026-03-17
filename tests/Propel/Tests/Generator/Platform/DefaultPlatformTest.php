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
    protected DefaultPlatform|null $platform = null;

    /**
     * Get the Platform object for this class
     *
     * @return \Propel\Generator\Platform\DefaultPlatform
     */
    protected function getPlatform(): DefaultPlatform
    {
        if (!$this->platform) {
            $this->platform = new DefaultPlatform();
        }

        return $this->platform;
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->platform = null;
    }

    /**
     * @dataProvider provideValidBooleanValues
     *
     * @return void
     */
    public function testGetBooleanString($value)
    {
        $p = $this->getPlatform();

        $this->assertEquals('1', $p->getBooleanString($value));
    }

    public function provideValidBooleanValues()
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
     * @dataProvider provideInvalidBooleanValues
     *
     * @return void
     */
    public function testGetNonBooleanString($value)
    {
        $p = $this->getPlatform();

        $this->assertEquals('0', $p->getBooleanString($value));
    }

    public function provideInvalidBooleanValues()
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
        $p = $this->getPlatform();

        $unquoted = 'Nice';
        $quoted = $p->quote($unquoted);

        $this->assertEquals("'$unquoted'", $quoted);

        $unquoted = "Naughty ' string";
        $quoted = $p->quote($unquoted);
        $expected = "'Naughty '' string'";
        $this->assertEquals($expected, $quoted);
    }

    protected function createColumn($type, $defaultValue)
    {
        $column = new Column('');
        $column->setType($type);
        $column->setDefaultValue($defaultValue);

        return $column;
    }

    public function createEnumeratedColumn(string $propelType, $defaultValues, $defaultValue)
    {
        $column = $this->createColumn($propelType, $defaultValue);
        $column->setValueSet($defaultValues);

        return $column;
    }

    public function getColumnDefaultValueDDLDataProvider(): array
    {
        return [
            [$this->createColumn(PropelTypes::INTEGER, 0), 'DEFAULT 0'],
            [$this->createColumn(PropelTypes::INTEGER, '0'), 'DEFAULT 0'],
            [$this->createColumn(PropelTypes::VARCHAR, 'foo'), "DEFAULT 'foo'"],
            [$this->createColumn(PropelTypes::VARCHAR, 0), "DEFAULT '0'"],
            [$this->createColumn(PropelTypes::BOOLEAN, true), 'DEFAULT 1'],
            [$this->createColumn(PropelTypes::BOOLEAN, false), 'DEFAULT 0'],
            [$this->createColumn(PropelTypes::BOOLEAN, 'true'), 'DEFAULT 1'],
            [$this->createColumn(PropelTypes::BOOLEAN, 'false'), 'DEFAULT 0'],
            [$this->createColumn(PropelTypes::BOOLEAN, 'TRUE'), 'DEFAULT 1'],
            [$this->createColumn(PropelTypes::BOOLEAN, 'FALSE'), 'DEFAULT 0'],
            [$this->createEnumeratedColumn(PropelTypes::ENUM_BINARY, ['foo', 'bar', 'baz'], 'foo'), 'DEFAULT 0'],
            [$this->createEnumeratedColumn(PropelTypes::ENUM_BINARY, ['foo', 'bar', 'baz'], 'bar'), 'DEFAULT 1'],
            [$this->createEnumeratedColumn(PropelTypes::ENUM_BINARY, ['foo', 'bar', 'baz'], 'baz'), 'DEFAULT 2'],
            [$this->createEnumeratedColumn(PropelTypes::SET_BINARY, ['foo', 'bar', 'baz'], 'foo'), 'DEFAULT 1'],
            [$this->createEnumeratedColumn(PropelTypes::SET_BINARY, ['foo', 'bar', 'baz'], 'bar'), 'DEFAULT 2'],
            [$this->createEnumeratedColumn(PropelTypes::SET_BINARY, ['foo', 'bar', 'baz'], 'baz'), 'DEFAULT 4'],
            [$this->createEnumeratedColumn(PropelTypes::ENUM_NATIVE, ['foo', 'bar', 'baz'], 'bar'), 'DEFAULT \'bar\''],
            [$this->createEnumeratedColumn(PropelTypes::SET_NATIVE, ['foo', 'bar', 'baz'], 'bar'), 'DEFAULT \'bar\''],

        ];
    }

    /**
     * @dataProvider getColumnDefaultValueDDLDataProvider
     *
     * @return void
     */
    public function testGetColumnDefaultValueDDL($column, $default)
    {
        $this->assertEquals($default, $this->getPlatform()->getColumnDefaultValueDDL($column));
    }

    public function getColumnBindingDataProvider(): array
    {
        return [
            [$this->createColumn(PropelTypes::DATE, '2020-02-03'), '$stmt->bindValue(ID, ACCESSOR, PDO::PARAM_STR);'],
            [$this->createColumn(PropelTypes::BLOB, 'BLOB'), '$stmt->bindValue(ID, ACCESSOR, PDO::PARAM_LOB);'],
        ];
    }

    /**
     * @dataProvider getColumnBindingDataProvider
     *
     * @return void
     */
    public function testGetColumnBindingPHP($column, $default)
    {
        $this->assertStringContainsString($default, $this->getPlatform()->getColumnBindingPHP($column, 'ID', 'ACCESSOR'));
    }

    /**
     * @return void
     */
    public function testDoesNotSupportNativeOnDeleteTriggers()
    {
        $this->assertFalse($this->getPlatform()->supportsNativeDeleteTrigger());
    }
}
