<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Platform;

use Propel\Generator\Builder\Util\SchemaReader;
use Propel\Generator\Model\Column;
use Propel\Generator\Model\Diff\ColumnComparator;
use Propel\Generator\Model\Table;
use Propel\Generator\Platform\PgsqlPlatform;
use Propel\Generator\Platform\PlatformInterface;

class PgsqlPlatformMigrationTest extends PlatformMigrationTestProvider
{
    /**
     * Get the Platform object for this class
     *
     * @return \Propel\Generator\Platform\PgsqlPlatform
     */
    protected static function getPlatform(): PlatformInterface
    {
        return new PgsqlPlatform();
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetModifyDatabaseDDL')]
    public function testGetModifyDatabaseDDL($databaseDiff)
    {
        $expected = <<<END

BEGIN;

DROP TABLE IF EXISTS "foo1" CASCADE;

ALTER TABLE "foo3" RENAME TO "foo4";

CREATE TABLE "foo5"
(
    "id" serial NOT NULL,
    "lkdjfsh" INTEGER,
    "dfgdsgf" TEXT,
    PRIMARY KEY ("id")
);

ALTER TABLE "foo2" RENAME COLUMN "bar" TO "bar1";

ALTER TABLE "foo2"

  ALTER COLUMN "baz" DROP NOT NULL,

  ADD "baz3" TEXT;

COMMIT;

END;
        $this->assertEquals($expected, static::getPlatform()->getModifyDatabaseDDL($databaseDiff));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetRenameTableDDL')]
    public function testGetRenameTableDDL($fromName, $toName)
    {
        $expected = '
ALTER TABLE "foo1" RENAME TO "foo2";
';
        $this->assertEquals($expected, static::getPlatform()->getRenameTableDDL($fromName, $toName));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetModifyTableDDL')]
    public function testGetModifyTableDDL($tableDiff)
    {
        $expected = <<<END

ALTER TABLE "foo" DROP CONSTRAINT "foo1_fk_2";

ALTER TABLE "foo" DROP CONSTRAINT "foo1_fk_1";

DROP INDEX "bar_baz_fk";

DROP INDEX "bar_fk";

ALTER TABLE "foo" RENAME COLUMN "bar" TO "bar1";

ALTER TABLE "foo"

  ALTER COLUMN "baz" SET DEFAULT 'pdf;jpg;png;doc;docx;xls;xlsx;txt',

  ALTER COLUMN "baz" DROP NOT NULL,

  ADD "baz3" TEXT;

CREATE INDEX "bar_fk" ON "foo" ("bar1");

CREATE INDEX "baz_fk" ON "foo" ("baz3");

ALTER TABLE "foo" ADD CONSTRAINT "foo1_fk_1"
    FOREIGN KEY ("bar1")
    REFERENCES "foo2" ("bar");

END;
        $this->assertEquals($expected, static::getPlatform()->getModifyTableDDL($tableDiff));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetModifyTableColumnsDDL')]
    public function testGetModifyTableColumnsDDL($tableDiff)
    {
        $expected = <<<END

ALTER TABLE "foo" RENAME COLUMN "bar" TO "bar1";

ALTER TABLE "foo" ALTER COLUMN "baz" DROP NOT NULL;

ALTER TABLE "foo" ADD "baz3" TEXT;

END;
        $this->assertEquals($expected, static::getPlatform()->getModifyTableColumnsDDL($tableDiff));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetModifyTablePrimaryKeysDDL')]
    public function testGetModifyTablePrimaryKeysDDL($tableDiff)
    {
        $expected = <<<END

ALTER TABLE "foo" DROP CONSTRAINT "foo_pkey";

ALTER TABLE "foo" ADD PRIMARY KEY ("id","bar");

END;
        $this->assertEquals($expected, static::getPlatform()->getModifyTablePrimaryKeyDDL($tableDiff));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetModifyTableIndicesDDL')]
    public function testGetModifyTableIndicesDDL($tableDiff)
    {
        $expected = <<<END

DROP INDEX "bar_fk";

ALTER TABLE "foo" DROP CONSTRAINT "bax_unique";

CREATE INDEX "baz_fk" ON "foo" ("baz");

ALTER TABLE "foo" ADD CONSTRAINT "bax_bay_unique" UNIQUE ("bax","bay");

DROP INDEX "bar_baz_fk";

CREATE INDEX "bar_baz_fk" ON "foo" ("id","bar","baz");

END;
        $this->assertEquals($expected, static::getPlatform()->getModifyTableIndicesDDL($tableDiff));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetModifyTableForeignKeysDDL')]
    public function testGetModifyTableForeignKeysDDL($tableDiff)
    {
        $expected = <<<END

ALTER TABLE "foo1" DROP CONSTRAINT "foo1_fk_1";

ALTER TABLE "foo1" ADD CONSTRAINT "foo1_fk_3"
    FOREIGN KEY ("baz")
    REFERENCES "foo2" ("baz");

ALTER TABLE "foo1" DROP CONSTRAINT "foo1_fk_2";

ALTER TABLE "foo1" ADD CONSTRAINT "foo1_fk_2"
    FOREIGN KEY ("bar","id")
    REFERENCES "foo2" ("bar","id");

END;
        $this->assertEquals($expected, static::getPlatform()->getModifyTableForeignKeysDDL($tableDiff));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetModifyTableForeignKeysSkipSqlDDL')]
    public function testGetModifyTableForeignKeysSkipSqlDDL($tableDiff)
    {
        $expected = <<<END

ALTER TABLE "foo1" DROP CONSTRAINT "foo1_fk_1";

END;
        $this->assertEquals($expected, static::getPlatform()->getModifyTableForeignKeysDDL($tableDiff));
        $expected = <<<END

ALTER TABLE "foo1" ADD CONSTRAINT "foo1_fk_1"
    FOREIGN KEY ("bar")
    REFERENCES "foo2" ("bar");

END;
        $this->assertEquals($expected, static::getPlatform()->getModifyTableForeignKeysDDL($tableDiff->getReverseDiff()));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetModifyTableForeignKeysSkipSql2DDL')]
    public function testGetModifyTableForeignKeysSkipSql2DDL($tableDiff)
    {
        $expected = '';
        $this->assertEquals($expected, static::getPlatform()->getModifyTableForeignKeysDDL($tableDiff));
        $expected = '';
        $this->assertEquals($expected, static::getPlatform()->getModifyTableForeignKeysDDL($tableDiff->getReverseDiff()));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetRemoveColumnDDL')]
    public function testGetRemoveColumnDDL($column)
    {
        $expected = '
ALTER TABLE "foo" DROP COLUMN "bar";
';
        $this->assertEquals($expected, static::getPlatform()->getRemoveColumnDDL($column));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetRenameColumnDDL')]
    public function testGetRenameColumnDDL($fromColumn, $toColumn)
    {
        $expected = '
ALTER TABLE "foo" RENAME COLUMN "bar1" TO "bar2";
';
        $this->assertEquals($expected, static::getPlatform()->getRenameColumnDDL($fromColumn, $toColumn));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetModifyColumnDDL')]
    public function testGetModifyColumnDDL($columnDiff)
    {
        $expected = '
ALTER TABLE "foo" ALTER COLUMN "bar" TYPE DOUBLE PRECISION;
';
        $this->assertEquals($expected, static::getPlatform()->getModifyColumnDDL($columnDiff));
    }

    /**
     * @return void
     */
    public function testGetModifyColumnDDLWithChangedTypeAndDefault()
    {
        $t1 = new Table('foo');
        $t1->setIdentifierQuoting(true);
        $c1 = new Column('bar');
        $c1->getDomain()->copy(static::getPlatform()->getDomainForType('DOUBLE'));
        $c1->getDomain()->replaceSize(2);
        $t1->addColumn($c1);
        $t2 = new Table('foo');
        $t2->setIdentifierQuoting(true);
        $c2 = new Column('bar');
        $c2->getDomain()->copy(static::getPlatform()->getDomainForType('DOUBLE'));
        $c2->getDomain()->replaceSize(3);
        $c2->getDomain()->createDefaultValue(-100);
        $t2->addColumn($c2);
        $columnDiff = ColumnComparator::computeDiff($c1, $c2);
        $expected = <<<END

ALTER TABLE "foo" ALTER COLUMN "bar" TYPE DOUBLE PRECISION;

ALTER TABLE "foo" ALTER COLUMN "bar" SET DEFAULT -100;

END;
        $this->assertEquals($expected, static::getPlatform()->getModifyColumnDDL($columnDiff));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetModifyColumnsDDL')]
    public function testGetModifyColumnsDDL($columnDiffs)
    {
        $expected = <<<END

ALTER TABLE "foo" ALTER COLUMN "bar1" TYPE DOUBLE PRECISION;

ALTER TABLE "foo" ALTER COLUMN "bar2" SET NOT NULL;

END;
        $this->assertEquals($expected, static::getPlatform()->getModifyColumnsDDL($columnDiffs));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetAddColumnDDL')]
    public function testGetAddColumnDDL($column)
    {
        $expected = '
ALTER TABLE "foo" ADD "bar" INTEGER;
';
        $this->assertEquals($expected, static::getPlatform()->getAddColumnDDL($column));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetAddColumnsDDL')]
    public function testGetAddColumnsDDL($columns)
    {
        $expected = <<<END

ALTER TABLE "foo" ADD "bar1" INTEGER;

ALTER TABLE "foo" ADD "bar2" DOUBLE PRECISION DEFAULT -1 NOT NULL;

END;
        $this->assertEquals($expected, static::getPlatform()->getAddColumnsDDL($columns));
    }

    /**
     * @return void
     */
    public function testGetModifyColumnDDLWithVarcharWithoutSize()
    {
        $t1 = new Table('foo');
        $t1->setIdentifierQuoting(true);
        $c1 = new Column('bar');
        $c1->setTable($t1);
        $c1->getDomain()->copy(static::getPlatform()->getDomainForType('VARCHAR'));
        $c1->getDomain()->replaceSize(null);
        $c1->getDomain()->replaceScale(null);
        $t1->addColumn($c1);

        $schema = <<<EOF
<database name="test" identifierQuoting="true">
    <table name="foo">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <column name="bar" type="VARCHAR"/>
    </table>
</database>
EOF;

        $table = $this->getDatabaseFromSchema($schema)->getTable('foo');
        $c2 = $table->getColumn('bar');
        $columnDiff = ColumnComparator::computeDiff($c1, $c2);
        $expected = false;
        $this->assertSame($expected, $columnDiff);
    }

    /**
     * @return void
     */
    public function testGetModifyColumnDDLWithVarcharWithoutSizeAndPlatform()
    {
        $t1 = new Table('foo');
        $t1->setIdentifierQuoting(true);
        $c1 = new Column('bar');
        $c1->setTable($t1);
        $c1->getDomain()->copy(static::getPlatform()->getDomainForType('VARCHAR'));
        $c1->getDomain()->replaceSize(null);
        $c1->getDomain()->replaceScale(null);
        $t1->addColumn($c1);

        $schema = <<<EOF
<database name="test" identifierQuoting="true">
    <table name="foo">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <column name="bar"/>
    </table>
</database>
EOF;

        $xtad = new SchemaReader(null);
        $appData = $xtad->parseString($schema);
        $db = $appData->getDatabase();
        $table = $db->getTable('foo');
        $c2 = $table->getColumn('bar');
        $columnDiff = ColumnComparator::computeDiff($c1, $c2);
        $expected = false;
        $this->assertSame($expected, $columnDiff);
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetModifyColumnRemoveDefaultValueDDL')]
    public function testGetModifyColumnRemoveDefaultValueDDL($columnDiffs)
    {
        $expected = <<<EOF

ALTER TABLE "test" ALTER COLUMN "test" DROP DEFAULT;

EOF;
        $this->assertEquals($expected, static::getPlatform()->getModifyColumnDDL($columnDiffs));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetModifyTableForeignKeysSkipSql3DDL')]
    public function testGetModifyTableForeignKeysSkipSql3DDL($databaseDiff)
    {
        $this->assertFalse($databaseDiff);
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetModifyTableForeignKeysSkipSql4DDL')]
    public function testGetModifyTableForeignKeysSkipSql4DDL($databaseDiff)
    {
        $this->assertFalse($databaseDiff);
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestMigrateToUUIDColumn')]
    public function testMigrateToUUIDColumn($tableDiff)
    {
        $expected = <<<END

ALTER TABLE "foo" ALTER COLUMN "id" TYPE uuid USING id::uuid;

ALTER TABLE "foo" ALTER COLUMN "id" SET DEFAULT vendor_specific_uuid_generator_function();

END;
        $this->assertEquals($expected, static::getPlatform()->getModifyTableColumnsDDL($tableDiff));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestMigrateToUuidBinColumn')]
    public function testMigrateToUuidBinColumn($tableDiff)
    {
        $expected = <<<END

ALTER TABLE "foo" ALTER COLUMN "id" TYPE BYTEA USING NULL;

ALTER TABLE "foo" ALTER COLUMN "id" SET DEFAULT vendor_specific_uuid_generator_function();

END;
        $this->assertEquals($expected, static::getPlatform()->getModifyTableColumnsDDL($tableDiff));
    }
}
