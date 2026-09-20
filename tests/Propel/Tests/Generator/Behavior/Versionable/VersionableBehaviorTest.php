<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Behavior\Versionable;

use Propel\Generator\Behavior\Versionable\VersionableBehavior;
use Propel\Generator\Util\QuickBuilder;

/**
 * Tests for VersionableBehavior class
 *
 * @author François Zaninotto
 */
class VersionableBehaviorTest extends TestCase
{
    public static function basicSchemaDataProvider()
    {
        $schema = <<<EOF
<database name="versionable_behavior_test_0">
    <table name="versionable_behavior_test_0">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <column name="bar" type="INTEGER"/>
        <behavior name="versionable"/>
    </table>
</database>
EOF;

        return [[$schema]];
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('basicSchemaDataProvider')]
    public function testModifyTableAddsVersionColumn($schema)
    {
        $builder = new QuickBuilder();
        $builder->setSchemaXml($schema);
        $expected = <<<EOF
-- ---------------------------------------------------------------------
-- versionable_behavior_test_0
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS versionable_behavior_test_0;

CREATE TABLE versionable_behavior_test_0
(
    id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    bar INTEGER,
    version INTEGER DEFAULT 0,
    UNIQUE (id)
);
EOF;
        $this->assertStringContainsString($expected, $builder->buildSql());
    }

    /**
     * @return void
     */
    public function testModifyTableAddsVersionColumnCustomName()
    {
            $schema = <<<EOF
<database name="versionable_behavior_test_0">
    <table name="versionable_behavior_test_0">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <column name="bar" type="INTEGER"/>
        <behavior name="versionable">
            <parameter name="version_column" value="foo_ver"/>
        </behavior>
    </table>
</database>
EOF;
        $builder = new QuickBuilder();
        $builder->setSchemaXml($schema);
        $expected = <<<EOF
-- ---------------------------------------------------------------------
-- versionable_behavior_test_0
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS versionable_behavior_test_0;

CREATE TABLE versionable_behavior_test_0
(
    id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    bar INTEGER,
    foo_ver INTEGER DEFAULT 0,
    UNIQUE (id)
);
EOF;
        $this->assertStringContainsString($expected, $builder->buildSql());
    }

    /**
     * @return void
     */
    public function testModifyTableDoesNotAddVersionColumnIfExists()
    {
            $schema = <<<EOF
<database name="versionable_behavior_test_0">
    <table name="versionable_behavior_test_0">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <column name="bar" type="INTEGER"/>
        <column name="version" type="BIGINT"/>
        <behavior name="versionable"/>
    </table>
</database>
EOF;
        $builder = new QuickBuilder();
        $builder->setSchemaXml($schema);
        $expected = <<<EOF
-- ---------------------------------------------------------------------
-- versionable_behavior_test_0
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS versionable_behavior_test_0;

CREATE TABLE versionable_behavior_test_0
(
    id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    bar INTEGER,
    version BIGINT,
    UNIQUE (id)
);
EOF;
        $this->assertStringContainsString($expected, $builder->buildSql());
    }

