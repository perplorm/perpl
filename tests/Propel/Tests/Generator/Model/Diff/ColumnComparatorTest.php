<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Model\Diff;

use Propel\Generator\Model\Column;
use Propel\Generator\Model\ColumnDefaultValue;
use Propel\Generator\Model\Datatype\ColumnType;
use Propel\Generator\Model\Diff\ColumnComparator;
use Propel\Generator\Model\IdMethod;
use Propel\Generator\Model\Table;
use Propel\Generator\Platform\MysqlPlatform;
use Propel\Tests\TestCase;

/**
 * Tests for the ColumnComparator service class.
 */
class ColumnComparatorTest extends TestCase
{
    /**
     * @var \Propel\Generator\Platform\MysqlPlatform
     */
    protected $platform;

    /**
     * @return void
     */
    public function setUp(): void
    {
        $this->platform = new MysqlPlatform();
    }

    /**
     * @return void
     */
    public function testCompareNoDifference()
    {
        $c1 = new Column('');
        $c1->setTypeMapping($this->platform->getColumnTypeMapping(ColumnType::DOUBLE));
        $c1->getTypeMapping()->setScaleToValueIfNotNull(2);
        $c1->getTypeMapping()->setSizeToValueIfNotNull(3);
        $c1->setNotNull(true);
        $c1->getTypeMapping()->createDefaultValue(123);
        $c2 = new Column('');
        $c2->setTypeMapping($this->platform->getColumnTypeMapping(ColumnType::DOUBLE));
        $c2->getTypeMapping()->setScaleToValueIfNotNull(2);
        $c2->getTypeMapping()->setSizeToValueIfNotNull(3);
        $c2->setNotNull(true);
        $c2->getTypeMapping()->createDefaultValue(123);
        $this->assertEquals([], ColumnComparator::compareColumns($c1, $c2));
    }

    /**
     * @return void
     */
    public function testCompareType()
    {
        $c1 = new Column('', ColumnType::VARCHAR);
        $c1->setTypeMapping($this->platform->getColumnTypeMapping(ColumnType::VARCHAR));
        $c2 = new Column('');
        $c2->setTypeMapping($this->platform->getColumnTypeMapping(ColumnType::LONGVARCHAR));
        $expectedChangedProperties = [
            'type' => [ColumnType::VARCHAR, ColumnType::LONGVARCHAR],
            'sqlType' => [null, 'TEXT'],
        ];
        $this->assertEquals($expectedChangedProperties, ColumnComparator::compareColumns($c1, $c2));
    }

    /**
     * @return void
     */
    public function testCompareScale()
    {
        $c1 = new Column('');
        $c1->getTypeMapping()->setScaleToValueIfNotNull(2);
        $c2 = new Column('');
        $c2->getTypeMapping()->setScaleToValueIfNotNull(3);
        $expectedChangedProperties = ['scale' => [2, 3]];
        $this->assertEquals($expectedChangedProperties, ColumnComparator::compareColumns($c1, $c2));
    }

    /**
     * @return void
     */
    public function testCompareSize()
    {
        $c1 = new Column('');
        $c1->getTypeMapping()->setSizeToValueIfNotNull(2);
        $c2 = new Column('');
        $c2->getTypeMapping()->setSizeToValueIfNotNull(3);
        $expectedChangedProperties = ['size' => [2, 3]];
        $this->assertEquals($expectedChangedProperties, ColumnComparator::compareColumns($c1, $c2));
    }

    /**
     * @return void
     */
    public function testCompareSqlType()
    {
        $c1 = new Column('', ColumnType::INTEGER);
        $c2 = new Column('', ColumnType::INTEGER);
        $c2->getTypeMapping()->setSqlType('INTEGER(10) UNSIGNED');
        $expectedChangedProperties = ['sqlType' => [null, 'INTEGER(10) UNSIGNED']];
        $this->assertEquals($expectedChangedProperties, ColumnComparator::compareColumns($c1, $c2));
    }

    /**
     * @return void
     */
    public function testCompareNotNull()
    {
        $c1 = new Column('');
        $c1->setNotNull(true);
        $c2 = new Column('');
        $c2->setNotNull(false);
        $expectedChangedProperties = ['notNull' => [true, false]];
        $this->assertEquals($expectedChangedProperties, ColumnComparator::compareColumns($c1, $c2));
    }

    /**
     * @return void
     */
    public function testCompareDefaultValueToNull()
    {
        $c1 = new Column('');
        $c1->getTypeMapping()->createDefaultValue(123);
        $c2 = new Column('');
        $expectedChangedProperties = [
            'defaultValueType' => [ColumnDefaultValue::TYPE_VALUE, null],
            'defaultValueValue' => [123, null],
        ];
        $this->assertEquals($expectedChangedProperties, ColumnComparator::compareColumns($c1, $c2));
    }

