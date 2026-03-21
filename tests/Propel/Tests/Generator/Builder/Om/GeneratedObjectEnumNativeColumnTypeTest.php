<?php

declare(strict_types = 1);

namespace Propel\Tests\Generator\Builder\Om;

use EnumNativeEntity;
use EnumNativeEntityQuery;
use Map\EnumNativeEntityTableMap;
use Propel\Generator\Platform\PgsqlPlatform;
use Propel\Generator\Util\QuickBuilder;
use Propel\Runtime\Adapter\Pdo\PgsqlAdapter;
use Propel\Tests\Helpers\ColorsBackedEnum;
use Propel\Tests\TestCase;

/**
 * Tests runtime behavior of ENUM_NATIVE columns with PHP backed enums on PostgreSQL.
 *
 * @group pgsql
 * @group database
 */
class GeneratedObjectEnumNativeColumnTypeTest extends TestCase
{
    private static bool $built = false;

    public function setUp(): void
    {
        if (strtolower(getenv('DB') ?: '') !== 'pgsql') {
            $this->markTestSkipped('Test requires PostgreSQL (set DB=pgsql).');
        }

        if (self::$built) {
            return;
        }

        $enumClass = ColorsBackedEnum::class;
        $schema = <<<EOF
<database name="enum_native_test">
    <table name="enum_native_entity">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <column name="color" type="ENUM_NATIVE" valueSet="red,blue,yellow" sqlType="enum_native_entity_color" phpType="$enumClass"/>
        <column name="nullable_color" type="ENUM_NATIVE" valueSet="red,blue,yellow" sqlType="enum_native_entity_color" phpType="$enumClass" required="false"/>
    </table>
</database>
EOF;

        $host = getenv('DB_HOSTNAME') ?: '127.0.0.1';
        $dbName = getenv('DB_NAME') ?: 'test';
        $dsn = "pgsql:host=$host;dbname=$dbName";
        $user = getenv('DB_USER') ?: null;
        $pass = getenv('DB_PW') ?: null;

        $builder = new QuickBuilder();
        $builder->setSchema($schema);
        $builder->setPlatform(new PgsqlPlatform());

        $builder->build($dsn, $user, $pass, new PgsqlAdapter());

        self::$built = true;
    }

    public function testInsertAndHydrateEnum(): void
    {
        $entity = new EnumNativeEntity();
        $entity->setColor(ColorsBackedEnum::Red);
        $entity->save();

        EnumNativeEntityTableMap::clearInstancePool();

        $fetched = EnumNativeEntityQuery::create()->findOneById($entity->getId());
        $this->assertNotNull($fetched);
        $this->assertSame(ColorsBackedEnum::Red, $fetched->getColor());
    }

    public function testUpdateEnum(): void
    {
        $entity = new EnumNativeEntity();
        $entity->setColor(ColorsBackedEnum::Blue);
        $entity->save();

        $entity->setColor(ColorsBackedEnum::Yellow);
        $entity->save();

        EnumNativeEntityTableMap::clearInstancePool();

        $fetched = EnumNativeEntityQuery::create()->findOneById($entity->getId());
        $this->assertSame(ColorsBackedEnum::Yellow, $fetched->getColor());
    }

    public function testNullableEnumColumn(): void
    {
        $entity = new EnumNativeEntity();
        $entity->setColor(ColorsBackedEnum::Red);
        $entity->setNullableColor(null);
        $entity->save();

        EnumNativeEntityTableMap::clearInstancePool();

        $fetched = EnumNativeEntityQuery::create()->findOneById($entity->getId());
        $this->assertNull($fetched->getNullableColor());
    }

    public function testFilterByEnumColumn(): void
    {
        // Clear existing data
        EnumNativeEntityQuery::create()->deleteAll();

        $e1 = new EnumNativeEntity();
        $e1->setColor(ColorsBackedEnum::Red);
        $e1->save();

        $e2 = new EnumNativeEntity();
        $e2->setColor(ColorsBackedEnum::Blue);
        $e2->save();

        $e3 = new EnumNativeEntity();
        $e3->setColor(ColorsBackedEnum::Red);
        $e3->save();

        $count = EnumNativeEntityQuery::create()
            ->filterByColor('red')
            ->count();
        $this->assertEquals(2, $count);

        $count = EnumNativeEntityQuery::create()
            ->filterByColor('blue')
            ->count();
        $this->assertEquals(1, $count);
    }
}
