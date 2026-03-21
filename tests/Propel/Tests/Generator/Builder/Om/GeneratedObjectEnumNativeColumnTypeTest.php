<?php

declare(strict_types = 1);

namespace Propel\Tests\Generator\Builder\Om;

use EnumNativeBackedEntity;
use EnumNativeBackedEntityQuery;
use EnumNativeUnitEntity;
use EnumNativeUnitEntityQuery;
use Map\EnumNativeBackedEntityTableMap;
use Map\EnumNativeUnitEntityTableMap;
use Propel\Generator\Platform\PgsqlPlatform;
use Propel\Generator\Util\QuickBuilder;
use Propel\Runtime\Adapter\Pdo\PgsqlAdapter;
use Propel\Tests\Helpers\ColorsBackedEnum;
use Propel\Tests\Helpers\ColorsBasicEnum;
use Propel\Tests\TestCase;

/**
 * Tests runtime behavior of ENUM_NATIVE columns with PHP enums on PostgreSQL.
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

        $backedEnumClass = ColorsBackedEnum::class;
        $unitEnumClass = ColorsBasicEnum::class;
        $schema = <<<EOF
<database name="enum_native_test">
    <table name="enum_native_backed_entity">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <column name="color" type="ENUM_NATIVE" valueSet="red,blue,yellow" sqlType="enum_native_backed_color" phpType="$backedEnumClass"/>
        <column name="nullable_color" type="ENUM_NATIVE" valueSet="red,blue,yellow" sqlType="enum_native_backed_color" phpType="$backedEnumClass" required="false"/>
    </table>
    <table name="enum_native_unit_entity">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <column name="color" type="ENUM_NATIVE" valueSet="Red,Blue,Yellow" sqlType="enum_native_unit_color" phpType="$unitEnumClass"/>
        <column name="nullable_color" type="ENUM_NATIVE" valueSet="Red,Blue,Yellow" sqlType="enum_native_unit_color" phpType="$unitEnumClass" required="false"/>
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

    // --- BackedEnum tests ---

    public function testBackedEnumInsertAndHydrate(): void
    {
        $entity = new EnumNativeBackedEntity();
        $entity->setColor(ColorsBackedEnum::Red);
        $entity->save();

        EnumNativeBackedEntityTableMap::clearInstancePool();

        $fetched = EnumNativeBackedEntityQuery::create()->findOneById($entity->getId());
        $this->assertNotNull($fetched);
        $this->assertSame(ColorsBackedEnum::Red, $fetched->getColor());
    }

    public function testBackedEnumUpdate(): void
    {
        $entity = new EnumNativeBackedEntity();
        $entity->setColor(ColorsBackedEnum::Blue);
        $entity->save();

        $entity->setColor(ColorsBackedEnum::Yellow);
        $entity->save();

        EnumNativeBackedEntityTableMap::clearInstancePool();

        $fetched = EnumNativeBackedEntityQuery::create()->findOneById($entity->getId());
        $this->assertSame(ColorsBackedEnum::Yellow, $fetched->getColor());
    }

    public function testBackedEnumNullable(): void
    {
        $entity = new EnumNativeBackedEntity();
        $entity->setColor(ColorsBackedEnum::Red);
        $entity->setNullableColor(null);
        $entity->save();

        EnumNativeBackedEntityTableMap::clearInstancePool();

        $fetched = EnumNativeBackedEntityQuery::create()->findOneById($entity->getId());
        $this->assertNull($fetched->getNullableColor());
    }

    public function testBackedEnumFilter(): void
    {
        EnumNativeBackedEntityQuery::create()->deleteAll();

        $e1 = new EnumNativeBackedEntity();
        $e1->setColor(ColorsBackedEnum::Red);
        $e1->save();

        $e2 = new EnumNativeBackedEntity();
        $e2->setColor(ColorsBackedEnum::Blue);
        $e2->save();

        $e3 = new EnumNativeBackedEntity();
        $e3->setColor(ColorsBackedEnum::Red);
        $e3->save();

        $this->assertEquals(2, EnumNativeBackedEntityQuery::create()->filterByColor('red')->count());
        $this->assertEquals(1, EnumNativeBackedEntityQuery::create()->filterByColor('blue')->count());
    }

    // --- UnitEnum tests ---

    public function testUnitEnumInsertAndHydrate(): void
    {
        $entity = new EnumNativeUnitEntity();
        $entity->setColor(ColorsBasicEnum::Red);
        $entity->save();

        EnumNativeUnitEntityTableMap::clearInstancePool();

        $fetched = EnumNativeUnitEntityQuery::create()->findOneById($entity->getId());
        $this->assertNotNull($fetched);
        $this->assertSame(ColorsBasicEnum::Red, $fetched->getColor());
    }

    public function testUnitEnumUpdate(): void
    {
        $entity = new EnumNativeUnitEntity();
        $entity->setColor(ColorsBasicEnum::Blue);
        $entity->save();

        $entity->setColor(ColorsBasicEnum::Yellow);
        $entity->save();

        EnumNativeUnitEntityTableMap::clearInstancePool();

        $fetched = EnumNativeUnitEntityQuery::create()->findOneById($entity->getId());
        $this->assertSame(ColorsBasicEnum::Yellow, $fetched->getColor());
    }

    public function testUnitEnumNullable(): void
    {
        $entity = new EnumNativeUnitEntity();
        $entity->setColor(ColorsBasicEnum::Red);
        $entity->setNullableColor(null);
        $entity->save();

        EnumNativeUnitEntityTableMap::clearInstancePool();

        $fetched = EnumNativeUnitEntityQuery::create()->findOneById($entity->getId());
        $this->assertNull($fetched->getNullableColor());
    }

    public function testUnitEnumFilter(): void
    {
        EnumNativeUnitEntityQuery::create()->deleteAll();

        $e1 = new EnumNativeUnitEntity();
        $e1->setColor(ColorsBasicEnum::Red);
        $e1->save();

        $e2 = new EnumNativeUnitEntity();
        $e2->setColor(ColorsBasicEnum::Blue);
        $e2->save();

        $e3 = new EnumNativeUnitEntity();
        $e3->setColor(ColorsBasicEnum::Red);
        $e3->save();

        $this->assertEquals(2, EnumNativeUnitEntityQuery::create()->filterByColor('Red')->count());
        $this->assertEquals(1, EnumNativeUnitEntityQuery::create()->filterByColor('Blue')->count());
    }

    // Note: invalid enum value test is not possible with ENUM_NATIVE because
    // PostgreSQL enforces valid values at the database level. The PropelException
    // for unknown UnitEnum cases applies to non-native columns (e.g., VARCHAR with phpType).
}