    /**
     * @return void
     */
    public function testCompareDefaultValueFromNull()
    {
        $c1 = new Column('');
        $c2 = new Column('');
        $c2->getTypeMapping()->createDefaultValue(123);
        $expectedChangedProperties = [
            'defaultValueType' => [null, ColumnDefaultValue::TYPE_VALUE],
            'defaultValueValue' => [null, 123],
        ];
        $this->assertEquals($expectedChangedProperties, ColumnComparator::compareColumns($c1, $c2));
    }

    /**
     * @return void
     */
    public function testCompareDefaultValueValue()
    {
        $c1 = new Column('');
        $c1->getTypeMapping()->createDefaultValue(123);
        $c2 = new Column('');
        $c2->getTypeMapping()->createDefaultValue(456);
        $expectedChangedProperties = [
            'defaultValueValue' => [123, 456],
        ];
        $this->assertEquals($expectedChangedProperties, ColumnComparator::compareColumns($c1, $c2));
    }

    /**
     * @return void
     */
    public function testCompareDefaultValueType()
    {
        $c1 = new Column('');
        $c1->getTypeMapping()->createDefaultValue(123);
        $c2 = new Column('');
        $c2->getTypeMapping()->setDefaultValue(new ColumnDefaultValue(123, ColumnDefaultValue::TYPE_EXPR));
        $expectedChangedProperties = [
            'defaultValueType' => [ColumnDefaultValue::TYPE_VALUE, ColumnDefaultValue::TYPE_EXPR],
        ];
        $this->assertEquals($expectedChangedProperties, ColumnComparator::compareColumns($c1, $c2));
    }

    /**
     * @see http://www.propelorm.org/ticket/1141
     *
     * @return void
     */
    public function testCompareDefaultExrpCurrentTimestamp()
    {
        $c1 = new Column('');
        $c1->getTypeMapping()->setDefaultValue(new ColumnDefaultValue('NOW()', ColumnDefaultValue::TYPE_EXPR));
        $c2 = new Column('');
        $c2->getTypeMapping()->setDefaultValue(new ColumnDefaultValue('CURRENT_TIMESTAMP', ColumnDefaultValue::TYPE_EXPR));
        $this->assertEquals([], ColumnComparator::compareColumns($c1, $c2));
    }

    /**
     * @return void
     */
    public function testCompareAutoincrement()
    {
        $c1 = new Column('');
        $c1->setAutoIncrement(true);
        $c2 = new Column('');
        $c2->setAutoIncrement(false);
        $expectedChangedProperties = ['autoIncrement' => [true, false]];
        $this->assertEquals($expectedChangedProperties, ColumnComparator::compareColumns($c1, $c2));
    }

    /**
     * @return void
     */
    public function testCompareMultipleDifferences()
    {
        $c1 = new Column('');
        $c1->setTypeMapping($this->platform->getColumnTypeMapping(ColumnType::INTEGER));
        $c1->setNotNull(false);
        $c2 = new Column('');
        $c2->setTypeMapping($this->platform->getColumnTypeMapping(ColumnType::DOUBLE));
        $c2->getTypeMapping()->setScaleToValueIfNotNull(2);
        $c2->getTypeMapping()->setSizeToValueIfNotNull(3);
        $c2->setNotNull(true);
        $c2->getTypeMapping()->createDefaultValue(123);
        $expectedChangedProperties = [
            'type' => [ColumnType::INTEGER, ColumnType::DOUBLE],
            'scale' => [null, 2],
            'size' => [null, 3],
            'notNull' => [false, true],
            'defaultValueType' => [null, ColumnDefaultValue::TYPE_VALUE],
            'defaultValueValue' => [null, 123],
        ];
        $this->assertEquals($expectedChangedProperties, ColumnComparator::compareColumns($c1, $c2));
    }

    /**
     * @return void
     */
    public function testIgnoreIntegerSizeOnMySQL()
    {
        $platform = new MysqlPlatform();
        $domain = $platform->getColumnTypeMapping(ColumnType::INTEGER);

        $tableStub = $this->createStub(Table::class);
        $tableStub->method('getPlatform')->willReturn($platform);
        $tableStub->method('getIdMethod')->willReturn(IdMethod::NO_ID_METHOD);

        $fromColumn = new Column('foo');
        $fromColumn->setTable($tableStub);
        $fromColumn->setTypeMapping($domain);

        $toColumn = clone $fromColumn;
        $toColumn->getTypeMapping()->setSize(5);

        $hasChange = ColumnComparator::computeDiff($fromColumn, $toColumn);
        $this->assertFalse($hasChange, 'Changing integer column size should not build diff on MySQL');
    }
}
