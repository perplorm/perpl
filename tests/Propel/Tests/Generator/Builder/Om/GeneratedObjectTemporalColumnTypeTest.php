<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Builder\Om;

use ComplexColumnTypeEntity5;
use ComplexColumnTypeEntity5Query;
use ComplexColumnTypeEntity6;
use DateTime;
use Map\ComplexColumnTypeEntity5TableMap;
use Propel\Generator\Platform\MysqlPlatform;
use Propel\Generator\Util\QuickBuilder;
use Propel\Runtime\Exception\PropelException;
use Propel\Tests\TestCase;

/**
 * Tests the generated objects for temporal column types accessor & mutator.
 *
 * @author Francois Zaninotto
 */
class GeneratedObjectTemporalColumnTypeTest extends TestCase
{
    /**
     * @return void
     */
    public function setUp(): void
    {
        if (!class_exists('ComplexColumnTypeEntity5')) {
            $schema = <<<EOF
<database name="generated_object_complex_type_test_5">
    <table name="complex_column_type_entity_5">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <column name="bar1" type="DATE"/>
        <column name="bar2" type="TIME"/>
        <column name="bar3" type="TIMESTAMP"/>
        <column name="bar4" type="TIMESTAMP" default="2011-12-09"/>
        <column name="datetimecolumn" type="DATETIME"/>
    </table>
</database>
EOF;
            QuickBuilder::buildSchema($schema);
        }
    }

    /**
     * @return void
     */
    public function testNullValue()
    {
        $r = new ComplexColumnTypeEntity5();
        $this->assertNull($r->getBar1());
        $r->setBar1(new DateTime('2011-12-02'));
        $this->assertNotNull($r->getBar1());
        $r->setBar1(null);
        $this->assertNull($r->getBar1());
    }

    /**
     * @link http://propel.phpdb.org/trac/ticket/586
     *
     * @return void
     */
    public function testEmptyValue()
    {
        $r = new ComplexColumnTypeEntity5();
        $r->setBar1('');
        $this->assertNull($r->getBar1());
    }

    /**
     * @return void
     */
    public function testPreEpochValue()
    {
        $r = new ComplexColumnTypeEntity5();
        $r->setBar1(new DateTime('1602-02-02'));
        $this->assertEquals('1602-02-02', $r->getBar1(null)->format('Y-m-d'));

        $r->setBar1('1702-02-02');
        $this->assertTrue($r->isModified());
        $this->assertEquals('1702-02-02', $r->getBar1(null)->format('Y-m-d'));
    }

    /**
     * @return void
     */
    public function testInvalidValueThrowsPropelException()
    {
        $this->expectException(PropelException::class);

        $r = new ComplexColumnTypeEntity5();
        $r->setBar1('Invalid Date');
    }

    /**
     * @return void
     */
    public function testUnixTimestampValue()
    {
        $r = new ComplexColumnTypeEntity5();
        $r->setBar1(time());
        $this->assertEquals(date('Y-m-d'), $r->getBar1('Y-m-d'));

        $r = new ComplexColumnTypeEntity5();
        $r->setBar2(strtotime('12:55'));
        $this->assertEquals('12:55', $r->getBar2(null)->format('H:i'));

        $r = new ComplexColumnTypeEntity5();
        $r->setBar3(time());
        $this->assertEquals(date('Y-m-d H:i'), $r->getBar3('Y-m-d H:i'));
        
        $r = new ComplexColumnTypeEntity5();
        $r->setDatetimecolumn(time());
        $this->assertEquals(date('Y-m-d H:i'), $r->getDatetimecolumn('Y-m-d H:i'));
    }
    
    public static function AccessStorageFormatDataProvider()
    {
        $date = '2026-03-31';
        $time = '11:04:32.123456';

        return [
            // type description, column name, format, date/time string, optional input value 
            ['Date', 'Bar1','Y-m-d', $date,  null],
            ['Time', 'Bar2', 'H:i:s.u', $time, strtotime($time)],
            ['Timestamp', 'Bar3', 'Y-m-d H:i:s.u', "$date $time", null],
            ['Datetime', 'Datetimecolumn', 'Y-m-d H:i:s.u', "$date $time", null],
        ];
    }
    
