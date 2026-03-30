<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Runtime\TypeTests;

use PHPUnit\Framework\Attributes\DataProvider;
use Propel\Tests\Bookstore\Map\TypeObjectTableMap;
use Propel\Tests\Bookstore\TypeNumeric;
use Propel\Tests\Bookstore\TypeNumericQuery;
use Propel\Tests\Bookstore\TypeObject;
use Propel\Tests\Bookstore\TypeObjectQuery;
use Propel\Tests\Helpers\Bookstore\BookstoreTestBase;
use Propel\Tests\Runtime\TypeTests\DummyObjectClass;
use ReflectionClass;

/**
 * NOTE: Uses classes from bookstore/types-schema.xml.
 *
 * @group database
 */
class TypeTest extends BookstoreTestBase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        TypeNumericQuery::create()->deleteAll();
    }

    /**
     * @return void
     */
    public function testTypeHintClass()
    {
        $reflection = new ReflectionClass(TypeObject::class);
        $method = $reflection->getMethod('setDummyObject');
        $param = $method->getParameters()[0];

        $this->assertEquals(DummyObjectClass::class, $param->getType()->getName());
        $this->assertTrue($param->allowsNull());
    }

    /**
     * @return void
     */
    public function testTypeHintArray()
    {
        $reflection = new ReflectionClass(TypeObject::class);
        $method = $reflection->getMethod('setSomeArray');
        $param = $method->getParameters()[0];

        $this->assertTrue($param->getType() && $param->getType()->getName() === 'array');
        $this->assertTrue($param->allowsNull());
    }

    /**
     * @return void
     */
    public function testObjectType()
    {
        TypeObjectQuery::create()->deleteAll();

        $a = 'abc123$%&';
        $b = '3456&*(][';
        $c = "_$%^xxx\0d2";

        $objectInstance = new DummyObjectClass();
        $objectInstance->setPropPublic($a);
        $objectInstance->setPropProtected($b);
        $objectInstance->setPropPrivate($c);

        $typeObjectEntity = new TypeObject();
        $this->assertNull($typeObjectEntity->getDetails(), 'object columns are null by default');

        $typeObjectEntity->setDetails($objectInstance);
        $this->assertEquals($objectInstance, $typeObjectEntity->getDetails());
        $this->assertEquals($a, $typeObjectEntity->getDetails()->getPropPublic());
        $this->assertEquals($b, $typeObjectEntity->getDetails()->getPropProtected());
        $this->assertEquals($c, $typeObjectEntity->getDetails()->getPropPrivate());

        $typeObjectEntity->save();

        $typeObjectEntity->setDetails($objectInstance);
        $this->assertFalse($typeObjectEntity->isModified('details'));

        $clone = clone $objectInstance;
        $clone->setPropPublic('changed');

        $typeObjectEntity->setDetails($clone);
        $this->assertTrue($typeObjectEntity->isModified('details'));

        TypeObjectTableMap::clearInstancePool();
        $typeObjectEntity = TypeObjectQuery::create()->findOne();

        $this->assertEquals($objectInstance, $typeObjectEntity->getDetails());
        $this->assertEquals($a, $typeObjectEntity->getDetails()->getPropPublic());
        $this->assertEquals($b, $typeObjectEntity->getDetails()->getPropProtected());
        $this->assertEquals($c, $typeObjectEntity->getDetails()->getPropPrivate());

        // change propPublic, same object
        $detailsObject = $typeObjectEntity->getDetails();
        $detailsObject->setPropPublic('changed');
        $typeObjectEntity->setDetails($detailsObject);
        $typeObjectEntity->save();
        TypeObjectTableMap::clearInstancePool();
        $typeObjectEntity = TypeObjectQuery::create()->findOne();

        $this->assertEquals($detailsObject, $typeObjectEntity->getDetails());
        $this->assertEquals('changed', $typeObjectEntity->getDetails()->getPropPublic());

        // same but with a more complex object
        $q = TypeObjectQuery::create();
        $typeObjectEntity->setDetails($q);
        $this->assertEquals($q, $typeObjectEntity->getDetails());

        $typeObjectEntity->save();

        TypeObjectTableMap::clearInstancePool();
        $typeObjectEntity = TypeObjectQuery::create()->findOne();

        $this->assertEquals($q, $typeObjectEntity->getDetails());
    }

    public static function DecimalValuesDataProvider(): array
    {
        $values = [ // string $inputValue, string $storedValue
            ['12345.333', '12345.3330'],
            ['12345', '12345.0000'],
        ];

        return [ // string $columnName, string $inputValue, string $storedValue
            ...array_map(fn ($dataSet) => ['Decimal', ...$dataSet], $values),
            ...array_map(fn ($dataSet) => ['Numeric', ...$dataSet], $values),
        ];
    }

    #[DataProvider('DecimalValuesDataProvider')]
    public function testDecimalType(string $columnName, string $inputValue, string $storedValue): void
    {
        if (static::runningOnSQLite()) {
            $this->markTestSkipped('Sqlite stores decimals as strings.');
        }

        $o = new TypeNumeric();
        $o->setByName($columnName, $inputValue)->save();

        $o->reload();
        $this->assertSame($storedValue, $o->getByName($columnName));

        $foundValue = TypeNumericQuery::create()->filterBy($columnName, $storedValue)->findOne();
        $this->assertSame($o, $foundValue);
    }
}
