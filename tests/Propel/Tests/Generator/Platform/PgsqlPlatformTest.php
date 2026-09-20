<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Platform;

use PHPUnit\Framework\Attributes\DataProvider;
use Propel\Generator\Model\Column;
use Propel\Generator\Model\Database;
use Propel\Generator\Model\Datatype\ColumnType;
use Propel\Generator\Model\IdMethod;
use Propel\Generator\Model\IdMethodParameter;
use Propel\Generator\Model\Table;
use Propel\Generator\Model\Unique;
use Propel\Generator\Platform\PgsqlPlatform;
use Propel\Generator\Platform\PlatformInterface;

class PgsqlPlatformTest extends PlatformTestProvider
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
    public function testGetSequenceNameDefault()
    {
        $platform = static::getPlatform();
        $table = new Table('foo');
        $table->setIdMethod(IdMethod::SEQUENCE);
        $col = new Column('bar');
        $col->setTypeMapping($platform->getColumnTypeMapping(ColumnType::INTEGER));
        $col->setAutoIncrement(true);
        $table->addColumn($col);
        $expected = 'foo_bar_seq';
        $this->assertEquals($expected, $platform->buildDefaultTableIdSequenceName($table));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetAddTablesDDL')]
    public function testGetAddTablesDDL($schema)
    {
        $database = $this->getDatabaseFromSchema($schema);
        $expected = <<<EOF

BEGIN;

-- ---------------------------------------------------------------------
-- book
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS "book" CASCADE;

DROP SEQUENCE IF EXISTS "book_id_seq";

CREATE SEQUENCE IF NOT EXISTS "book_id_seq";

CREATE TABLE "book"
(
    "id" INTEGER DEFAULT nextval('book_id_seq'::regclass) NOT NULL,
    "title" VARCHAR(255) NOT NULL,
    "author_id" INTEGER,
    PRIMARY KEY ("id")
);

CREATE INDEX "book_i_639136" ON "book" ("title");

-- ---------------------------------------------------------------------
-- author
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS "author" CASCADE;

DROP SEQUENCE IF EXISTS "author_id_seq";

CREATE SEQUENCE IF NOT EXISTS "author_id_seq";

CREATE TABLE "author"
(
    "id" INTEGER DEFAULT nextval('author_id_seq'::regclass) NOT NULL,
    "first_name" VARCHAR(100),
    "last_name" VARCHAR(100),
    PRIMARY KEY ("id")
);

ALTER TABLE "book" ADD CONSTRAINT "book_fk_ea464c"
    FOREIGN KEY ("author_id")
    REFERENCES "author" ("id");

COMMIT;

EOF;
        $this->assertEquals($expected, static::getPlatform()->buildAddTablesDdl($database));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetAddTablesSkipSQLDDL')]
    public function testGetAddTablesDDLSkipSQL($schema)
    {
        $database = $this->getDatabaseFromSchema($schema);
        $expected = '';
        $this->assertEquals($expected, static::getPlatform()->buildAddTablesDdl($database));
    }

    /**
     * @return void
     */
    public function testGetAddTablesDDLSchemasVendor()
    {
        $schema = <<<EOF
<database name="test" identifierQuoting="true">
    <table name="table1">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <vendor type="pgsql">
            <parameter name="schema" value="Woopah"/>
        </vendor>
    </table>
    <table name="table2">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
    </table>
    <table name="table3">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <vendor type="pgsql">
            <parameter name="schema" value="Yipee"/>
        </vendor>
    </table>
</database>
EOF;
        $database = $this->getDatabaseFromSchema($schema);
        $expected = <<<EOF

BEGIN;

CREATE SCHEMA "Woopah";

CREATE SCHEMA "Yipee";

-- ---------------------------------------------------------------------
-- table1
-- ---------------------------------------------------------------------

SET search_path TO "Woopah";

DROP TABLE IF EXISTS "table1" CASCADE;

DROP SEQUENCE IF EXISTS "table1_id_seq";

SET search_path TO public;

SET search_path TO "Woopah";

CREATE SEQUENCE IF NOT EXISTS "table1_id_seq";

CREATE TABLE "table1"
(
    "id" INTEGER DEFAULT nextval('table1_id_seq'::regclass) NOT NULL,
    PRIMARY KEY ("id")
);

SET search_path TO public;

-- ---------------------------------------------------------------------
-- table2
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS "table2" CASCADE;

DROP SEQUENCE IF EXISTS "table2_id_seq";

CREATE SEQUENCE IF NOT EXISTS "table2_id_seq";

CREATE TABLE "table2"
(
    "id" INTEGER DEFAULT nextval('table2_id_seq'::regclass) NOT NULL,
    PRIMARY KEY ("id")
);

-- ---------------------------------------------------------------------
-- table3
-- ---------------------------------------------------------------------

SET search_path TO "Yipee";

DROP TABLE IF EXISTS "table3" CASCADE;

DROP SEQUENCE IF EXISTS "table3_id_seq";

SET search_path TO public;

SET search_path TO "Yipee";

CREATE SEQUENCE IF NOT EXISTS "table3_id_seq";

CREATE TABLE "table3"
(
    "id" INTEGER DEFAULT nextval('table3_id_seq'::regclass) NOT NULL,
    PRIMARY KEY ("id")
);

SET search_path TO public;

COMMIT;

EOF;
        $this->assertEquals($expected, static::getPlatform()->buildAddTablesDdl($database));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetAddTablesDDLSchema')]
    public function testGetAddTablesDDLSchemas($schema)
    {
        $database = $this->getDatabaseFromSchema($schema);
        $expected = <<<EOF

BEGIN;

-- ---------------------------------------------------------------------
-- x.book
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS "x"."book" CASCADE;

DROP SEQUENCE IF EXISTS "x"."book_id_seq";

CREATE SEQUENCE IF NOT EXISTS "x"."book_id_seq";

CREATE TABLE "x"."book"
(
    "id" INTEGER DEFAULT nextval('x.book_id_seq'::regclass) NOT NULL,
    "title" VARCHAR(255) NOT NULL,
    "author_id" INTEGER,
    PRIMARY KEY ("id")
);

CREATE INDEX "book_i_639136" ON "x"."book" ("title");

-- ---------------------------------------------------------------------
-- y.author
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS "y"."author" CASCADE;

DROP SEQUENCE IF EXISTS "y"."author_id_seq";

CREATE SEQUENCE IF NOT EXISTS "y"."author_id_seq";

CREATE TABLE "y"."author"
(
    "id" INTEGER DEFAULT nextval('y.author_id_seq'::regclass) NOT NULL,
    "first_name" VARCHAR(100),
    "last_name" VARCHAR(100),
    PRIMARY KEY ("id")
);

-- ---------------------------------------------------------------------
-- x.book_summary
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS "x"."book_summary" CASCADE;

DROP SEQUENCE IF EXISTS "x"."book_summary_id_seq";

CREATE SEQUENCE IF NOT EXISTS "x"."book_summary_id_seq";

CREATE TABLE "x"."book_summary"
(
    "id" INTEGER DEFAULT nextval('x.book_summary_id_seq'::regclass) NOT NULL,
    "book_id" INTEGER NOT NULL,
    "summary" TEXT NOT NULL,
    PRIMARY KEY ("id")
);

ALTER TABLE "x"."book" ADD CONSTRAINT "book_fk_4444ca"
    FOREIGN KEY ("author_id")
    REFERENCES "y"."author" ("id");

ALTER TABLE "x"."book_summary" ADD CONSTRAINT "book_summary_fk_23450f"
    FOREIGN KEY ("book_id")
    REFERENCES "x"."book" ("id")
    ON DELETE CASCADE;

COMMIT;

EOF;
        $this->assertEquals($expected, static::getPlatform()->buildAddTablesDdl($database));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetAddTableDDLSimplePK')]
    public function testGetAddTableDDLSimplePK($schema)
    {
        $table = $this->getTableFromSchema($schema);
        $expected = <<<EOF

CREATE SEQUENCE IF NOT EXISTS "foo_id_seq";

CREATE TABLE "foo"
(
    "id" INTEGER DEFAULT nextval('foo_id_seq'::regclass) NOT NULL,
    "bar" VARCHAR(255) NOT NULL,
    PRIMARY KEY ("id")
);

COMMENT ON TABLE "foo" IS 'This is foo table';

EOF;
        $this->assertEquals($expected, static::getPlatform()->buildAddTableDdl($table));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetAddTableDDLCompositePK')]
    public function testGetAddTableDDLCompositePK($schema)
    {
        $table = $this->getTableFromSchema($schema);
        $expected = <<<EOF

CREATE TABLE "foo"
(
    "foo" INTEGER NOT NULL,
    "bar" INTEGER NOT NULL,
    "baz" VARCHAR(255) NOT NULL,
    PRIMARY KEY ("foo","bar")
);

EOF;
        $this->assertEquals($expected, static::getPlatform()->buildAddTableDdl($table));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetAddTableDDLUniqueIndex')]
    public function testGetAddTableDDLUniqueIndex($schema)
    {
        $table = $this->getTableFromSchema($schema);
        $expected = <<<EOF

CREATE SEQUENCE IF NOT EXISTS "foo_id_seq";

CREATE TABLE "foo"
(
    "id" INTEGER DEFAULT nextval('foo_id_seq'::regclass) NOT NULL,
    "bar" INTEGER,
    PRIMARY KEY ("id"),
    CONSTRAINT "foo_u_14f552" UNIQUE ("bar")
);

EOF;
        $this->assertEquals($expected, static::getPlatform()->buildAddTableDdl($table));
    }

    /**
     * @return void
     */
    public function testGetAddTableDDLSchemaVendor()
    {
        $schema = <<<EOF
<database name="test" identifierQuoting="true">
    <table name="foo">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <vendor type="pgsql">
            <parameter name="schema" value="Woopah"/>
        </vendor>
    </table>
</database>
EOF;
        $table = $this->getTableFromSchema($schema);
        $expected = <<<EOF

SET search_path TO "Woopah";

CREATE SEQUENCE IF NOT EXISTS "foo_id_seq";

CREATE TABLE "foo"
(
    "id" INTEGER DEFAULT nextval('foo_id_seq'::regclass) NOT NULL,
    PRIMARY KEY ("id")
);

SET search_path TO public;

EOF;
        $this->assertEquals($expected, static::getPlatform()->buildAddTableDdl($table));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetAddTableDDLSchema')]
    public function testGetAddTableDDLSchema($schema)
    {
        $table = $this->getTableFromSchema($schema, 'Woopah.foo');
        $expected = <<<EOF

CREATE SEQUENCE IF NOT EXISTS "woopah"."foo_id_seq";

CREATE TABLE "Woopah"."foo"
(
    "id" INTEGER DEFAULT nextval('Woopah.foo_id_seq'::regclass) NOT NULL,
    "bar" INTEGER,
    PRIMARY KEY ("id")
);

EOF;
        $this->assertEquals($expected, static::getPlatform()->buildAddTableDdl($table));
    }

    /**
     * @return void
     */
    public function testGetAddTableDDLSequence()
    {
        $schema = <<<EOF
<database name="test" identifierQuoting="true">
    <table name="foo" idMethod="sequence">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <id-method-parameter value="my_custom_sequence_name"/>
    </table>
</database>
EOF;
        $table = $this->getTableFromSchema($schema);
        $expected = <<<EOF

CREATE SEQUENCE IF NOT EXISTS "my_custom_sequence_name";

CREATE TABLE "foo"
(
    "id" INTEGER DEFAULT nextval('foo_id_seq'::regclass) NOT NULL,
    PRIMARY KEY ("id")
);

EOF;
        $this->assertEquals($expected, static::getPlatform()->buildAddTableDdl($table));
    }

    /**
     * @return void
     */
    public function testGetAddTableDDLColumnComments()
    {
        $schema = <<<EOF
<database name="test" identifierQuoting="true">
    <table name="foo">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true" description="identifier column"/>
        <column name="bar" type="INTEGER" description="your name here"/>
    </table>
</database>
EOF;
        $table = $this->getTableFromSchema($schema);
        $expected = <<<EOF

CREATE SEQUENCE IF NOT EXISTS "foo_id_seq";

CREATE TABLE "foo"
(
    "id" INTEGER DEFAULT nextval('foo_id_seq'::regclass) NOT NULL,
    "bar" INTEGER,
    PRIMARY KEY ("id")
);

COMMENT ON COLUMN "foo"."id" IS 'identifier column';

COMMENT ON COLUMN "foo"."bar" IS 'your name here';

EOF;
        $this->assertEquals($expected, static::getPlatform()->buildAddTableDdl($table));
    }

    /**
     * @return void
     */
    public function testGetDropTableDDL()
    {
        $table = new Table('foo');
        $expected = '
DROP TABLE IF EXISTS "foo" CASCADE;
';
        $this->assertEquals($expected, static::getPlatform()->buildDropTableDdl($table));
    }

    /**
     * @return void
     */
    public function testGetDropTableDDLSchemaVendor()
    {
        $schema = <<<EOF
<database name="test">
    <table name="foo">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <vendor type="pgsql">
            <parameter name="schema" value="Woopah"/>
        </vendor>
    </table>
</database>
EOF;
        $table = $this->getTableFromSchema($schema);
        $expected = <<<EOF

SET search_path TO "Woopah";

DROP TABLE IF EXISTS "foo" CASCADE;

DROP SEQUENCE IF EXISTS "foo_id_seq";

SET search_path TO public;

EOF;
        $this->assertEquals($expected, static::getPlatform()->buildDropTableDdl($table));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetAddTableDDLSchema')]
    public function testGetDropTableDDLSchema($schema)
    {
        $table = $this->getTableFromSchema($schema, 'Woopah.foo');
        $expected = <<<EOF

DROP TABLE IF EXISTS "Woopah"."foo" CASCADE;

DROP SEQUENCE IF EXISTS "woopah"."foo_id_seq";

EOF;
        $this->assertEquals($expected, static::getPlatform()->buildDropTableDdl($table));
    }

    /**
     * @return void
     */
    public function testGetDropTableWithSequenceDDL()
    {
        $table = new Table('foo');
        $idMethodParameter = new IdMethodParameter();
        $idMethodParameter->setValue('foo_sequence');
        $table->addIdMethodParameter($idMethodParameter);
        $table->setIdMethod(IdMethod::SEQUENCE);
        $expected = '
DROP TABLE IF EXISTS "foo" CASCADE;

DROP SEQUENCE IF EXISTS "foo_sequence";
';
        $this->assertEquals($expected, static::getPlatform()->buildDropTableDdl($table));
    }

    /**
     * @return void
     */
    public function testGetColumnDDL()
    {
        $c = new Column('foo');
        $c->setTypeMapping(static::getPlatform()->getColumnTypeMapping(ColumnType::DOUBLE));
        $c->getTypeMapping()->setScaleToValueIfNotNull(2);
        $c->getTypeMapping()->setSizeToValueIfNotNull(3);
        $c->setNotNull(true);
        $c->getTypeMapping()->createDefaultValue(123);
        $expected = '"foo" DOUBLE PRECISION DEFAULT 123 NOT NULL';
        $this->assertEquals($expected, static::getPlatform()->buildColumnDdl($c));
    }

    public static function SerialTypeDataProvider(): array
    {
        return [
            [ColumnType::BIGINT, '"foo" INT8 DEFAULT nextval(\'foo_table_foo_seq\'::regclass)'],
            [ColumnType::SMALLINT, '"foo" INT2 DEFAULT nextval(\'foo_table_foo_seq\'::regclass)'],
            [ColumnType::INTEGER, '"foo" INTEGER DEFAULT nextval(\'foo_table_foo_seq\'::regclass)'],
        ];
    }
    /**
     * @return void
     */
    #[DataProvider('SerialTypeDataProvider')]
    public function testGetColumnDDLAutoIncrement(ColumnType $columnType, string $expected)
    {
        $platform = static::getPlatform();

        $database = new Database();
        $database->setPlatform($platform);

        $table = new Table('foo_table');
        $table->setIdMethod(IdMethod::NATIVE);
        $database->addTable($table);

        $column = new Column('foo');
        $column->setTypeMapping($platform->getColumnTypeMapping($columnType));
        $column->setAutoIncrement(true);
        $table->addColumn($column);

        $this->assertEquals($expected, $platform->buildColumnDdl($column));
    }

    /**
     * @return void
     */
    public function testGetColumnDDLCustomSqlType()
    {
        $column = new Column('foo');
        $column->setTypeMapping(static::getPlatform()->getColumnTypeMapping(ColumnType::DOUBLE));
        $column->getTypeMapping()->setScaleToValueIfNotNull(2);
        $column->getTypeMapping()->setSizeToValueIfNotNull(3);
        $column->setNotNull(true);
        $column->getTypeMapping()->createDefaultValue(123);
        $column->getTypeMapping()->setSqlType('DECIMAL(5,6)');
        $expected = '"foo" DECIMAL(5,6) DEFAULT 123 NOT NULL';
        $this->assertEquals($expected, static::getPlatform()->buildColumnDdl($column));
    }

    /**
     * @return void
     */
    public function testGetPrimaryKeyDDLSimpleKey()
    {
        $table = new Table('foo');
        $column = new Column('bar');
        $column->setPrimaryKey(true);
        $table->addColumn($column);
        $expected = 'PRIMARY KEY ("bar")';
        $this->assertEquals($expected, static::getPlatform()->buildPrimaryKeyDdl($table));
    }

    /**
     * @return void
     */
    public function testGetPrimaryKeyDDLCompositeKey()
    {
        $table = new Table('foo');
        $column1 = new Column('bar1');
        $column1->setPrimaryKey(true);
        $table->addColumn($column1);
        $column2 = new Column('bar2');
        $column2->setPrimaryKey(true);
        $table->addColumn($column2);
        $expected = 'PRIMARY KEY ("bar1","bar2")';
        $this->assertEquals($expected, static::getPlatform()->buildPrimaryKeyDdl($table));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestPrimaryKeyDDL')]
    public function testGetDropPrimaryKeyDDL($table)
    {
        $expected = '
ALTER TABLE "foo" DROP CONSTRAINT "foo_pkey";
';
        $this->assertEquals($expected, static::getPlatform()->buildDropPrimaryKeyDdl($table));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestPrimaryKeyDDL')]
    public function testGetAddPrimaryKeyDDL($table)
    {
        $expected = '
ALTER TABLE "foo" ADD PRIMARY KEY ("bar");
';
        $this->assertEquals($expected, static::getPlatform()->buildAddPrimaryKeyDdl($table));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetIndexDDL')]
    public function testAddIndexDDL($index)
    {
        $expected = '
CREATE INDEX "babar" ON "foo" ("bar1","bar2");
';
        $this->assertEquals($expected, static::getPlatform()->buildAddIndexDdl($index));
    }

    /**
     *
     * @param \Propel\Generator\Model\Unique $index
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetUniqueIndexDDL')]
    public function testAddUniqueIndexDDL(Unique $index): void
    {
        $expected = '
ALTER TABLE "foo" ADD CONSTRAINT "babar" UNIQUE ("bar1");
';
        $this->assertEquals($expected, static::getPlatform()->buildAddIndexDdl($index));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetIndicesDDL')]
    public function testAddIndicesDDL($table)
    {
        $expected = '
CREATE INDEX "babar" ON "foo" ("bar1","bar2");

CREATE INDEX "foo_index" ON "foo" ("bar1");
';
        $this->assertEquals($expected, static::getPlatform()->buildAddIndicesDdl($table));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetIndexDDL')]
    public function testDropIndexDDL($index)
    {
        $expected = '
DROP INDEX "babar";
';
        $this->assertEquals($expected, static::getPlatform()->buildDropIndexDdl($index));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetIndexDDL')]
    public function testGetIndexDDL($index)
    {
        $expected = 'INDEX "babar" ("bar1","bar2")';
        $this->assertEquals($expected, static::getPlatform()->buildIndexDdl($index));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetUniqueDDL')]
    public function testGetUniqueDDL($index)
    {
        $expected = 'CONSTRAINT "babar" UNIQUE ("bar1","bar2")';
        $this->assertEquals($expected, static::getPlatform()->buildUniqueDdl($index));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetForeignKeysDDL')]
    public function testGetAddForeignKeysDDL($table)
    {
        $expected = '
ALTER TABLE "foo" ADD CONSTRAINT "foo_bar_fk"
    FOREIGN KEY ("bar_id")
    REFERENCES "bar" ("id")
    ON DELETE CASCADE;

ALTER TABLE "foo" ADD CONSTRAINT "foo_baz_fk"
    FOREIGN KEY ("baz_id")
    REFERENCES "baz" ("id")
    ON DELETE SET NULL;
';
        $this->assertEquals($expected, static::getPlatform()->buildAddForeignKeysDdl($table));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetForeignKeyDDL')]
    public function testGetAddForeignKeyDDL($fk)
    {
        $expected = '
ALTER TABLE "foo" ADD CONSTRAINT "foo_bar_fk"
    FOREIGN KEY ("bar_id")
    REFERENCES "bar" ("id")
    ON DELETE CASCADE;
';
        $this->assertEquals($expected, static::getPlatform()->buildAddForeignKeyDdl($fk));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetForeignKeySkipSqlDDL')]
    public function testGetAddForeignKeySkipSqlDDL($fk)
    {
        $expected = '';
        $this->assertEquals($expected, static::getPlatform()->buildAddForeignKeyDdl($fk));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetForeignKeyDDL')]
    public function testGetDropForeignKeyDDL($fk)
    {
        $expected = '
ALTER TABLE "foo" DROP CONSTRAINT "foo_bar_fk";
';
        $this->assertEquals($expected, static::getPlatform()->buildDropForeignKeyDdl($fk));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetForeignKeySkipSqlDDL')]
    public function testGetDropForeignKeySkipSqlDDL($fk)
    {
        $expected = '';
        $this->assertEquals($expected, static::getPlatform()->buildDropForeignKeyDdl($fk));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetForeignKeyDDL')]
    public function testGetForeignKeyDDL($fk)
    {
        $expected = 'CONSTRAINT "foo_bar_fk"
    FOREIGN KEY ("bar_id")
    REFERENCES "bar" ("id")
    ON DELETE CASCADE';
        $this->assertEquals($expected, static::getPlatform()->buildForeignKeyDdl($fk));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetForeignKeySkipSqlDDL')]
    public function testGetForeignKeySkipSqlDDL($fk)
    {
        $expected = '';
        $this->assertEquals($expected, static::getPlatform()->buildForeignKeyDdl($fk));
    }

    /**
     * @return void
     */
    public function testGetCommentBlockDDL()
    {
        $expected = "
-- ---------------------------------------------------------------------
-- foo bar
-- ---------------------------------------------------------------------
";
        $this->assertEquals($expected, static::getPlatform()->buildCommentBlockDdl('foo bar'));
    }

    /**
     * @return void
     */
    public function assertCreateTableMatches(string $expected, $schema, ?string $tableName = 'foo' )
    {
        $table = $this->getTableFromSchema($schema, $tableName);
        $this->assertEquals($expected, static::getPlatform()->buildAddTableDdl($table));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestCreateSchemaWithUuidColumns')]
    public function testCreateSchemaWithUuidColumns($schema)
    {
        $expected = <<< 'EOT'

CREATE TABLE "foo"
(
    "uuid" uuid DEFAULT vendor_specific_default() NOT NULL,
    "other_uuid" uuid,
    PRIMARY KEY ("uuid")
);

EOT;
        $this->assertCreateTableMatches($expected, $schema);
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestCreateSchemaWithUuidBinaryColumns')]
    public function testCreateSchemaWithUuidBinaryColumns($schema)
    {
        $expected = <<< 'EOT'

CREATE TABLE "foo"
(
    "uuid-bin" BYTEA DEFAULT vendor_specific_default() NOT NULL,
    "other_uuid-bin" BYTEA,
    PRIMARY KEY ("uuid-bin")
);

EOT;
        $this->assertCreateTableMatches($expected, $schema);
    }
}
