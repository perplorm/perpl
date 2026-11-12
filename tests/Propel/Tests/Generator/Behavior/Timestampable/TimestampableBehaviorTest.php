<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Behavior\Timestampable;

use Propel\Generator\Config\QuickGeneratorConfig;
use Propel\Generator\Util\QuickBuilder;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Tests\Bookstore\Behavior\Map\Table1TableMap;
use Propel\Tests\Bookstore\Behavior\Map\Table2TableMap;
use Propel\Tests\Bookstore\Behavior\Table2;
use Propel\Tests\Bookstore\Behavior\Table2Query;
use Propel\Tests\Helpers\Bookstore\BookstoreTestBase;
use TableWithoutCreatedAt;
use TableWithoutUpdatedAt;
use TableDateTimeClass;
use TableColumnTypes;

/**
 * Tests for TimestampableBehavior class
 *
 * @author François Zaninotto
 *
 * @group database
 */
class TimestampableBehaviorTest extends BookstoreTestBase
{
    /**
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        static::$isInitialized = false;
        parent::setUpBeforeClass();
    }

    private function assertTimeEquals($expected, $actual, $message = '')
    {
        // accept $expected or ($expected + 1s)
        return $this->assertThat(
            $actual,
            $this->logicalOr(
                $this->equalTo($expected),
                $this->equalTo($expected + 1)
            ),
            $message
        );
    }

    /**
     * @return void
     */
    public function testParameters()
    {
        $table2 = Table2TableMap::getTableMap();
        $this->assertEquals(count($table2->getColumns()), 4, 'Timestampable adds two columns by default');
        $this->assertTrue(method_exists('\Propel\Tests\Bookstore\Behavior\Table2', 'getCreatedAt'), 'Timestampable adds a created_at column by default');
        $this->assertTrue(method_exists('\Propel\Tests\Bookstore\Behavior\Table2', 'getUpdatedAt'), 'Timestampable adds an updated_at column by default');
        $table1 = Table1TableMap::getTableMap();
        $this->assertEquals(count($table1->getColumns()), 4, 'Timestampable does not add two columns when they already exist');
        $this->assertTrue(method_exists('\Propel\Tests\Bookstore\Behavior\Table1', 'getCreatedOn'), 'Timestampable allows customization of create_column name');
        $this->assertTrue(method_exists('\Propel\Tests\Bookstore\Behavior\Table1', 'getUpdatedOn'), 'Timestampable allows customization of update_column name');
    }

    /**
     * @return void
     */
    public function testSaveCreatesTimestamp()
    {
        $t1 = new Table2();
        $this->assertNull($t1->getUpdatedAt());
        $this->assertNull($t1->getCreatedAt());

        $tupdate = time();
        $t1->save();

        $this->assertTimeEquals($tupdate, $t1->getUpdatedAt('U'));
        $this->assertEquals(
            $t1->getUpdatedAt(),
            $t1->getCreatedAt(),
            'updated_column and created_column should match after creation'
        );
    }

    public function testSetCreatedManually(): void
    {
        $t1 = new Table2();
        $manualDate = '2026-11-10 20:13:00';
        $t1->setCreatedAt($manualDate)
            ->save();

        $this->assertSame($manualDate, $t1->getCreatedAt('Y-m-d H:i:s'));
    }

    public function testSaveUpdatesTimestamp(): void
    {
        $t1 = new Table2();
        $manualDate = '2026-11-10 20:13:00';
        $t1->setUpdatedAt($manualDate)
            ->save();
        $this->assertSame($manualDate, $t1->getUpdatedAt('Y-m-d H:i:s'), 'should preserve manual update');

        $t1->setTitle('foo');
        $tupdate = time();
        $t1->save();
        $this->assertTimeEquals($tupdate, $t1->getUpdatedAt('U'), 'Timestampable changes updated_column to time() on update');
    }

