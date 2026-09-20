<?php

namespace Propel\Tests\Runtime\TypeTests;

use PHPUnit\Framework\Attributes\DataProvider;
use Propel\Generator\Util\QuickBuilder;
use Propel\Runtime\Perpl;
use Propel\Tests\Helpers\Bookstore\BookstoreTestBase;
use Propel\Tests\TestCase;
use BigintTypeTest\BigintEntity;

/**
 * @group database
 * @group mysql
 */
class BigintTypeTest extends BookstoreTestBase
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
        $connection = Perpl::getServiceContainer()->getConnection('bookstore');
        $connection->exec('DROP TABLE IF EXISTS bigint_entity;');

        parent::tearDownAfterClass();
    }

    /**
     * @return void
     */
    public static function createSchema(): void
    {
        $schema = <<<XML
<database name="bookstore" namespace="BigintTypeTest">
    <table name="bigint_entity">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <column name="le_bigint" type="BIGINT"/>
        <column name="le_unsigned_bigint" sqlType="bigint unsigned"/>
    </table>
</database>
XML;
        $builder = new QuickBuilder();
        $builder->setSchemaXml($schema);
        $builder->setVfs(false);
        $builder->setPlatform(TestCase::getPlatform());
        $connection = Perpl::getServiceContainer()->getConnection('bookstore');
        $connection->exec('DROP TABLE IF EXISTS bigint_entity;');
        $builder->buildAndRunSql($connection);
        $builder->buildClasses();
    }

    protected function fillBigintEntity(int|string|null $value, $entity = null)
    {
        $entity ??= new BigintEntity();
        $entity
            ->setBigint($value)
            ->setBigintUnsigned($value);
        $entity->save();
        $entity->reload();

        return $entity;
    }

    /**
     * @return void
     */
    #[DataProvider('BigintValueDataProvider')]
    public function testBigintValues(string $description, string|int $value, int $expectedBigintValue, string $expectedBigintUnsigendValue)
    {
        $o = (new BigintEntity())
            ->setLeBigint($value)
            ->setLeUnsignedBigint($value);
        $o->save();
        $o->reload();

        $this->assertSame($expectedBigintValue, $o->getLeBigint(), "Bigint with $description");
        $this->assertSame($expectedBigintUnsigendValue, $o->getLeUnsignedBigint(), "Unsigned Bigint with $description");
    }

    public static function BigintValueDataProvider(): array
    {
        $maxUnsignedBigint = '18446744073709551615';

        return [
            ['max int', PHP_INT_MAX, PHP_INT_MAX, (string)PHP_INT_MAX], // PHP_INT_MAX = 2^63 - 1 = 9223372036854775807
            ['overflow', $maxUnsignedBigint, PHP_INT_MAX, $maxUnsignedBigint],
        ];
    }
}
