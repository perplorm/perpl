<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Platform;

use Propel\Generator\Config\GeneratorConfig;
use Propel\Generator\Platform\MysqlPlatform;
use Propel\Generator\Platform\PlatformInterface;

class MysqlPlatformMigrationMyISAMTest extends PlatformMigrationTestProvider
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
                'adapters.mysql.tableType' => 'MyISAM',
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
) ENGINE=MyISAM;

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
DROP INDEX `bar_baz_fk` ON `foo`;

DROP INDEX `foo1_fi_2` ON `foo`;

DROP INDEX `bar_fk` ON `foo`;

ALTER TABLE `foo`

  CHANGE `bar` `bar1` INTEGER,

  CHANGE `baz` `baz` VARCHAR(12) DEFAULT 'pdf;jpg;png;doc;docx;xls;xlsx;txt',

  ADD `baz3` TEXT AFTER `baz`;

CREATE INDEX `bar_fk` ON `foo` (`bar1`);

CREATE INDEX `baz_fk` ON `foo` (`baz3`);
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
        $expected = '';
        $this->assertEquals($expected, static::getPlatform()->getModifyTableForeignKeysDDL($tableDiff));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetModifyTableForeignKeysSkipSqlDDL')]
    public function testGetModifyTableForeignKeysSkipSqlDDL($tableDiff)
    {
        $expected = '';
        $this->assertEquals($expected, static::getPlatform()->getModifyTableForeignKeysDDL($tableDiff));
        $expected = '';
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
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetAddColumnsDDL')]
    public function testGetAddColumnsDDL($columns)
    {
        $expected = "
ALTER TABLE `foo` ADD `bar1` INTEGER AFTER `id`;

ALTER TABLE `foo` ADD `bar2` DOUBLE(3,2) DEFAULT -1 NOT NULL AFTER `bar1`;
";
        $this->assertEquals($expected, static::getPlatform()->getAddColumnsDDL($columns));
    }
}
