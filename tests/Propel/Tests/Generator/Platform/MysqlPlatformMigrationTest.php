<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Platform;

use Propel\Generator\Config\GeneratorConfig;
use Propel\Generator\Model\Diff\DatabaseComparator;
use Propel\Generator\Platform\MysqlPlatform;
use Propel\Generator\Platform\PlatformInterface;

class MysqlPlatformMigrationTest extends MysqlPlatformMigrationTestProvider
{
    protected static $platform;

    /**
     * Get the Platform object for this class
     *
     * @return \Propel\Generator\Platform\MysqlPlatform
     */
    protected static function getPlatform(): PlatformInterface
    {
        static::$platform ??= static::setUpPlatform();

        return static::$platform;
    }
    /**
     * Get the Platform object for this class
     *
     * @return \Propel\Generator\Platform\MysqlPlatform
     */
    protected static function setUpPlatform(): MysqlPlatform
    {
        $platform = new MysqlPlatform();
        $config = [
            'propel.database' => [
                'adapters.mysql.tableType' => 'InnoDB',
                'connections.bookstore' => [
                    'adapter' => 'mysql',
                    'classname' => '\\Propel\\Runtime\\Connection\\DebugPDO',
                    'dsn' => 'mysql:host=127.0.0.1;dbname=test',
                    'user' => 'root',
                    'password' => '',
                ]
            ],
            'propel.generator' => [
                'defaultConnection' => 'bookstore',
                'connections' => ['bookstore'],
            ],
            'propel.runtime' => [
                'defaultConnection' => 'bookstore',
                'connections' => ['bookstore'],
            ],
        ];

        $config = new GeneratorConfig(null, $config);
        $platform->setGeneratorConfig($config);

        return $platform;
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetModifyDatabaseDDL')]
    public function testRenameTableDDL($databaseDiff)
    {
        $expected = "
# This is a fix for InnoDB in MySQL >= 4.1.x
# It \"suspends judgement\" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `foo1`;

RENAME TABLE `foo3` TO `foo4`;

ALTER TABLE `foo2`

  CHANGE `bar` `bar1` INTEGER,

  CHANGE `baz` `baz` VARCHAR(12),

  ADD `baz3` TEXT AFTER `baz`;

CREATE TABLE `foo5`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `lkdjfsh` INTEGER,
    `dfgdsgf` TEXT,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
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
RENAME TABLE `foo1` TO `foo2`;
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
ALTER TABLE `foo` DROP FOREIGN KEY `foo1_fk_2`;

ALTER TABLE `foo` DROP FOREIGN KEY `foo1_fk_1`;

DROP INDEX `bar_baz_fk` ON `foo`;

DROP INDEX `foo1_fi_2` ON `foo`;

DROP INDEX `bar_fk` ON `foo`;

ALTER TABLE `foo`

  CHANGE `bar` `bar1` INTEGER,

  CHANGE `baz` `baz` VARCHAR(12) DEFAULT 'pdf;jpg;png;doc;docx;xls;xlsx;txt',

  ADD `baz3` TEXT AFTER `baz`;

CREATE INDEX `bar_fk` ON `foo` (`bar1`);

CREATE INDEX `baz_fk` ON `foo` (`baz3`);

ALTER TABLE `foo` ADD CONSTRAINT `foo1_fk_1`
    FOREIGN KEY (`bar1`)
    REFERENCES `foo2` (`bar`);
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
ALTER TABLE `foo` CHANGE `bar` `bar1` INTEGER;

ALTER TABLE `foo` CHANGE `baz` `baz` VARCHAR(12);

ALTER TABLE `foo` ADD `baz3` TEXT AFTER `baz`;
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
ALTER TABLE `foo` DROP PRIMARY KEY;

ALTER TABLE `foo` ADD PRIMARY KEY (`id`,`bar`);
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
DROP INDEX `bar_fk` ON `foo`;

DROP INDEX `bax_unique` ON `foo`;

CREATE INDEX `baz_fk` ON `foo` (`baz`);

CREATE UNIQUE INDEX `bax_bay_unique` ON `foo` (`bax`, `bay`);

DROP INDEX `bar_baz_fk` ON `foo`;

CREATE INDEX `bar_baz_fk` ON `foo` (`id`, `bar`, `baz`);
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
ALTER TABLE `foo1` DROP FOREIGN KEY `foo1_fk_1`;

ALTER TABLE `foo1` ADD CONSTRAINT `foo1_fk_3`
    FOREIGN KEY (`baz`)
    REFERENCES `foo2` (`baz`);

ALTER TABLE `foo1` DROP FOREIGN KEY `foo1_fk_2`;

ALTER TABLE `foo1` ADD CONSTRAINT `foo1_fk_2`
    FOREIGN KEY (`bar`,`id`)
    REFERENCES `foo2` (`bar`,`id`);
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
ALTER TABLE `foo1` DROP FOREIGN KEY `foo1_fk_1`;
";
        $this->assertEquals($expected, static::getPlatform()->getModifyTableForeignKeysDDL($tableDiff));
        $expected = "
ALTER TABLE `foo1` ADD CONSTRAINT `foo1_fk_1`
    FOREIGN KEY (`bar`)
    REFERENCES `foo2` (`bar`);
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
ALTER TABLE `foo` DROP `bar`;
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
ALTER TABLE `foo` CHANGE `bar1` `bar2` DOUBLE(2);
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
ALTER TABLE `foo` CHANGE `bar` `bar` DOUBLE(3);
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
ALTER TABLE `foo` CHANGE `bar1` `bar1` DOUBLE(3);

ALTER TABLE `foo` CHANGE `bar2` `bar2` INTEGER NOT NULL;
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
ALTER TABLE `foo` ADD `bar` INTEGER AFTER `id`;
";
        $this->assertEquals($expected, static::getPlatform()->getAddColumnDDL($column));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetAddColumnFirstDDL')]
    public function testGetAddColumnFirstDDL($column)
    {
        $expected = "
ALTER TABLE `foo` ADD `bar` INTEGER FIRST;
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
ALTER TABLE `foo` ADD `bar1` INTEGER AFTER `id`;

ALTER TABLE `foo` ADD `bar2` DOUBLE(3,2) DEFAULT -1 NOT NULL AFTER `bar1`;
";
        $this->assertEquals($expected, static::getPlatform()->getAddColumnsDDL($columns));
    }

    /**
     * @return void
     */
    public function testColumnRenaming()
    {
        $schema1 = '
<database name="test">
    <table name="foo">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <column name="bar1" type="INTEGER"/>
        <column name="bar2" type="INTEGER"/>
    </table>
</database>
';
        $schema2 = '
<database name="test">
    <table name="foo">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <column name="bar_la1" type="INTEGER"/>
        <column name="bar_la2" type="INTEGER"/>
    </table>
</database>
';

        $d1 = $this->getDatabaseFromSchema($schema1);
        $d2 = $this->getDatabaseFromSchema($schema2);

        $diff = DatabaseComparator::computeDiff($d1, $d2);

        $tables = $diff->getModifiedTables();
        $this->assertEquals('foo', key($tables));
        $fooChanges = array_shift($tables);
        $this->assertInstanceOf('\Propel\Generator\Model\Diff\TableDiff', $fooChanges);

        $renamedColumns = $fooChanges->getRenamedColumns();

        $firstPair = array_shift($renamedColumns);
        $secondPair = array_shift($renamedColumns);

        $this->assertEquals('bar1', $firstPair[0]->getName());
        $this->assertEquals('bar_la1', $firstPair[1]->getName());

        $this->assertEquals('bar2', $secondPair[0]->getName());
        $this->assertEquals('bar_la2', $secondPair[1]->getName());
    }

    /**
     * @return void
     */
    public function testTableRenaming()
    {
        $schema1 = '
<database name="test">
    <table name="foo">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <column name="bar1" type="INTEGER"/>
        <column name="bar2" type="INTEGER"/>
    </table>
    <table name="foo2">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <column name="bar1" type="INTEGER"/>
        <column name="bar2" type="INTEGER"/>
    </table>
</database>
';
        $schema2 = '
<database name="test">
    <table name="foo_bla">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <column name="bar1" type="INTEGER"/>
        <column name="bar2" type="INTEGER"/>
    </table>
    <table name="foo_bla2">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <column name="bar1" type="INTEGER"/>
        <column name="bar2" type="INTEGER"/>
    </table>
</database>
';

        $d1 = $this->getDatabaseFromSchema($schema1);
        $d2 = $this->getDatabaseFromSchema($schema2);

        $diff = DatabaseComparator::computeDiff($d1, $d2, false, true);
        $renamedTables = $diff->getRenamedTables();

        $firstPair = [key($renamedTables), current($renamedTables)];
        next($renamedTables);
        $secondPair = [key($renamedTables), current($renamedTables)];

        $this->assertEquals('foo', $firstPair[0]);
        $this->assertEquals('foo_bla', $firstPair[1]);

        $this->assertEquals('foo2', $secondPair[0]);
        $this->assertEquals(
            'foo_bla2',
            $secondPair[1],
            'Table `Foo2` should not renamed to `foo_bla` since we have already renamed a table to this name.'
        );
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestMigrateToUuidBinColumn')]
    public function testGetMigrateToUuidBinaryColumn($tableDiff)
    {
        $expected = <<<'EOT'

;--sql statement block;
# START migration of UUIDs in column 'foo.id'.
# This can break your DB. Validate and edit these statements as you see fit.
# Please be aware of Propel's ABSOLUTELY NO WARRANTY policy!

;--sql statement block;

ALTER TABLE `foo` DROP PRIMARY KEY;
ALTER TABLE `foo` ADD COLUMN `id_%x` BINARY(16) AFTER `id`;
UPDATE `foo` SET `id_%x` = UUID_TO_BIN(`id`, true);
ALTER TABLE `foo` DROP COLUMN `id`;
ALTER TABLE `foo` CHANGE COLUMN `id_%x` `id` BINARY(16) DEFAULT vendor_specific_uuid_generator_function() NOT NULL;
ALTER TABLE `foo` ADD PRIMARY KEY (`id`);
# END migration of UUIDs in column 'id'

;--sql statement block;

EOT;
        $actual = static::getPlatform()->getModifyTableColumnsDDL($tableDiff);
        $this->assertStringMatchesFormat($expected, $actual);
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestMigrateFromUuidBinColumn')]
    public function testGetMigrateFromUuidBinaryColumn($tableDiff)
    {
        $expected = <<<EOT

;--sql statement block;
# START migration of UUIDs in column 'foo.id'.
# This can break your DB. Validate and edit these statements as you see fit.
# Please be aware of Propel's ABSOLUTELY NO WARRANTY policy!

;--sql statement block;

ALTER TABLE `foo` DROP PRIMARY KEY;
ALTER TABLE `foo` ADD COLUMN `id_%x` VARCHAR(36) AFTER `id`;
UPDATE `foo` SET `id_%x` = BIN_TO_UUID(`id`, true);
ALTER TABLE `foo` DROP COLUMN `id`;
ALTER TABLE `foo` CHANGE COLUMN `id_%x` `id` VARCHAR(36) NOT NULL;
ALTER TABLE `foo` ADD PRIMARY KEY (`id`);
# END migration of UUIDs in column 'id'

;--sql statement block;
EOT;
        $actual = static::getPlatform()->getModifyTableColumnsDDL($tableDiff);
        $this->assertStringMatchesFormat($expected, $actual);
    }
}