    /**
     * @return void
     */
    public function testPreSaveNoChange()
    {
        $t1 = new Table2();
        $manualDate = '2026-11-10 20:13:00';
        $t1->setUpdatedAt(new \DateTime($manualDate))->save();
        $this->assertSame($manualDate, $t1->getUpdatedAt('Y-m-d H:i:s'), 'should preserve manual update');

        $t1->save();
        $this->assertSame($manualDate, $t1->getUpdatedAt('Y-m-d H:i:s'), 'should not update when object was not modified');
    }

    /**
     * @return void
     */
    public function testPreSaveManuallyUpdated()
    {
        $t1 = new Table2();
        $t1->setUpdatedAt(time() - 10);
        $tsave = time();
        $t1->save();
        $this->assertLessThan($tsave, $t1->getUpdatedAt('U'), 'Timestampable does not set updated_column to time() on creation when it is set by the user');

        $t1->setUpdatedAt(time() - 15);
        $tupdate = time();
        $t1->save();
        $this->assertLessThan($tupdate, $t1->getUpdatedAt('U'), 'Timestampable does not change updated_column to time() on update when it is set by the user');
    }

    /**
     * @return void
     */
    public function testPreInsert()
    {
        $t1 = new Table2();
        $manualDate = '2026-11-10 20:13:00';
        $t1->setCreatedAt($manualDate)
            ->save();

        $this->assertSame($manualDate, $t1->getCreatedAt('Y-m-d H:i:s'), 'should use specified creation time');

        $t1->setTitle('foo')
            ->save();
        $this->assertSame($manualDate, $t1->getCreatedAt('Y-m-d H:i:s'), 'should not update creation time');
    }

    /**
     * @return void
     */
    public function testPreInsertManuallyUpdated()
    {
        $t1 = new Table2();
        $t1->setCreatedAt(time() - 10);
        $tsave = time();
        $t1->save();
        $this->assertLessThan($tsave, $t1->getCreatedAt('U'), 'Timestampable does not set created_column to time() on creation when it is set by the user');
    }

    /**
     * @return void
     */
    public function testObjectKeepUpdateDateUnchanged()
    {
        $t1 = new Table2();
        $t1->setUpdatedAt(time() - 10);
        $tsave = time();
        $t1->save();
        $this->assertLessThan($tsave, $t1->getUpdatedAt('U'));
        // let's save it a second time; the updated_at should be changed
        $t1->setTitle('foo');
        $tsave = time();
        $t1->save();
        $this->assertTimeEquals($tsave, $t1->getUpdatedAt('U'));

        // now let's do this a second time
        $t1 = new Table2();
        $t1->setUpdatedAt(time() - 10);
        $tsave = time();
        $t1->save();
        $this->assertLessThan($tsave, $t1->getUpdatedAt('U'));
        // let's save it a second time; the updated_at should be changed
        $t1->keepUpdateDateUnchanged();
        $t1->setTitle('foo');
        $tsave = time();
        $t1->save();
        $this->assertLessThan($tsave, $t1->getUpdatedAt('U'), 'keepUpdateDateUnchanged() prevents the behavior from updating the update date');
    }

    /**
     * @return void
     */
    protected function populateUpdatedAt()
    {
        Table2Query::create()->deleteAll();
        $ts = new ObjectCollection();
        $ts->setModel('\Propel\Tests\Bookstore\Behavior\Table2');
        for ($i = 0; $i < 10; $i++) {
            $t = new Table2();
            $t->setTitle('UpdatedAt' . $i);
            /* additional -30 in case the check is done in the same second (which we can't guarantee, so no assert(8 ...) below).*/
            $t->setUpdatedAt(time() - $i * 24 * 60 * 60 - 30);
            $ts[] = $t;
        }
        $ts->save();
    }

