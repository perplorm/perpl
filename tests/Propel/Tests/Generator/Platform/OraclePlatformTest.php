<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Platform;

use Propel\Generator\Model\Column;
use Propel\Generator\Model\IdMethod;
use Propel\Generator\Model\IdMethodParameter;
use Propel\Generator\Model\Table;
use Propel\Generator\Platform\OraclePlatform;
use Propel\Generator\Platform\PlatformInterface;

class OraclePlatformTest extends PlatformTestProvider
{
    /**
     * Get the Platform object for this class
     *
     * @return \Propel\Generator\Platform\OraclePlatform
     */
    protected static function getPlatform(): PlatformInterface
    {
        return new OraclePlatform();
    }

    /**
     * @return void
     */
    public function testGetSequenceNameDefault()
    {
        $table = new Table('foo');
        $table->setIdMethod(IdMethod::NATIVE);
        $expected = 'foo_SEQ';
        $this->assertEquals($expected, static::getPlatform()->getSequenceName($table));
    }

    /**
     * @return void
     */
    public function testGetSequenceNameCustom()
    {
        $table = new Table('foo');
        $table->setIdMethod(IdMethod::NATIVE);
        $idMethodParameter = new IdMethodParameter();
        $idMethodParameter->setValue('foo_sequence');
        $table->addIdMethodParameter($idMethodParameter);
        $table->setIdMethod(IdMethod::NATIVE);
        $expected = 'foo_sequence';
        $this->assertEquals($expected, static::getPlatform()->getSequenceName($table));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetAddTablesDDL')]
    public function testGetAddTablesDDL($schema)
    {
        $database = $this->getDatabaseFromSchema($schema);
        $expected = <<<EOF

ALTER SESSION SET NLS_DATE_FORMAT='YYYY-MM-DD';
ALTER SESSION SET NLS_TIMESTAMP_FORMAT='YYYY-MM-DD HH24:MI:SS';

-----------------------------------------------------------------------
-- book
-----------------------------------------------------------------------

DROP TABLE book CASCADE CONSTRAINTS;

DROP SEQUENCE book_SEQ;

CREATE TABLE book
(
    id NUMBER NOT NULL,
    title NVARCHAR2(255) NOT NULL,
    author_id NUMBER
);

ALTER TABLE book ADD CONSTRAINT book_pk PRIMARY KEY (id);

CREATE SEQUENCE book_SEQ
    INCREMENT BY 1 START WITH 1 NOMAXVALUE NOCYCLE NOCACHE ORDER;

CREATE INDEX book_i_639136 ON book (title);

-----------------------------------------------------------------------
-- author
-----------------------------------------------------------------------

DROP TABLE author CASCADE CONSTRAINTS;

DROP SEQUENCE author_SEQ;

CREATE TABLE author
(
    id NUMBER NOT NULL,
    first_name NVARCHAR2(100),
    last_name NVARCHAR2(100)
);

ALTER TABLE author ADD CONSTRAINT author_pk PRIMARY KEY (id);

CREATE SEQUENCE author_SEQ
    INCREMENT BY 1 START WITH 1 NOMAXVALUE NOCYCLE NOCACHE ORDER;

-----------------------------------------------------------------------
-- Foreign Keys
-----------------------------------------------------------------------

ALTER TABLE book ADD CONSTRAINT book_fk_ea464c
    FOREIGN KEY (author_id) REFERENCES author (id);

EOF;
        $this->assertEquals($expected, static::getPlatform()->getAddTablesDDL($database));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetAddTablesSkipSQLDDL')]
    public function testGetAddTablesSkipSQLDDL($schema)
    {
        $database = $this->getDatabaseFromSchema($schema);
        $expected = "
ALTER SESSION SET NLS_DATE_FORMAT='YYYY-MM-DD';
ALTER SESSION SET NLS_TIMESTAMP_FORMAT='YYYY-MM-DD HH24:MI:SS';
";
        $this->assertEquals($expected, static::getPlatform()->getAddTablesDDL($database));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetAddTableDDLSimplePK')]
    public function testGetAddTableDDLSimplePK($schema)
    {
        $table = $this->getTableFromSchema($schema);
        $expected = "
-- This is foo table
CREATE TABLE foo
(
    id NUMBER NOT NULL,
    bar NVARCHAR2(255) NOT NULL
);

ALTER TABLE foo ADD CONSTRAINT foo_pk PRIMARY KEY (id);

CREATE SEQUENCE foo_SEQ
    INCREMENT BY 1 START WITH 1 NOMAXVALUE NOCYCLE NOCACHE ORDER;
";
        $this->assertEquals($expected, static::getPlatform()->getAddTableDDL($table));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetAddTableDDLCompositePK')]
    public function testGetAddTableDDLCompositePK($schema)
    {
        $table = $this->getTableFromSchema($schema);
        $expected = "
CREATE TABLE foo
(
    foo NUMBER NOT NULL,
    bar NUMBER NOT NULL,
    baz NVARCHAR2(255) NOT NULL
);

ALTER TABLE foo ADD CONSTRAINT foo_pk PRIMARY KEY (foo,bar);
";
        $this->assertEquals($expected, static::getPlatform()->getAddTableDDL($table));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetAddTableDDLUniqueIndex')]
    public function testGetAddTableDDLUniqueIndex($schema)
    {
        $table = $this->getTableFromSchema($schema);
        $expected = "
CREATE TABLE foo
(
    id NUMBER NOT NULL,
    bar NUMBER,
    CONSTRAINT foo_u_14f552 UNIQUE (bar)
);

ALTER TABLE foo ADD CONSTRAINT foo_pk PRIMARY KEY (id);

CREATE SEQUENCE foo_SEQ
    INCREMENT BY 1 START WITH 1 NOMAXVALUE NOCYCLE NOCACHE ORDER;
";
        $this->assertEquals($expected, static::getPlatform()->getAddTableDDL($table));
    }

    /**
     * @return void
     */
    public function testGetDropTableDDL()
    {
        $table = new Table('foo');
        $expected = "
DROP TABLE foo CASCADE CONSTRAINTS;
";
        $this->assertEquals($expected, static::getPlatform()->getDropTableDDL($table));
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
        $table->setIdMethod(IdMethod::NATIVE);
        $expected = "
DROP TABLE foo CASCADE CONSTRAINTS;

DROP SEQUENCE foo_sequence;
";
        $this->assertEquals($expected, static::getPlatform()->getDropTableDDL($table));
    }

    /**
     * @return void
     */
    public function testGetColumnDDLCustomSqlType()
    {
        $column = new Column('foo');
        $column->getDomain()->copy(static::getPlatform()->getDomainForType('DOUBLE'));
        $column->getDomain()->replaceScale(2);
        $column->getDomain()->replaceSize(3);
        $column->setNotNull(true);
        $column->getDomain()->createDefaultValue(123);
        $column->getDomain()->replaceSqlType('DECIMAL(5,6)');
        $expected = 'foo DECIMAL(5,6) DEFAULT 123 NOT NULL';
        $this->assertEquals($expected, static::getPlatform()->getColumnDDL($column));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestPrimaryKeyDDL')]
    public function testGetPrimaryKeyDDLSimpleKey($table)
    {
        $expected = 'CONSTRAINT foo_pk PRIMARY KEY (bar)';
        $this->assertEquals($expected, static::getPlatform()->getPrimaryKeyDDL($table));
    }

    /**
     * @return void
     */
    public function testGetPrimaryKeyDDLLongTableName()
    {
        $table = new Table('this_table_has_a_very_long_name');
        $column = new Column('bar');
        $column->setPrimaryKey(true);
        $table->addColumn($column);
        $expected = 'CONSTRAINT this_table_has_a_very_long__pk PRIMARY KEY (bar)';
        $this->assertEquals($expected, static::getPlatform()->getPrimaryKeyDDL($table));
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
        $expected = 'CONSTRAINT foo_pk PRIMARY KEY (bar1,bar2)';
        $this->assertEquals($expected, static::getPlatform()->getPrimaryKeyDDL($table));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestPrimaryKeyDDL')]
    public function testGetDropPrimaryKeyDDL($table)
    {
        $expected = "
ALTER TABLE foo DROP CONSTRAINT foo_pk;
";
        $this->assertEquals($expected, static::getPlatform()->getDropPrimaryKeyDDL($table));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestPrimaryKeyDDL')]
    public function testGetAddPrimaryKeyDDL($table)
    {
        $expected = "
ALTER TABLE foo ADD CONSTRAINT foo_pk PRIMARY KEY (bar);
";
        $this->assertEquals($expected, static::getPlatform()->getAddPrimaryKeyDDL($table));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetIndicesDDL')]
    public function testAddIndicesDDL($table)
    {
        $expected = "
CREATE INDEX babar ON foo (bar1,bar2);

CREATE INDEX foo_index ON foo (bar1);
";
        $this->assertEquals($expected, static::getPlatform()->getAddIndicesDDL($table));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetIndexDDL')]
    public function testAddIndexDDL($index)
    {
        $expected = "
CREATE INDEX babar ON foo (bar1,bar2);
";
        $this->assertEquals($expected, static::getPlatform()->getAddIndexDDL($index));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetIndexDDL')]
    public function testDropIndexDDL($index)
    {
        $expected = "
DROP INDEX babar;
";
        $this->assertEquals($expected, static::getPlatform()->getDropIndexDDL($index));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetIndexDDL')]
    public function testGetIndexDDL($index)
    {
        $expected = 'INDEX babar (bar1,bar2)';
        $this->assertEquals($expected, static::getPlatform()->getIndexDDL($index));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetUniqueDDL')]
    public function testGetUniqueDDL($index)
    {
        $expected = 'CONSTRAINT babar UNIQUE (bar1,bar2)';
        $this->assertEquals($expected, static::getPlatform()->getUniqueDDL($index));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetForeignKeysDDL')]
    public function testGetAddForeignKeysDDL($table)
    {
        $expected = "
ALTER TABLE foo ADD CONSTRAINT foo_bar_fk
    FOREIGN KEY (bar_id) REFERENCES bar (id)
    ON DELETE CASCADE;

ALTER TABLE foo ADD CONSTRAINT foo_baz_fk
    FOREIGN KEY (baz_id) REFERENCES baz (id)
    ON DELETE SET NULL;
";
        $this->assertEquals($expected, static::getPlatform()->getAddForeignKeysDDL($table));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetForeignKeyDDL')]
    public function testGetAddForeignKeyDDL($fk)
    {
        $expected = "
ALTER TABLE foo ADD CONSTRAINT foo_bar_fk
    FOREIGN KEY (bar_id) REFERENCES bar (id)
    ON DELETE CASCADE;
";
        $this->assertEquals($expected, static::getPlatform()->getAddForeignKeyDDL($fk));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetForeignKeySkipSqlDDL')]
    public function testGetAddForeignKeySkipSqlDDL($fk)
    {
        $expected = '';
        $this->assertEquals($expected, static::getPlatform()->getAddForeignKeyDDL($fk));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetForeignKeyDDL')]
    public function testGetDropForeignKeyDDL($fk)
    {
        $expected = "
ALTER TABLE foo DROP CONSTRAINT foo_bar_fk;
";
        $this->assertEquals($expected, static::getPlatform()->getDropForeignKeyDDL($fk));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetForeignKeySkipSqlDDL')]
    public function testGetDropForeignKeySkipSqlDDL($fk)
    {
        $expected = '';
        $this->assertEquals($expected, static::getPlatform()->getDropForeignKeyDDL($fk));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetForeignKeyDDL')]
    public function testGetForeignKeyDDL($fk)
    {
        $expected = "CONSTRAINT foo_bar_fk
    FOREIGN KEY (bar_id) REFERENCES bar (id)
    ON DELETE CASCADE";
        $this->assertEquals($expected, static::getPlatform()->getForeignKeyDDL($fk));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetForeignKeySkipSqlDDL')]
    public function testGetForeignKeySkipSqlDDL($fk)
    {
        $expected = '';
        $this->assertEquals($expected, static::getPlatform()->getForeignKeyDDL($fk));
    }

    /**
     * @return void
     */
    public function testGetCommentBlockDDL()
    {
        $expected = "
-----------------------------------------------------------------------
-- foo bar
-----------------------------------------------------------------------
";
        $this->assertEquals($expected, static::getPlatform()->getCommentBlockDDL('foo bar'));
    }

    /**
     * @return void
     */
    public function testGetOracleBlockStorageDDL()
    {
        $schema = <<<EOF
<database name="test" schema="x">
    <table name="book">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <column name="title" type="VARCHAR" size="255" required="true"/>
        <index>
            <index-column name="title"/>
            <vendor type="oracle">
                <parameter name="PCTFree" value="20"/>
                <parameter name="InitTrans" value="4"/>
                <parameter name="MinExtents" value="1"/>
                <parameter name="MaxExtents" value="99"/>
                <parameter name="PCTIncrease" value="0"/>
                <parameter name="Tablespace" value="IL_128K"/>
            </vendor>
        </index>
        <column name="author_id" type="INTEGER"/>
        <foreign-key foreignTable="author" foreignSchema="y">
            <reference local="author_id" foreign="id"/>
        </foreign-key>
        <vendor type="oracle">
            <parameter name="PCTFree" value="20"/>
            <parameter name="InitTrans" value="4"/>
            <parameter name="MinExtents" value="1"/>
            <parameter name="MaxExtents" value="99"/>
            <parameter name="PCTIncrease" value="0"/>
            <parameter name="Tablespace" value="L_128K"/>
            <parameter name="PKPCTFree" value="20"/>
            <parameter name="PKInitTrans" value="4"/>
            <parameter name="PKMinExtents" value="1"/>
            <parameter name="PKMaxExtents" value="99"/>
            <parameter name="PKPCTIncrease" value="0"/>
            <parameter name="PKTablespace" value="IL_128K"/>
        </vendor>
    </table>
    <table name="author" schema="y">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <column name="first_name" type="VARCHAR" size="100"/>
        <column name="last_name" type="VARCHAR" size="100"/>
        <vendor type="oracle">
            <parameter name="PCTFree" value="20"/>
            <parameter name="InitTrans" value="4"/>
            <parameter name="MinExtents" value="1"/>
            <parameter name="MaxExtents" value="99"/>
            <parameter name="PCTIncrease" value="0"/>
            <parameter name="Tablespace" value="L_128K"/>
            <parameter name="PKPCTFree" value="20"/>
            <parameter name="PKInitTrans" value="4"/>
            <parameter name="PKMinExtents" value="1"/>
            <parameter name="PKMaxExtents" value="99"/>
            <parameter name="PKPCTIncrease" value="0"/>
            <parameter name="PKTablespace" value="IL_128K"/>
        </vendor>
    </table>
</database>
EOF;
        $database = $this->getDatabaseFromSchema($schema);
        $expected = <<<EOF

ALTER SESSION SET NLS_DATE_FORMAT='YYYY-MM-DD';
ALTER SESSION SET NLS_TIMESTAMP_FORMAT='YYYY-MM-DD HH24:MI:SS';

-----------------------------------------------------------------------
-- book
-----------------------------------------------------------------------

DROP TABLE book CASCADE CONSTRAINTS;

DROP SEQUENCE book_SEQ;

CREATE TABLE book
(
    id NUMBER NOT NULL,
    title NVARCHAR2(255) NOT NULL,
    author_id NUMBER
)
PCTFREE 20
INITRANS 4
STORAGE
(
    MINEXTENTS 1
    MAXEXTENTS 99
    PCTINCREASE 0
)
TABLESPACE L_128K;

ALTER TABLE book ADD CONSTRAINT book_pk PRIMARY KEY (id)
USING INDEX
PCTFREE 20
INITRANS 4
STORAGE
(
    MINEXTENTS 1
    MAXEXTENTS 99
    PCTINCREASE 0
)
TABLESPACE IL_128K;

CREATE SEQUENCE book_SEQ
    INCREMENT BY 1 START WITH 1 NOMAXVALUE NOCYCLE NOCACHE ORDER;

CREATE INDEX book_i_639136 ON book (title)
PCTFREE 20
INITRANS 4
STORAGE
(
    MINEXTENTS 1
    MAXEXTENTS 99
    PCTINCREASE 0
)
TABLESPACE IL_128K;

-----------------------------------------------------------------------
-- author
-----------------------------------------------------------------------

DROP TABLE author CASCADE CONSTRAINTS;

DROP SEQUENCE author_SEQ;

CREATE TABLE author
(
    id NUMBER NOT NULL,
    first_name NVARCHAR2(100),
    last_name NVARCHAR2(100)
)
PCTFREE 20
INITRANS 4
STORAGE
(
    MINEXTENTS 1
    MAXEXTENTS 99
    PCTINCREASE 0
)
TABLESPACE L_128K;

ALTER TABLE author ADD CONSTRAINT author_pk PRIMARY KEY (id)
USING INDEX
PCTFREE 20
INITRANS 4
STORAGE
(
    MINEXTENTS 1
    MAXEXTENTS 99
    PCTINCREASE 0
)
TABLESPACE IL_128K;

CREATE SEQUENCE author_SEQ
    INCREMENT BY 1 START WITH 1 NOMAXVALUE NOCYCLE NOCACHE ORDER;

-----------------------------------------------------------------------
-- Foreign Keys
-----------------------------------------------------------------------

ALTER TABLE book ADD CONSTRAINT book_fk_4444ca
    FOREIGN KEY (author_id) REFERENCES author (id);

EOF;

        $this->assertEquals($expected, static::getPlatform()->getAddTablesDDL($database));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestCreateSchemaWithUuidColumns')]
    public function testCreateSchemaWithUuidColumns($schema)
    {
        $expected = "
CREATE TABLE foo
(
    uuid UUID DEFAULT vendor_specific_default() NOT NULL,
    other_uuid UUID
);

ALTER TABLE foo ADD CONSTRAINT foo_pk PRIMARY KEY (uuid);
";
        $this->assertCreateTableMatches($expected, $schema);
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestCreateSchemaWithUuidBinaryColumns')]
    public function testCreateSchemaWithUuidBinaryColumns($schema)
    {
        $expected = "
CREATE TABLE foo
(
    uuid-bin RAW(16) DEFAULT vendor_specific_default() NOT NULL,
    other_uuid-bin RAW(16)
);

ALTER TABLE foo ADD CONSTRAINT foo_pk PRIMARY KEY (uuid-bin);
";
        $this->assertCreateTableMatches($expected, $schema);
    }
}