    #[\PHPUnit\Framework\Attributes\DataProvider('AccessStorageFormatDataProvider')]
    public function testAccessStorageFormatWithoutObjectInstantiation(string $typeDescription, string $columnName, string $format, string $formattedDate, int|null $inputValue)
    {
        $r = new ComplexColumnTypeEntity5();
        $r->setByName($columnName, $inputDateValue ?? new DateTime($formattedDate))->save();
        $r->reload();

        $dateObjectPropertyName = strtolower($columnName) . '_date_object';
        $dateObjectAfterClear = $this->getObjectPropertyValue($r, $dateObjectPropertyName);
        $this->assertNull($dateObjectAfterClear, "internal object should be null after reload()");

        $getter = "get$columnName";
        $storedValue = $r->$getter($format);
        $this->assertSame($formattedDate, $storedValue, "should return storage date");

        $dateObjectAfterGetStorageFormat = $this->getObjectPropertyValue($r, $dateObjectPropertyName);
        $this->assertNull($dateObjectAfterGetStorageFormat, "getting storage format should not instantiate DateTime object");

        $dateTimeObject = $r->$getter();
        $this->assertInstanceOf(DateTime::class, $dateTimeObject, "should return DateTime object");

        $dateObjectAfterGetObject = $this->getObjectPropertyValue($r, $dateObjectPropertyName);
        $this->assertInstanceOf(DateTime::class, $dateObjectAfterGetObject, "should have set internal DateTime field");

        $rfcDate = $r->$getter('r');
        $expectedDate = $dateObjectAfterGetObject->format('r');
        $this->assertSame($expectedDate, $rfcDate, "should produce DateTime format output");
    }

    /**
     * @return void
     */
    public function testDateTimeGetterReturnsADateTime()
    {
        $r = new ComplexColumnTypeEntity5();
        $r->setBar3(new DateTime());
        $r->save();

        $this->assertInstanceOf('DateTime', $r->getBar3());

        $r->setBar3(strtotime('10/10/2011'));
        $r->save();

        $this->assertInstanceOf('DateTime', $r->getBar3());
    }

    /**
     * @return void
     */
    public function testDateTimeGetterReturnsAReference()
    {
        $r = new ComplexColumnTypeEntity5();
        $r->setBar3(new DateTime('2011-11-23'));
        $r->getBar3()->modify('+1 days');
        $this->assertEquals('2011-11-24', $r->getBar3('Y-m-d'));
    }

    /**
     * @return void
     */
    public function testHasOnlyDefaultValues()
    {
        $r = new ComplexColumnTypeEntity5();
        $this->assertEquals('2011-12-09', $r->getBar4('Y-m-d'));
        $this->assertTrue($r->hasOnlyDefaultValues());
    }

    /**
     * @return void
     */
    public function testHydrateWithMysqlInvalidDate()
    {
        $schema = <<<EOF
<database name="generated_object_complex_type_test_6">
<table name="complex_column_type_entity_6">
    <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
    <column name="bar1" type="DATE"/>
    <column name="bar2" type="TIME"/>
    <column name="bar3" type="TIMESTAMP"/>
</table>
</database>
EOF;
        $builder = new QuickBuilder();
        $builder->setSchema($schema);
        $builder->setPlatform(new MysqlPlatform());
        $builder->buildClasses();
        $r = new ComplexColumnTypeEntity6();
        $r->hydrate([
            123,
            '0000-00-00',
            '00:00:00',
            '0000-00-00 00:00:00',
        ]);
        $this->assertNull($r->getBar1());
        $this->assertEquals('00:00:00', $r->getBar2()->format('H:i:s'));
        $this->assertNull($r->getBar3());
    }

    /**
     * @return void
     */
    public function testDateTimesSerialize()
    {
        $r = new ComplexColumnTypeEntity5();
        $r->setBar3(new DateTime('2011-11-23'));
        $str = serialize($r);

        $r2 = unserialize($str);
        $this->assertEquals('2011-11-23', $r2->getBar3('Y-m-d'));
    }
}