    public static function foreignTableSchemaDataProvider()
    {
        $schema = <<<EOF
<database name="versionable_behavior_test_0">
    <table name="versionable_behavior_test_0">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <column name="bar" type="INTEGER"/>
        <column name="foreign_id" type="INTEGER"/>
        <foreign-key foreignTable="versionable_behavior_test_1">
            <reference local="foreign_id" foreign="id"/>
        </foreign-key>
        <behavior name="versionable"/>
    </table>
    <table name="versionable_behavior_test_1">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <column name="bar" type="INTEGER"/>
        <behavior name="versionable"/>
    </table>
</database>
EOF;

        return [[$schema]];
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('foreignTableSchemaDataProvider')]
    public function testModifyTableAddsVersionColumnForForeignKeysIfForeignTableIsVersioned($schema)
    {
        $builder = new QuickBuilder();
        $builder->setSchemaXml($schema);
        $expected = <<<EOF
-- ---------------------------------------------------------------------
-- versionable_behavior_test_0
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS versionable_behavior_test_0;

CREATE TABLE versionable_behavior_test_0
(
    id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    bar INTEGER,
    foreign_id INTEGER,
    version INTEGER DEFAULT 0,
    UNIQUE (id),
    FOREIGN KEY (foreign_id) REFERENCES versionable_behavior_test_1 (id)
);
EOF;
        $this->assertStringContainsString($expected, $builder->buildSql());
        $expected = <<<EOF

-- ---------------------------------------------------------------------
-- versionable_behavior_test_0_version
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS versionable_behavior_test_0_version;

CREATE TABLE versionable_behavior_test_0_version
(
    id INTEGER NOT NULL,
    bar INTEGER,
    foreign_id INTEGER,
    version INTEGER DEFAULT 0 NOT NULL,
    foreign_id_version INTEGER DEFAULT 0,
    PRIMARY KEY (id,version),
    UNIQUE (id,version),
    FOREIGN KEY (id) REFERENCES versionable_behavior_test_0 (id)
        ON DELETE CASCADE
);
EOF;
        $this->assertStringContainsString($expected, $builder->buildSql());
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('foreignTableSchemaDataProvider')]
    public function testModifyTableAddsVersionColumnForReferrersIfForeignTableIsVersioned($schema)
    {
        $builder = new QuickBuilder();
        $builder->setSchemaXml($schema);
        $expected = <<<EOF
-- ---------------------------------------------------------------------
-- versionable_behavior_test_1
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS versionable_behavior_test_1;

CREATE TABLE versionable_behavior_test_1
(
    id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    bar INTEGER,
    version INTEGER DEFAULT 0,
    UNIQUE (id)
);
EOF;
        $this->assertStringContainsString($expected, $builder->buildSql());
        $expected = <<<EOF

-- ---------------------------------------------------------------------
-- versionable_behavior_test_1_version
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS versionable_behavior_test_1_version;

CREATE TABLE versionable_behavior_test_1_version
(
    id INTEGER NOT NULL,
    bar INTEGER,
    version INTEGER DEFAULT 0 NOT NULL,
    versionable_behavior_test_0_ids MEDIUMTEXT,
    versionable_behavior_test_0_versions MEDIUMTEXT,
    PRIMARY KEY (id,version),
    UNIQUE (id,version),
    FOREIGN KEY (id) REFERENCES versionable_behavior_test_1 (id)
        ON DELETE CASCADE
);
EOF;
        $this->assertStringContainsString($expected, $builder->buildSql());
    }

    /**
     * @return void
     */
    public function testReferrerVersionColumnsDoNotIncludeTheTableSchema()
    {
        $schema = <<<EOF
<database schema="grapevine">
    <table name="responsibility_matrix" schema="grapevine">
        <column name="id" type="INTEGER"/>
        <behavior name="versionable"/>
    </table>
    <table name="responsibility_matrix_item" schema="grapevine">
        <column name="id" type="INTEGER"/>
        <column name="responsibility_matrix_id" type="INTEGER"/>
        <foreign-key foreignTable="responsibility_matrix" foreignSchema="grapevine">
            <reference local="responsibility_matrix_id" foreign="id"/>
        </foreign-key>
        <behavior name="versionable"/>
    </table>
</database>
EOF;
        $database = $this->buildDatabaseFromSchema($schema);
        $versionTable = $database->getTable('grapevine§responsibility_matrix_version');
        $this->assertTrue($versionTable->hasColumn('responsibility_matrix_item_ids'));
        $this->assertTrue($versionTable->hasColumn('responsibility_matrix_item_versions'));
        $this->assertFalse($versionTable->hasColumn('grapevine.responsibility_matrix_item_ids'));
        $this->assertFalse($versionTable->hasColumn('grapevine.responsibility_matrix_item_versions'));

        $table = $database->getTable('grapevine§responsibility_matrix');
        $foreignKey = $table->getReferrers()[0];
        /** @var VersionableBehavior */
        $behavior = $table->getBehavior('versionable');
        $this->assertSame('responsibility_matrix_item_ids', $behavior->getReferrerIdsColumn($foreignKey)->getName());
        $this->assertSame('responsibility_matrix_item_versions', $behavior->getReferrerVersionsColumn($foreignKey)->getName());
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('basicSchemaDataProvider')]
    public function testModifyTableAddsVersionTable($schema)
    {
        $builder = new QuickBuilder();
        $builder->setSchemaXml($schema);
        $expected = <<<EOF
-- ---------------------------------------------------------------------
-- versionable_behavior_test_0_version
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS versionable_behavior_test_0_version;

CREATE TABLE versionable_behavior_test_0_version
(
    id INTEGER NOT NULL,
    bar INTEGER,
    version INTEGER DEFAULT 0 NOT NULL,
    PRIMARY KEY (id,version),
    UNIQUE (id,version),
    FOREIGN KEY (id) REFERENCES versionable_behavior_test_0 (id)
        ON DELETE CASCADE
);
EOF;
        $this->assertStringContainsString($expected, $builder->buildSql());
    }

