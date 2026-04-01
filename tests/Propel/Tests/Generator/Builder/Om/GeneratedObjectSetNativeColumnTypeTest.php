<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Builder\Om;

use GeneratedObjectSetNativeColumnTypeTest\NativeSetEntity;
use GeneratedObjectSetNativeColumnTypeTest\NativeSetEntityQuery;
use Propel\Generator\Platform\MysqlPlatform;
use Propel\Generator\Util\QuickBuilder;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Propel;
use Propel\Tests\TestCaseFixtures;

/**
 * @group database
 * @group mysql
 */
class GeneratedObjectSetNativeColumnTypeTest extends TestCaseFixtures
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
        $connection->exec('DROP TABLE IF EXISTS native_set_entity;');

        parent::tearDownAfterClass();
    }

    /**
     * @return void
     */
    public static function createSchema(): void
    {
        $schema = <<<EOF
<database name="bookstore" namespace="GeneratedObjectSetNativeColumnTypeTest">
    <table name="native_set_entity">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <column name="tags" type="SET_NATIVE" valueSet="foo, bar, baz, 1, 4,(, foo bar "/>
        <column name="bar" type="SET_NATIVE" valueSet="foo, bar"/>
        <column name="defaults" type="SET_NATIVE" valueSet="foo, bar, foo baz" defaultValue="foo"/>
        <column name="bears" type="SET_NATIVE" valueSet="foo, bar, baz, kevin" defaultValue="bar,baz"/>
    </table>
</database>
EOF;
        $builder = new QuickBuilder();
        $builder->setSchema($schema);
        $builder->setVfs(false);
        $builder->setPlatform(new MysqlPlatform());
        $connection = Propel::getServiceContainer()->getConnection('bookstore');
        $connection->exec('DROP TABLE IF EXISTS native_set_entity;');
        $builder->buildSQL($connection);
        $builder->buildClasses();
    }

    /**
     * @return void
     */
    public function testActiveRecordMethods()
    {
        $className = '\GeneratedObjectSetNativeColumnTypeTest\NativeSetEntity';
        $this->assertTrue(method_exists($className, 'getTags'));
        $this->assertTrue(method_exists($className, 'hasTag'));
        $this->assertTrue(method_exists($className, 'setTags'));
        $this->assertTrue(method_exists($className, 'addTag'));
        $this->assertTrue(method_exists($className, 'removeTag'));
        // only plural column names get a tester, an adder, and a remover method
        $this->assertTrue(method_exists($className, 'getBar'));
        $this->assertFalse(method_exists($className, 'hasBar'));
        $this->assertTrue(method_exists($className, 'setBar'));
        $this->assertFalse(method_exists($className, 'addBar'));
        $this->assertFalse(method_exists($className, 'removeBar'));
    }

    /**
     * @return void
     */
    public function testColumnHydration()
    {
        $e = new NativeSetEntity();
        $e->hydrate([42, null, 'foo', 'foo,bar', 'foo,baz,kevin']);
        $expected = ['Id' => 42, 'Tags' => [], 'Bar' => ['foo'], 'Defaults' => ['foo', 'bar'], 'Bears' => ['foo', 'baz', 'kevin']];
        $this->assertEquals($expected, $e->toArray());
    }

    /**
     * @return array[]
     */
    public static function DefaultValueDataProvider(): array
    {
        return [
            ['Tags', []],
            ['Defaults', ['foo']],
            ['Bears', ['bar', 'baz']],
        ];
    }

    /**
     *
     * @param string $columnName
     * @param array $expectedDefaultValues
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('DefaultValueDataProvider')]
    public function testDefaultValuesAfterCreate(string $columnName, array $expectedDefaultValues): void
    {
        $e = new NativeSetEntity();
        $this->assertEquals($expectedDefaultValues, $e->getByName($columnName));
    }

    /**
     *
     * @param string $columnName
     * @param array $expectedDefaultValues
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('DefaultValueDataProvider')]
    public function testDefaultValuesAfterPersist(string $columnName, array $expectedDefaultValues)
    {
        $e = new NativeSetEntity();
        $e->save();
        $e->reload();

        $this->assertEquals($expectedDefaultValues, $e->getByName($columnName));
    }

    /**
     * @return void
     */
    public function testSetterRestoresOrderOfSet(): void
    {
        $e = new NativeSetEntity();
        $e->setTags(['1', '4', 'bar', 'foo']); // set order is: "foo, bar, baz, 1, 4,(, foo bar "

        $this->assertEquals(['foo', 'bar', '1', '4'], $e->getTags());
    }

    /**
     * @return void
     */
    public function testGetterValidValue()
    {
        $e = new NativeSetEntity();
        $this->setObjectPropertyValue($e, 'tags', 'foo,baz');
        $this->assertEquals(['foo', 'baz'], $e->getTags());
    }

    /**
     * @return void
     */
    public function testAdderAddsNewValueToExistingData()
    {
        $this->expectException(PropelException::class);
        $e = new NativeSetEntity();
        $e->addDefault('bar baz');
    }

    /**
     * @return void
     */
    public function testAdderAddsNewValueToMultipleExistingData()
    {
        $e = new NativeSetEntity();
        $this->assertEquals(['bar', 'baz'], $e->getBears());
        $e->addBear('kevin');
        $this->assertEquals(['bar', 'baz', 'kevin'], $e->getBears());
    }

    /**
     * @return void
     */
    public function testSetterArrayValue()
    {
        $e = new NativeSetEntity();
        $value = ['foo', '1'];
        $e->setTags($value);
        $this->assertEquals($value, $e->getTags(), 'array columns can store arrays');

        $internalValue = $this->getObjectPropertyValue($e, 'tags');
        $this->assertEquals('foo,1', $internalValue);
    }

    /**
     * @return void
     */
    public function testSetterResetValue()
    {
        $e = new NativeSetEntity();
        $e->setTags(['foo', '1']);
        $e->setTags([]);
        $this->assertEquals([], $e->getTags(), 'object columns can be reset');
    }

    /**
     * @return void
     */
    public function testSetterThrowsExceptionOnUnknownValue()
    {
        $this->expectException(PropelException::class);

        $e = new NativeSetEntity();
        $e->setBar(['bazz']);
    }

    /**
     * @return void
     */
    public function testAdder()
    {
        $e = new NativeSetEntity();
        $e->addTag('foo');
        $this->assertEquals(['foo'], $e->getTags());
        $e->addTag('1');
        $this->assertEquals(['foo', '1'], $e->getTags());
        $e->addTag('foo');
        $this->assertEquals(['foo', '1'], $e->getTags());
        $e->setTags(['foo bar', '4']);
        $e->addTag('foo');
        $this->assertEquals(['foo', '4', 'foo bar'], $e->getTags());
    }

    /**
     * @return void
     */
    public function testRemover()
    {
        $e = new NativeSetEntity();
        $e->removeTag('foo');
        $this->assertEquals([], $e->getTags());
        $e->setTags(['foo', '1']);
        $e->removeTag('foo');
        $this->assertEquals(['1'], $e->getTags());
        $e->removeTag('1');
        $this->assertEquals([], $e->getTags());
        $e->setTags(['1', 'bar', 'baz']);
        $e->removeTag('foo');
        $this->assertEquals(['bar', 'baz', '1'], $e->getTags());
        $e->removeTag('bar');
        $this->assertEquals(['baz', '1'], $e->getTags());
    }

    /**
     * @return void
     */
    public function testValueIsPersisted()
    {
        $e = new NativeSetEntity();
        $value = ['foo', '1'];
        $e->setTags($value)->save();
        $e->setTags([])->reload();
        $this->assertEquals($value, $e->getTags(), 'array columns are persisted');
    }

    /**
     * @return void
     */
    public function testGetterDoesNotKeepValueBetweenTwoHydrationsWhenUsingOnDemandFormatter()
    {
        NativeSetEntityQuery::create()->deleteAll();

        $e = new NativeSetEntity();
        $e->setTags(['foo', 'bar']);
        $e->save();

        $e = new NativeSetEntity();
        $e->setTags(['baz', '1']);
        $e->save();

        $q = NativeSetEntityQuery::create()
            ->setFormatter(ModelCriteria::FORMAT_ON_DEMAND)
            ->find();

        $tags = [];
        foreach ($q as $e) {
            $tags[] = $e->getTags();
        }
        $this->assertNotEquals($tags[0], $tags[1]);
    }

    /**
     * @return void
     */
    public function testFindOneOrCreate(): void
    {
        $e = NativeSetEntityQuery::create('e')
            ->filterByTags('baz')
            ->filterByBar(['foo', 'bar'])
            ->where('e.Defaults & ?', 4)
            ->where('e.BEARS = ?', 'baz,kevin')
            ->findOneOrCreate();
        $this->assertInstanceOf(NativeSetEntity::class, $e);
        $this->assertTrue($e->isNew());
        $expected = ['Id' => null, 'Tags' => ['baz'], 'Bar' => ['foo', 'bar'], 'Defaults' => ['foo baz'], 'Bears' => ['baz', 'kevin']];
        $this->assertEquals($expected, $e->toArray());

    }
}