    /**
     * @return void
     */
    protected function populateCreatedAt()
    {
        Table2Query::create()->deleteAll();
        $ts = new ObjectCollection();
        $ts->setModel('\Propel\Tests\Bookstore\Behavior\Table2');
        for ($i = 0; $i < 10; $i++) {
            $t = new Table2();
            $t->setTitle('CreatedAt' . $i);
            $t->setCreatedAt(time() - $i * 24 * 60 * 60 - 30);
            $ts[] = $t;
        }
        $ts->save();
    }

    /**
     * @return void
     */
    public function testQueryRecentlyUpdated()
    {
        $q = Table2Query::create()->recentlyUpdated();
        $this->assertTrue($q instanceof Table2Query, 'recentlyUpdated() returns the current Query object');
        $this->populateUpdatedAt();
        $ts = Table2Query::create()->recentlyUpdated()->count();
        $this->assertEquals(7, $ts, 'recentlyUpdated() returns the elements updated in the last 7 days by default');
        $ts = Table2Query::create()->recentlyUpdated(5)->count();
        $this->assertEquals(5, $ts, 'recentlyUpdated() accepts a number of days as parameter');
    }

    /**
     * @return void
     */
    public function testQueryRecentlyCreated()
    {
        $q = Table2Query::create()->recentlyCreated();
        $this->assertTrue($q instanceof Table2Query, 'recentlyCreated() returns the current Query object');
        $this->populateCreatedAt();
        $ts = Table2Query::create()->recentlyCreated()->count();
        $this->assertEquals(7, $ts, 'recentlyCreated() returns the elements created in the last 7 days by default');
        $ts = Table2Query::create()->recentlyCreated(5)->count();
        $this->assertEquals(5, $ts, 'recentlyCreated() accepts a number of days as parameter');
    }

    /**
     * @return void
     */
    public function testQueryLastUpdatedFirst()
    {
        $q = Table2Query::create()->lastUpdatedFirst();
        $this->assertTrue($q instanceof Table2Query, 'lastUpdatedFirst() returns the current Query object');
        $this->populateUpdatedAt();
        $t = Table2Query::create()->lastUpdatedFirst()->findOne();
        $this->assertEquals('UpdatedAt0', $t->getTitle(), 'lastUpdatedFirst() returns element with most recent update date first');
    }

    /**
     * @return void
     */
    public function testQueryFirstUpdatedFirst()
    {
        $q = Table2Query::create()->firstUpdatedFirst();
        $this->assertTrue($q instanceof Table2Query, 'firstUpdatedFirst() returns the current Query object');
        $this->populateUpdatedAt();
        $t = Table2Query::create()->firstUpdatedFirst()->findOne();
        $this->assertEquals('UpdatedAt9', $t->getTitle(), 'firstUpdatedFirst() returns the element with oldest updated date first');
    }

    /**
     * @return void
     */
    public function testQueryLastCreatedFirst()
    {
        $q = Table2Query::create()->lastCreatedFirst();
        $this->assertTrue($q instanceof Table2Query, 'lastCreatedFirst() returns the current Query object');
        $this->populateCreatedAt();
        $t = Table2Query::create()->lastCreatedFirst()->findOne();
        $this->assertEquals('CreatedAt0', $t->getTitle(), 'lastCreatedFirst() returns element with most recent create date first');
    }

    /**
     * @return void
     */
    public function testQueryFirstCreatedFirst()
    {
        $q = Table2Query::create()->firstCreatedFirst();
        $this->assertTrue($q instanceof Table2Query, 'firstCreatedFirst() returns the current Query object');
        $this->populateCreatedAt();
        $t = Table2Query::create()->firstCreatedFirst()->findOne();
        $this->assertEquals('CreatedAt9', $t->getTitle(), 'firstCreatedFirst() returns the element with oldest create date first');
    }