    /**
     * @return void
     */
    public function testModifyTableAddsVersionTableCustomName()
    {
        $schema = <<<EOF
<database name="versionable_behavior_test_0">
    <table name="versionable_behavior_test_0">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <column name="bar" type="INTEGER"/>
        <behavior name="versionable">
          <parameter name="version_table" value="foo_ver"/>
        </behavior>
    </table>
</database>
EOF;
        $builder = new QuickBuilder();
        $builder->setSchemaXml($schema);
        $expected = <<<EOF
-- ---------------------------------------------------------------------
-- foo_ver
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS foo_ver;

CREATE TABLE foo_ver
(
    id INTEGER NOT NULL,
    bar INTEGER,
    version INTEGER DEFAULT 0 NOT NULL,
    PRIMARY KEY (id,version),
    UNIQUE (id,version),
    FOREIGN KEY (id) REFERENCES versionable_behavior_test_0 (id)
        ON DELETE CASCADE
);
EOF;
        $this->assertStringContainsString($expected, $builder->buildSql());
    }

    /**
     * @return void
     */
    public function testModifyTableDoesNotAddVersionTableIfExists()
    {
        $schema = <<<EOF
<database name="versionable_behavior_test_0">
    <table name="versionable_behavior_test_0">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <column name="bar" type="INTEGER"/>
        <behavior name="versionable"/>
    </table>
    <table name="versionable_behavior_test_0_version">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <column name="baz" type="INTEGER"/>
    </table>
</database>
EOF;
        $builder = new QuickBuilder();
        $builder->setSchemaXml($schema);
        $expected = <<<EOF

-- ---------------------------------------------------------------------
-- versionable_behavior_test_0
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS versionable_behavior_test_0;

CREATE TABLE versionable_behavior_test_0
(
    id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    bar INTEGER,
    version INTEGER DEFAULT 0,
    UNIQUE (id)
);

-- ---------------------------------------------------------------------
-- versionable_behavior_test_0_version
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS versionable_behavior_test_0_version;

CREATE TABLE versionable_behavior_test_0_version
(
    id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    baz INTEGER,
    UNIQUE (id)
);

EOF;
        $this->assertEquals($expected, $builder->buildSql());
    }

    public static function logSchemaDataProvider()
    {
        $schema = <<<EOF
<database name="versionable_behavior_test_0">
    <table name="versionable_behavior_test_0">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <column name="bar" type="INTEGER"/>
        <behavior name="versionable">
          <parameter name="log_created_at" value="true"/>
          <parameter name="log_created_by" value="true"/>
          <parameter name="log_comment" value="true"/>
        </behavior>
    </table>
</database>
EOF;

        return [[$schema]];
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('logSchemaDataProvider')]
    public function testModifyTableAddsLogColumns($schema)
    {
        $builder = new QuickBuilder();
        $builder->setSchemaXml($schema);
        $expected = <<<EOF
-- ---------------------------------------------------------------------
-- versionable_behavior_test_0
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS versionable_behavior_test_0;

CREATE TABLE versionable_behavior_test_0
(
    id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    bar INTEGER,
    version INTEGER DEFAULT 0,
    version_created_at TIMESTAMP,
    version_created_by VARCHAR(100),
    version_comment VARCHAR(255),
    UNIQUE (id)
);
EOF;
        $this->assertStringContainsString($expected, $builder->buildSql());
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('logSchemaDataProvider')]
    public function testModifyTableAddsVersionTableLogColumns($schema)
    {
        $builder = new QuickBuilder();
        $builder->setSchemaXml($schema);
        $expected = <<<EOF
-- ---------------------------------------------------------------------
-- versionable_behavior_test_0_version
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS versionable_behavior_test_0_version;

CREATE TABLE versionable_behavior_test_0_version
(
    id INTEGER NOT NULL,
    bar INTEGER,
    version INTEGER DEFAULT 0 NOT NULL,
    version_created_at TIMESTAMP,
    version_created_by VARCHAR(100),
    version_comment VARCHAR(255),
    PRIMARY KEY (id,version),
    UNIQUE (id,version),
    FOREIGN KEY (id) REFERENCES versionable_behavior_test_0 (id)
        ON DELETE CASCADE
);
EOF;
        $this->assertStringContainsString($expected, $builder->buildSql());
    }

