<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Builder\Om;

use GeneratedQuerySetNativeColumnTypeTest\NativeSetTestEntity;
use GeneratedQuerySetNativeColumnTypeTest\NativeSetTestEntityQuery;
use Propel\Common\Util\SetColumnConverter;
use Propel\Generator\Platform\MysqlPlatform;
use Propel\Generator\Util\QuickBuilder;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Propel;
use Propel\Tests\TestCaseFixtures;

/**
 * @group database
 * @group mysql
 */
class GeneratedQuerySetNativeColumnTypeTest extends TestCaseFixtures
{
    /**
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        static::createSchema();
    }

    /**
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        $connection = Propel::getServiceContainer()->getConnection('bookstore');
        $connection->exec('DROP TABLE IF EXISTS native_set_test_entity;');

        parent::tearDownAfterClass();
    }

    /**
     * @return void
     */
    public static function createSchema(): void
    {
            $schema = <<<EOF
<database name="bookstore" namespace="GeneratedQuerySetNativeColumnTypeTest">
    <table name="native_set_test_entity">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <column name="tags" type="SET_NATIVE" valueSet="foo, bar, baz, bar23"/>
        <column name="set_with_singular_name" type="SET_NATIVE" valueSet="foo, bar"/>
    </table>
</database>
EOF;
            $builder = new QuickBuilder();
            $builder->setSchema($schema);
            $builder->setVfs(false);
            $builder->setPlatform(new MysqlPlatform());
            $connection = Propel::getServiceContainer()->getConnection('bookstore');
            $connection->exec('DROP TABLE IF EXISTS native_set_test_entity;');
            $builder->buildSQL($connection);
            $builder->buildClasses();
            $e0 = new NativeSetTestEntity();
            $e0->save();
            $e1 = new NativeSetTestEntity();
            $e1->setTags(['foo', 'bar', 'baz']);
            $e1->save();
            $e2 = new NativeSetTestEntity();
            $e2->setTags(['bar']);
            $e2->save();
            $e3 = new NativeSetTestEntity();
            $e3->setTags(['bar23']);
            $e3->save();
    }

    /**
     * @return void
     */
    public function testActiveQueryMethods()
    {
        $queryClass = '\GeneratedQuerySetNativeColumnTypeTest\NativeSetTestEntityQuery';
        $this->assertTrue(method_exists($queryClass, 'filterByTags'));
        $this->assertTrue(method_exists($queryClass, 'filterByTag'));

        $this->assertTrue(method_exists($queryClass, 'filterBySetWithSingularName'));
        // only plural column names get a singular filter
        $this->assertFalse(method_exists($queryClass, 'filterBySetWithSingularNames'));
    }

    /**
     * @return array<array>
     */
    public static function TestWhereDataProvider(): array
    {
        $valueSet = ['foo', 'bar', 'baz', 'bar23'];

        return [
            ['NativeSetTestEntity.Tags & ?', SetColumnConverter::convertToBitmask('bar23', $valueSet), 1, ['bar23']],
            ['NativeSetTestEntity.Tags & ?', SetColumnConverter::convertToBitmask(['bar', 'bar23'], $valueSet), 3, null],
            ['NativeSetTestEntity.Tags & ? ', SetColumnConverter::convertToBitmask(['bar'], $valueSet), 2, null],
            ['NativeSetTestEntity.Tags LIKE ?', 'bar23', 1, ['bar23']],
            ['NativeSetTestEntity.Tags LIKE ?', 'foo,bar,baz', 1, ['foo','bar','baz']],
            ['NativeSetTestEntity.Tags LIKE ?', 'foo,%,baz', 1, ['foo','bar','baz']],
        ];
    }

    /**
     *
     * @param string $clause
     * @param mixed $param
     * @param int $expectedRows
     * @param array|null $expectedTags
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('TestWhereDataProvider')]
    public function testWhere(string $clause, $param, int $expectedRows, array|null $expectedTags)
    {
        $e = NativeSetTestEntityQuery::create()->where($clause, $param)->find();

        $this->assertEquals($expectedRows, $e->count());
        if ($expectedTags !== null) {
            $this->assertEquals($expectedTags, $e[0]->getTags(), 'set columns are searchable by single value using where()');
        }
    }

    /**
     * @return array<array>
     */
    public static function FilterByDataProvider(): array
    {
        return [ // value, op, expected tags
            ['bar23', null, [['bar23']]],
            [null, null, [[], ['foo', 'bar', 'baz'], ['bar'], ['bar23']]],
            ['bar', null, [['foo', 'bar', 'baz'], ['bar']]],
            ['bar23', null, [['bar23']]],

            [[], Criteria::CONTAINS_ALL, [[], ['foo', 'bar', 'baz'], ['bar'], ['bar23']]],
            [null, Criteria::CONTAINS_ALL, [[], ['foo', 'bar', 'baz'], ['bar'], ['bar23']]],
            [['foo', 'bar'], Criteria::CONTAINS_ALL, [['foo', 'bar', 'baz']]],
            [['foo', 'bar23'], Criteria::CONTAINS_ALL, []],
            ['bar', Criteria::CONTAINS_ALL, [['foo', 'bar', 'baz'], ['bar']]],

            [[], Criteria::CONTAINS_SOME, [[], ['foo', 'bar', 'baz'], ['bar'], ['bar23']]],
            [null, Criteria::CONTAINS_SOME, [[], ['foo', 'bar', 'baz'], ['bar'], ['bar23']]],
            [['bar'], Criteria::CONTAINS_SOME, [['foo', 'bar', 'baz'], ['bar']]],
            [['foo', 'bar'], Criteria::CONTAINS_SOME, [['foo', 'bar', 'baz'], ['bar']]],
            [['foo', 'bar23'], Criteria::CONTAINS_SOME, [['foo', 'bar', 'baz'], ['bar23']]],

            [[], Criteria::CONTAINS_NONE, [[]]],
            [null, Criteria::CONTAINS_NONE, [[]]],
            [['bar'], Criteria::CONTAINS_NONE, [[], ['bar23']]],
            [['bar23'], Criteria::CONTAINS_NONE, [[], ['foo', 'bar', 'baz'], ['bar']]],
            [['foo', 'bar'], Criteria::CONTAINS_NONE, [[], ['bar23']]],
            ['bar', Criteria::CONTAINS_NONE, [[], ['bar23']]],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('FilterByDataProvider')]
    public function testFilterByColumn($value, string|null $op, array $expectedTags)
    {
        /** @var ObjectCollection $e */
        $e = NativeSetTestEntityQuery::create()
            ->filterByTags($value, $op)
            ->orderById()
            ->find();

        $this->assertEquals($expectedTags, $e->getColumnValues('Tags'));
    }
}
