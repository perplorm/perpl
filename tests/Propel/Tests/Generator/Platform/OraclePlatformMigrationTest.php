<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Platform;

use Propel\Generator\Model\Diff\DatabaseComparator;
use Propel\Generator\Platform\OraclePlatform;
use Propel\Generator\Platform\PlatformInterface;

class OraclePlatformMigrationTest extends PlatformMigrationTestProvider
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
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetModifyDatabaseDDL')]
    public function testGetModifyDatabaseDDL($databaseDiff)
    {
        $expected = "
ALTER SESSION SET NLS_DATE_FORMAT='YYYY-MM-DD';
ALTER SESSION SET NLS_TIMESTAMP_FORMAT='YYYY-MM-DD HH24:MI:SS';

DROP TABLE foo1 CASCADE CONSTRAINTS;

DROP SEQUENCE foo1_SEQ;

ALTER TABLE foo3 RENAME TO foo4;

CREATE TABLE foo5
(
    id NUMBER NOT NULL,
    lkdjfsh NUMBER,
    dfgdsgf NVARCHAR2(2000)
);

ALTER TABLE foo5 ADD CONSTRAINT foo5_pk PRIMARY KEY (id);

CREATE SEQUENCE foo5_SEQ
    INCREMENT BY 1 START WITH 1 NOMAXVALUE NOCYCLE NOCACHE ORDER;

ALTER TABLE foo2 RENAME COLUMN bar TO bar1;

ALTER TABLE foo2

  MODIFY
(
    baz NVARCHAR2(12)
),

  ADD
(
    baz3 NVARCHAR2(2000)
);
";
        $this->assertEquals($expected, static::getPlatform()->getModifyDatabaseDDL($databaseDiff));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetRenameTableDDL')]
    public function testGetRenameTableDDL($fromName, $toName)
    {
        $expected = "
ALTER TABLE foo1 RENAME TO foo2;
";
        $this->assertEquals($expected, static::getPlatform()->getRenameTableDDL($fromName, $toName));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetModifyTableDDL')]
    public function testGetModifyTableDDL($tableDiff)
    {
        $expected = "
ALTER TABLE foo DROP CONSTRAINT foo1_fk_2;

ALTER TABLE foo DROP CONSTRAINT foo1_fk_1;

DROP INDEX bar_baz_fk;

DROP INDEX bar_fk;

ALTER TABLE foo RENAME COLUMN bar TO bar1;

ALTER TABLE foo

  MODIFY
(
    baz NVARCHAR2(12) DEFAULT 'pdf;jpg;png;doc;docx;xls;xlsx;txt'
),

  ADD
(
    baz3 NVARCHAR2(2000)
);

CREATE INDEX bar_fk ON foo (bar1);

CREATE INDEX baz_fk ON foo (baz3);

ALTER TABLE foo ADD CONSTRAINT foo1_fk_1
    FOREIGN KEY (bar1) REFERENCES foo2 (bar);
";
        $this->assertEquals($expected, static::getPlatform()->getModifyTableDDL($tableDiff));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetModifyTableColumnsDDL')]
    public function testGetModifyTableColumnsDDL($tableDiff)
    {
        $expected = "
ALTER TABLE foo RENAME COLUMN bar TO bar1;

ALTER TABLE foo MODIFY
(
    baz NVARCHAR2(12)
);

ALTER TABLE foo ADD
(
    baz3 NVARCHAR2(2000)
);
";
        $this->assertEquals($expected, static::getPlatform()->getModifyTableColumnsDDL($tableDiff));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetModifyTablePrimaryKeysDDL')]
    public function testGetModifyTablePrimaryKeysDDL($tableDiff)
    {
        $expected = "
ALTER TABLE foo DROP CONSTRAINT foo_pk;

ALTER TABLE foo ADD CONSTRAINT foo_pk PRIMARY KEY (id,bar);
";
        $this->assertEquals($expected, static::getPlatform()->getModifyTablePrimaryKeyDDL($tableDiff));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetModifyTableIndicesDDL')]
    public function testGetModifyTableIndicesDDL($tableDiff)
    {
        $expected = "
DROP INDEX bar_fk;

DROP INDEX bax_unique;

CREATE INDEX baz_fk ON foo (baz);

CREATE UNIQUE INDEX bax_bay_unique ON foo (bax,bay);

DROP INDEX bar_baz_fk;

CREATE INDEX bar_baz_fk ON foo (id,bar,baz);
";
        $this->assertEquals($expected, static::getPlatform()->getModifyTableIndicesDDL($tableDiff));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetModifyTableForeignKeysDDL')]
    public function testGetModifyTableForeignKeysDDL($tableDiff)
    {
        $expected = "
ALTER TABLE foo1 DROP CONSTRAINT foo1_fk_1;

ALTER TABLE foo1 ADD CONSTRAINT foo1_fk_3
    FOREIGN KEY (baz) REFERENCES foo2 (baz);

ALTER TABLE foo1 DROP CONSTRAINT foo1_fk_2;

ALTER TABLE foo1 ADD CONSTRAINT foo1_fk_2
    FOREIGN KEY (bar,id) REFERENCES foo2 (bar,id);
";
        $this->assertEquals($expected, static::getPlatform()->getModifyTableForeignKeysDDL($tableDiff));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetModifyTableForeignKeysSkipSqlDDL')]
    public function testGetModifyTableForeignKeysSkipSqlDDL($tableDiff)
    {
        $expected = "
ALTER TABLE foo1 DROP CONSTRAINT foo1_fk_1;
";
        $this->assertEquals($expected, static::getPlatform()->getModifyTableForeignKeysDDL($tableDiff));
        $expected = "
ALTER TABLE foo1 ADD CONSTRAINT foo1_fk_1
    FOREIGN KEY (bar) REFERENCES foo2 (bar);
";
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
        $expected = "
ALTER TABLE foo DROP COLUMN bar;
";
        $this->assertEquals($expected, static::getPlatform()->getRemoveColumnDDL($column));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetRenameColumnDDL')]
    public function testGetRenameColumnDDL($fromColumn, $toColumn)
    {
        $expected = "
ALTER TABLE foo RENAME COLUMN bar1 TO bar2;
";
        $this->assertEquals($expected, static::getPlatform()->getRenameColumnDDL($fromColumn, $toColumn));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetModifyColumnDDL')]
    public function testGetModifyColumnDDL($columnDiff)
    {
        $expected = "
ALTER TABLE foo MODIFY bar FLOAT(3);
";
        $this->assertEquals($expected, static::getPlatform()->getModifyColumnDDL($columnDiff));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetModifyColumnsDDL')]
    public function testGetModifyColumnsDDL($columnDiffs)
    {
        $expected = "
ALTER TABLE foo MODIFY
(
    bar1 FLOAT(3),
    bar2 INTEGER NOT NULL
);
";
        $this->assertEquals($expected, static::getPlatform()->getModifyColumnsDDL($columnDiffs));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetAddColumnDDL')]
    public function testGetAddColumnDDL($column)
    {
        $expected = "
ALTER TABLE foo ADD bar NUMBER;
";
        $this->assertEquals($expected, static::getPlatform()->getAddColumnDDL($column));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetAddColumnsDDL')]
    public function testGetAddColumnsDDL($columns)
    {
        $expected = "
ALTER TABLE foo ADD
(
    bar1 NUMBER,
    bar2 FLOAT(3,2) DEFAULT -1 NOT NULL
);
";
        $this->assertEquals($expected, static::getPlatform()->getAddColumnsDDL($columns));
    }

    /**
     * @return void
     */
    public function testGetModifyDatabaseWithBlockStorageDDL()
    {
        $schema1 = <<<EOF
<database name="test">
    <table name="foo1">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <column name="blooopoo" type="INTEGER"/>
    </table>
    <table name="foo2">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <column name="bar" type="INTEGER"/>
        <column name="baz" type="VARCHAR" size="12" required="true"/>
    </table>
    <table name="foo3">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <column name="yipee" type="INTEGER"/>
    </table>
</database>
EOF;
        $schema2 = <<<EOF
<database name="test">
    <table name="foo2">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <column name="bar1" type="INTEGER"/>
        <column name="baz" type="VARCHAR" size="12" required="false"/>
        <column name="baz3" type="CLOB"/>
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
    <table name="foo4">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <column name="yipee" type="INTEGER"/>
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
    <table name="foo5">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <column name="lkdjfsh" type="INTEGER"/>
        <column name="dfgdsgf" type="CLOB"/>
        <index name="lkdjfsh_IDX">
            <index-column name="lkdjfsh"/>
            <vendor type="oracle">
                <parameter name="PCTFree" value="20"/>
                <parameter name="InitTrans" value="4"/>
                <parameter name="MinExtents" value="1"/>
                <parameter name="MaxExtents" value="99"/>
                <parameter name="PCTIncrease" value="0"/>
                <parameter name="Tablespace" value="L_128K"/>
            </vendor>
        </index>
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
        $d1 = $this->getDatabaseFromSchema($schema1);
        $d2 = $this->getDatabaseFromSchema($schema2);
        $databaseDiff = DatabaseComparator::computeDiff($d1, $d2);
        $expected = "
ALTER SESSION SET NLS_DATE_FORMAT='YYYY-MM-DD';
ALTER SESSION SET NLS_TIMESTAMP_FORMAT='YYYY-MM-DD HH24:MI:SS';

DROP TABLE foo1 CASCADE CONSTRAINTS;

DROP SEQUENCE foo1_SEQ;

DROP TABLE foo3 CASCADE CONSTRAINTS;

DROP SEQUENCE foo3_SEQ;

CREATE TABLE foo4
(
    id NUMBER NOT NULL,
    yipee NUMBER
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

ALTER TABLE foo4 ADD CONSTRAINT foo4_pk PRIMARY KEY (id)
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

CREATE SEQUENCE foo4_SEQ
    INCREMENT BY 1 START WITH 1 NOMAXVALUE NOCYCLE NOCACHE ORDER;

CREATE TABLE foo5
(
    id NUMBER NOT NULL,
    lkdjfsh NUMBER,
    dfgdsgf CLOB
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

ALTER TABLE foo5 ADD CONSTRAINT foo5_pk PRIMARY KEY (id)
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

CREATE SEQUENCE foo5_SEQ
    INCREMENT BY 1 START WITH 1 NOMAXVALUE NOCYCLE NOCACHE ORDER;

CREATE INDEX lkdjfsh_IDX ON foo5 (lkdjfsh)
PCTFREE 20
INITRANS 4
STORAGE
(
    MINEXTENTS 1
    MAXEXTENTS 99
    PCTINCREASE 0
)
TABLESPACE L_128K;

ALTER TABLE foo2 RENAME COLUMN bar TO bar1;

ALTER TABLE foo2

  MODIFY
(
    baz NVARCHAR2(12)
),

  ADD
(
    baz3 CLOB
);
";
        $this->assertEquals($expected, static::getPlatform()->getModifyDatabaseDDL($databaseDiff));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestMigrateToUUIDColumn')]
    public function testMigrateToUUIDColumn($tableDiff)
    {
        $expected = <<<END

ALTER TABLE foo MODIFY
(
    id UUID DEFAULT vendor_specific_uuid_generator_function() NOT NULL
);

END;
        $this->assertEquals($expected, static::getPlatform()->getModifyTableColumnsDDL($tableDiff));
    }
}