    /**
     * @return void
     */
    public function testDatabaseLevelBehavior()
    {
        $schema = <<<EOF
<database name="versionable_behavior_test_0">
    <behavior name="versionable"/>
    <table name="versionable_behavior_test_0">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <column name="bar" type="INTEGER"/>
    </table>
</database>
EOF;
        $expected = <<<EOF
-- ---------------------------------------------------------------------
-- versionable_behavior_test_0_version
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS versionable_behavior_test_0_version;

CREATE TABLE versionable_behavior_test_0_version
(
    id INTEGER NOT NULL,
    bar INTEGER,
    version INTEGER DEFAULT 0 NOT NULL,
    PRIMARY KEY (id,version),
    UNIQUE (id,version),
    FOREIGN KEY (id) REFERENCES versionable_behavior_test_0 (id)
        ON DELETE CASCADE
);
EOF;
        $builder = new QuickBuilder();
        $builder->setSchemaXml($schema);
        $this->assertStringContainsString($expected, $builder->buildSql());
    }

    /**
     * @return void
     */
    public function testIndicesParameter()
    {
        $schema = <<<EOF
<database name="versionable_behavior_test_0">
    <table name="versionable_behavior_test_0">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <column name="bar" type="INTEGER"/>
        <index>
            <index-column name="bar"/>
        </index>
        <behavior name="versionable">
            <parameter name="indices" value="true"/>
        </behavior>
    </table>
</database>
EOF;
        $expected = <<<EOF
CREATE TABLE versionable_behavior_test_0_version
(
    id INTEGER NOT NULL,
    bar INTEGER,
    version INTEGER DEFAULT 0 NOT NULL,
    PRIMARY KEY (id,version),
    UNIQUE (id,version),
    FOREIGN KEY (id) REFERENCES versionable_behavior_test_0 (id)
        ON DELETE CASCADE
);

CREATE INDEX versionable_behavior_test_0_version_i_14f552 ON versionable_behavior_test_0_version (bar);
EOF;
        $builder = new QuickBuilder();
        $builder->setSchemaXml($schema);
        $this->assertStringContainsString($expected, $builder->buildSql());
    }

    /**
     * @return void
     */
    public function testSkipSqlParameterOnParentTable()
    {
        $schema = <<<EOF
<database name="versionable_behavior_test_0">
    <table name="versionable_behavior_test_0" skipSql="true">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <column name="bar" type="INTEGER"/>
        <behavior name="versionable"/>
    </table>
</database>
EOF;

        $builder = new QuickBuilder();
        $builder->setSchemaXml($schema);

        $this->assertEmpty($builder->buildSql());
    }

    public static function tablePrefixSchemaDataProvider()
    {
        $schema = <<<XML
<database name="versionable_behavior_test_0" tablePrefix="prefix_">
    <table name="versionable_behavior_test_0">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <column name="bar" type="INTEGER"/>
        <behavior name="versionable"/>
    </table>
</database>
XML;

        return [[$schema]];
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('tablePrefixSchemaDataProvider')]
    public function testModifyTableAddsVersionColumnWithPrefix($schema)
    {
        $builder = new QuickBuilder();
        $builder->setSchemaXml($schema);
        $expected = <<<SQL
-- ---------------------------------------------------------------------
-- prefix_versionable_behavior_test_0
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS prefix_versionable_behavior_test_0;

CREATE TABLE prefix_versionable_behavior_test_0
(
    id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    bar INTEGER,
    version INTEGER DEFAULT 0,
    UNIQUE (id)
);
SQL;
        $this->assertStringContainsString($expected, $builder->buildSql());
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('tablePrefixSchemaDataProvider')]
    public function testModifyTableAddsVersionTableWithPrefix($schema)
    {
        $builder = new QuickBuilder();
        $builder->setSchemaXml($schema);
        $expected = <<<SQL
-- ---------------------------------------------------------------------
-- prefix_versionable_behavior_test_0_version
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS prefix_versionable_behavior_test_0_version;

CREATE TABLE prefix_versionable_behavior_test_0_version
(
    id INTEGER NOT NULL,
    bar INTEGER,
    version INTEGER DEFAULT 0 NOT NULL,
    PRIMARY KEY (id,version),
    UNIQUE (id,version),
    FOREIGN KEY (id) REFERENCES prefix_versionable_behavior_test_0 (id)
        ON DELETE CASCADE
);
SQL;
        $this->assertStringContainsString($expected, $builder->buildSql());
    }
}
