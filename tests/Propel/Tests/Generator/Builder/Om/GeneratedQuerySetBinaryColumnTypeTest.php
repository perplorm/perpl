<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Builder\Om;

use ComplexColumnTypeEntitySet2;
use ComplexColumnTypeEntitySet2Query;
use PHPUnit\Framework\TestCase;
use Propel\Generator\Util\QuickBuilder;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;

/**
 * Tests the generated queries for array column types filters
 *
 * @author Francois Zaninotto
 */
class GeneratedQuerySetBinaryColumnTypeTest extends TestCase
{
    /**
     * @return void
     */
    public function setUp(): void
    {
        if (!class_exists('\ComplexColumnTypeEntitySet2')) {
            $schema = <<<EOF
<database name="generated_object_complex_type_test_set_2">
    <table name="complex_column_type_entity_set_2">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <column name="tags" type="SET_BINARY" valueSet="foo, bar, baz, bar23"/>
        <column name="value_set" type="SET_BINARY" valueSet="foo, bar, baz, kevin"/>
    </table>
</database>
EOF;
            QuickBuilder::buildSchema($schema);
            $e0 = new ComplexColumnTypeEntitySet2();
            $e0->save();
            $e1 = new ComplexColumnTypeEntitySet2();
            $e1->setTags(['foo', 'bar', 'baz']);
            $e1->save();
            $e2 = new ComplexColumnTypeEntitySet2();
            $e2->setTags(['bar']);
            $e2->save();
            $e3 = new ComplexColumnTypeEntitySet2();
            $e3->setTags(['bar23']);
            $e3->save();
        }
    }

    /**
     * @return void
     */
    public function testActiveQueryMethods()
    {
        $this->assertTrue(method_exists('\ComplexColumnTypeEntitySet2Query', 'filterByTags'));
        $this->assertTrue(method_exists('\ComplexColumnTypeEntitySet2Query', 'filterByTag'));
        // only plural column names get a singular filter
        $this->assertTrue(method_exists('\ComplexColumnTypeEntitySet2Query', 'filterByValueSet'));
    }

    /**
     * @return void
     */
    public function testColumnHydration()
    {
        $e = ComplexColumnTypeEntitySet2Query::create()->orderById()->offset(1)->findOne();
        $this->assertEquals(['foo', 'bar', 'baz'], $e->getTags(), 'array columns are correctly hydrated');
    }

    /**
     * @return array<array>
     */
    public static function TestWhereDataProvider(): array
    {
        return [
            ['ComplexColumnTypeEntitySet2.Tags LIKE ?', 'bar23', 1, ['bar23']],
            ['ComplexColumnTypeEntitySet2.Tags LIKE ?', ['foo', 'bar', 'baz'], 1, ['foo', 'bar', 'baz']],
            ['ComplexColumnTypeEntitySet2.Tags IN ?', ['baz', 'bar23'], 2, null],
            ['ComplexColumnTypeEntitySet2.Tags NOT IN ?', ['baz', 'bar23'], 1, null],
            ['ComplexColumnTypeEntitySet2.Tags NOT IN ?', null, 4, null],
            ['ComplexColumnTypeEntitySet2.Tags IN ?', null, 0, null],
        ];
    }

    /**
     *
     * @param string $clause
     * @param string|array $param
     * @param int $expectedRows
     * @param array|null $expectedTags
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('TestWhereDataProvider')]
    public function testWhere(string $clause, array|string|null $param, int $expectedRows, array|null $expectedTags)
    {
        $e = ComplexColumnTypeEntitySet2Query::create()
            ->where($clause, $param)
            ->find();
        $this->assertEquals($expectedRows, $e->count());
        if ($expectedTags !== null) {
            $this->assertEquals($expectedTags, $e[0]->getTags(), 'set columns are searchable by single value using where()');
        }
    }

    /**
     * @return void
     */
    public function testWhereInvalidValueThrowsException()
    {
        $this->expectException(PropelException::class);

        ComplexColumnTypeEntitySet2Query::create()
            ->where('ComplexColumnTypeEntitySet2.Tags LIKE ?', 'bar231')
            ->find();
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
        $e = ComplexColumnTypeEntitySet2Query::create()
            ->filterByTags($value, $op)
            ->orderById()
            ->find();

        $this->assertEquals($expectedTags, $e->getColumnValues('Tags'));
    }
}