    /**
     * @return void
     */
    public function testDisableUpdatedAt()
    {
        $schema = <<<EOF
<database name="timestampable_database">
    <table name="table_without_updated_at">
        <column name="id" type="INTEGER" primaryKey="true" autoIncrement="true"/>
        <column name="name" type="varchar"/>

        <behavior name="timestampable">
            <parameter name="disable_updated_at" value="true"/>
        </behavior>
    </table>
</database>
EOF;

        QuickBuilder::buildDatabaseAndClasses($schema);

        $this->assertTrue(method_exists('TableWithoutUpdatedAt', 'getCreatedAt'));
        $this->assertTrue(method_exists('TableWithoutUpdatedAt', 'setCreatedAt'));
        $this->assertFalse(method_exists('TableWithoutUpdatedAt', 'getUpdatedAt'));
        $this->assertFalse(method_exists('TableWithoutUpdatedAt', 'setUpdatedAt'));

        $obj = new TableWithoutUpdatedAt();
        $obj->setName('Peter');
        $this->assertNull($obj->getCreatedAt());
        $this->assertEquals(1, $obj->save());
        $this->assertNotNull($obj->getCreatedAt());
    }

    /**
     * @return void
     */
    public function testDisableCreatedAt()
    {
        $schema = <<<EOF
<database name="timestampable_database">
    <table name="table_without_created_at">
        <column name="id" type="INTEGER" primaryKey="true" autoIncrement="true"/>
        <column name="name" type="varchar"/>

        <behavior name="timestampable">
            <parameter name="disable_created_at" value="true"/>
        </behavior>
    </table>
</database>
EOF;

        QuickBuilder::buildDatabaseAndClasses($schema);

        $this->assertFalse(method_exists('TableWithoutCreatedAt', 'getCreatedAt'));
        $this->assertFalse(method_exists('TableWithoutCreatedAt', 'setCreatedAt'));
        $this->assertTrue(method_exists('TableWithoutCreatedAt', 'getUpdatedAt'));
        $this->assertTrue(method_exists('TableWithoutCreatedAt', 'setUpdatedAt'));

        $obj = new TableWithoutCreatedAt();
        $obj->setName('Peter');
        $this->assertNull($obj->getUpdatedAt());
        $this->assertEquals(1, $obj->save());
        $this->assertNotNull($obj->getUpdatedAt());
    }

    /**
     * @return void
     */
    public function testDateTimeClass()
    {
        $schema = <<<EOF
<database name="timestampable_database">
    <table name="table_date_time_class">
        <column name="id" type="INTEGER" primaryKey="true" autoIncrement="true"/>
        <behavior name="timestampable">
        </behavior>
    </table>
</database>
EOF;

        // Custom Configuration to use DateTimeImmutable
        $builder = new QuickBuilder($schema);
        $config = new QuickGeneratorConfig([
            'propel' => [
                'generator' => [
                    'dateTime' => [
                        'dateTimeClass' => 'DateTimeImmutable',
                    ],
                ],
            ],
        ]);
        $builder->setConfig($config);
        $builder->build();

        $obj = new TableDateTimeClass();
        $obj->save();
        $this->assertInstanceOf('DateTimeImmutable', $obj->getCreatedAt(), 'Timestampable behavior does not use the propel.generator.dateTime.dateTimeClass configuration property for created_column');
        $this->assertInstanceOf('DateTimeImmutable', $obj->getUpdatedAt(), 'Timestampable behavior does not use the propel.generator.dateTime.dateTimeClass configuration property for updated_column');
    }

    /**
     * @return void
     */
    public function testColumnTypes()
    {
        $schema = <<<EOF
<database name="timestampable_database">
    <table name="table_column_types">
        <column name="id" type="INTEGER" primaryKey="true" autoIncrement="true"/>
        <column name="created_at" type="INTEGER"/>
        <behavior name="timestampable"/>
    </table>
</database>
EOF;

        QuickBuilder::buildDatabaseAndClasses($schema);
        $obj = new TableColumnTypes();
        $obj->save();
        $this->assertEquals($obj->getCreatedAt('U'), $obj->getUpdatedAt('U'), 'Timestampable does not set created_column and updated_column to the same value when column types are different');
    }
}
