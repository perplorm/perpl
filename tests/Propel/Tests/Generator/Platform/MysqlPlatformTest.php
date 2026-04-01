<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Platform;

use Propel\Generator\Builder\Util\SchemaReader;
use Propel\Generator\Config\GeneratorConfig;
use Propel\Generator\Exception\EngineException;
use Propel\Generator\Model\Column;
use Propel\Generator\Model\ColumnDefaultValue;
use Propel\Generator\Model\IdMethod;
use Propel\Generator\Model\IdMethodParameter;
use Propel\Generator\Model\Index;
use Propel\Generator\Model\Table;
use Propel\Generator\Model\VendorInfo;
use Propel\Generator\Platform\MysqlPlatform;
use Propel\Generator\Model\PropelTypes;
use Propel\Generator\Platform\PlatformInterface;

class MysqlPlatformTest extends PlatformTestProvider
{
    /**
     * Get the Platform object for this class
     *
     * @return \Propel\Generator\Platform\MysqlPlatform
     */
    protected static function getPlatform():PlatformInterface
    {
        static $platform;

        if (!$platform) {
            $platform = new MysqlPlatform();

            $configProp['propel.database.adapters.mysql.tableType'] = 'InnoDB';
            $config = new GeneratorConfig(__DIR__ . '/../../../../Fixtures/bookstore', $configProp);

            $platform->setGeneratorConfig($config);
        }

        return $platform;
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
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetAddTablesDDLSchema')]
    public function testGetAddTablesDDLSchema($schema)
    {
        $database = $this->getDatabaseFromSchema($schema);
        $expected = <<<EOF

# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- x.book
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `x`.`book`;

CREATE TABLE `x`.`book`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `author_id` INTEGER,
    PRIMARY KEY (`id`),
    INDEX `book_i_639136` (`title`),
    INDEX `book_fi_4444ca` (`author_id`),
    CONSTRAINT `book_fk_4444ca`
        FOREIGN KEY (`author_id`)
        REFERENCES `y`.`author` (`id`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- y.author
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `y`.`author`;

CREATE TABLE `y`.`author`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `first_name` VARCHAR(100),
    `last_name` VARCHAR(100),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- x.book_summary
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `x`.`book_summary`;

CREATE TABLE `x`.`book_summary`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `book_id` INTEGER NOT NULL,
    `summary` TEXT NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `book_summary_fi_23450f` (`book_id`),
    CONSTRAINT `book_summary_fk_23450f`
        FOREIGN KEY (`book_id`)
        REFERENCES `x`.`book` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;

EOF;
        $this->assertEquals($expected, static::getPlatform()->getAddTablesDDL($database));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetAddTablesDDL')]
    public function testGetAddTablesDDL($schema)
    {
        $database = $this->getDatabaseFromSchema($schema);
        $expected = <<<EOF

# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- book
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `book`;

CREATE TABLE `book`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `author_id` INTEGER,
    PRIMARY KEY (`id`),
    INDEX `book_i_639136` (`title`),
    INDEX `book_fi_ea464c` (`author_id`),
    CONSTRAINT `book_fk_ea464c`
        FOREIGN KEY (`author_id`)
        REFERENCES `author` (`id`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- author
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `author`;

CREATE TABLE `author`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `first_name` VARCHAR(100),
    `last_name` VARCHAR(100),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;

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
        $expected = '';
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
CREATE TABLE `foo`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `bar` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB COMMENT='This is foo table';
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
CREATE TABLE `foo`
(
    `foo` INTEGER NOT NULL,
    `bar` INTEGER NOT NULL,
    `baz` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`foo`,`bar`)
) ENGINE=InnoDB;
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
CREATE TABLE `foo`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `bar` INTEGER,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `foo_u_14f552` (`bar`)
) ENGINE=InnoDB;
";
        $this->assertEquals($expected, static::getPlatform()->getAddTableDDL($table));
    }

    /**
     * @return void
     */
    public function testGetAddTableDDLIndex()
    {
        $schema = <<<EOF
<database name="test" identifierQuoting="true">
    <table name="foo">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <column name="bar" type="INTEGER"/>
        <index>
            <index-column name="bar"/>
        </index>
    </table>
</database>
EOF;
        $table = $this->getTableFromSchema($schema);
        $expected = "
CREATE TABLE `foo`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `bar` INTEGER,
    PRIMARY KEY (`id`),
    INDEX `foo_i_14f552` (`bar`)
) ENGINE=InnoDB;
";
        $this->assertEquals($expected, static::getPlatform()->getAddTableDDL($table));
    }

    /**
     * @return void
     */
    public function testGetAddTableDDLForeignKey()
    {
        $schema = <<<EOF
<database name="test" identifierQuoting="true">
    <table name="foo">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <column name="bar_id" type="INTEGER"/>
        <foreign-key foreignTable="bar">
            <reference local="bar_id" foreign="id"/>
        </foreign-key>
    </table>
    <table name="bar">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
    </table>
</database>
EOF;
        $table = $this->getTableFromSchema($schema);
        $expected = "
CREATE TABLE `foo`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `bar_id` INTEGER,
    PRIMARY KEY (`id`),
    INDEX `foo_fi_426410` (`bar_id`),
    CONSTRAINT `foo_fk_426410`
        FOREIGN KEY (`bar_id`)
        REFERENCES `bar` (`id`)
) ENGINE=InnoDB;
";
        $this->assertEquals($expected, static::getPlatform()->getAddTableDDL($table));
    }

    /**
     * @return void
     */
    public function testGetAddTableDDLForeignKeySkipSql()
    {
        $schema = <<<EOF
<database name="test" identifierQuoting="true">
    <table name="foo">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <column name="bar_id" type="INTEGER"/>
        <foreign-key foreignTable="bar" skipSql="true">
            <reference local="bar_id" foreign="id"/>
        </foreign-key>
    </table>
    <table name="bar">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
    </table>
</database>
EOF;
        $table = $this->getTableFromSchema($schema);
        $expected = "
CREATE TABLE `foo`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `bar_id` INTEGER,
    PRIMARY KEY (`id`),
    INDEX `foo_fi_426410` (`bar_id`)
) ENGINE=InnoDB;
";
        $this->assertEquals($expected, static::getPlatform()->getAddTableDDL($table));
    }

    /**
     * @return void
     */
    public function testGetAddTableDDLEngine()
    {
        $schema = <<<EOF
<database name="test" identifierQuoting="true">
    <table name="foo">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
    </table>
</database>
EOF;
        $platform = new MysqlPlatform();
        $platform->setTableEngineKeyword('TYPE');
        $platform->setDefaultTableEngine('MEMORY');
        $xtad = new SchemaReader($platform);
        $appData = $xtad->parseString($schema);
        $table = $appData->getDatabase()->getTable('foo');
        $expected = "
CREATE TABLE `foo`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (`id`)
) TYPE=MEMORY;
";
        $this->assertEquals($expected, $platform->getAddTableDDL($table));
    }

    /**
     * @return void
     */
    public function testGetAddTableDDLVendor()
    {
        $schema = <<<EOF
<database name="test" identifierQuoting="true">
    <table name="foo">
        <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <vendor type="mysql">
            <parameter name="Engine" value="InnoDB"/>
            <parameter name="Charset" value="utf8"/>
            <parameter name="AutoIncrement" value="1000"/>
        </vendor>
    </table>
</database>
EOF;
        $table = $this->getTableFromSchema($schema);
        $expected = "
CREATE TABLE `foo`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1000 CHARACTER SET='utf8';
";
        $this->assertEquals($expected, static::getPlatform()->getAddTableDDL($table));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetAddTableDDLSchema')]
    public function testGetAddTableDDLSchema($schema)
    {
        $table = $this->getTableFromSchema($schema, 'Woopah.foo');
        $expected = "
CREATE TABLE `Woopah`.`foo`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `bar` INTEGER,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;
";
        $this->assertEquals($expected, static::getPlatform()->getAddTableDDL($table));
    }

    /**
     * @return void
     */
    public function testGetDropTableDDL()
    {
        $table = new Table('foo');
        $table->setIdentifierQuoting(true);
        $expected = "
DROP TABLE IF EXISTS `foo`;
";
        $this->assertEquals($expected, static::getPlatform()->getDropTableDDL($table));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetAddTableDDLSchema')]
    public function testGetDropTableDDLSchema($schema)
    {
        $table = $this->getTableFromSchema($schema, 'Woopah.foo');
        $expected = "
DROP TABLE IF EXISTS `Woopah`.`foo`;
";
        $this->assertEquals($expected, static::getPlatform()->getDropTableDDL($table));
    }

    /**
     * @return void
     */
    public function testGetColumnDDL()
    {
        $column = new Column('foo');
        $column->getDomain()->copy(static::getPlatform()->getDomainForType('DOUBLE'));
        $column->getDomain()->replaceScale(2);
        $column->getDomain()->replaceSize(3);
        $column->setNotNull(true);
        $column->getDomain()->createDefaultValue(123);
        $expected = '`foo` DOUBLE(3,2) DEFAULT 123 NOT NULL';
        $this->assertEquals($expected, static::getPlatform()->getColumnDDL($column));
    }

    /**
     * @return array{string, bool, string}[]
     */
    public static function IntegerTypesDataProvider(): array
    {
        $integerTypes = [PropelTypes::INTEGER, PropelTypes::BIGINT, PropelTypes::SMALLINT, PropelTypes::TINYINT];

        return array_merge([
                [PropelTypes::DECIMAL, true, "`foo` DECIMAL(3)"],
                [PropelTypes::DECIMAL, false, "`foo` DECIMAL(3)"],
            ],
            array_map(fn($type) => [$type, true, "`foo` $type"], $integerTypes),
            array_map(fn($type) => [$type, false, "`foo` {$type}(3)"], $integerTypes),
            
        );
    }

    /**
     *
     * @param string $integerType
     * @param bool $ignoreSize
     * @param string $expectedDdl
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('IntegerTypesDataProvider')]
    public function testGetColumnDDLIgnoresSizeOnInteger(string $integerType, bool $ignoreSize, string $expectedDdl)
    {
        $platform = new MysqlPlatform();
        $this->setObjectPropertyValue($platform, 'ignoreSizeOnIntegerTypes', $ignoreSize);
        $domain = clone $platform->getDomainForType($integerType);
        $domain->replaceSize(3);

        $column = new Column('foo');
        $column->setDomain($domain);

        $actual = $platform->getColumnDDL($column);

        $this->assertEquals($expectedDdl, $actual);
    }


    /**
     * @return void
     */
    public function testGetColumnDDLTextDefaultValue(): void
    {
        $column = new Column('foo');
        $column->getDomain()->copy(static::getPlatform()->getDomainForType(PropelTypes::LONGVARCHAR));
        $column->getDomain()->setDefaultValue(new ColumnDefaultValue('hello', ColumnDefaultValue::TYPE_VALUE));
        $expected = '`foo` TEXT DEFAULT \'hello\'';
        $this->assertEquals($expected, static::getPlatform()->getColumnDDL($column));
    }

    /**
     * @return void
     */
    public function testGetColumnDDLBlobDefaultValueThrowsException(): void
    {
        $this->expectException(EngineException::class);
        $this->expectExceptionMessage('BLOB columns cannot have DEFAULT values in MySQL.');

        $column = new Column('bar');
        $column->getDomain()->copy(static::getPlatform()->getDomainForType(PropelTypes::BLOB));
        $column->getDomain()->setDefaultValue(new ColumnDefaultValue('data', ColumnDefaultValue::TYPE_VALUE));
        static::getPlatform()->getColumnDDL($column);
    }

    /**
     * @return void
     */
    public function testGetColumnDDLCharsetVendor()
    {
        $column = new Column('foo');
        $column->getDomain()->copy(static::getPlatform()->getDomainForType('LONGVARCHAR'));
        $vendor = new VendorInfo('mysql');
        $vendor->setParameter('Charset', 'greek');
        $column->addVendorInfo($vendor);
        $expected = '`foo` TEXT CHARACTER SET \'greek\'';
        $this->assertEquals($expected, static::getPlatform()->getColumnDDL($column));
    }

    /**
     * @return void
     */
    public function testGetColumnDDLCharsetCollation()
    {
        $column = new Column('foo');
        $column->getDomain()->copy(static::getPlatform()->getDomainForType('LONGVARCHAR'));
        $vendor = new VendorInfo('mysql');
        $vendor->setParameter('Collate', 'latin1_german2_ci');
        $column->addVendorInfo($vendor);
        $expected = '`foo` TEXT COLLATE \'latin1_german2_ci\'';
        $this->assertEquals($expected, static::getPlatform()->getColumnDDL($column));

        $column = new Column('foo');
        $column->getDomain()->copy(static::getPlatform()->getDomainForType('LONGVARCHAR'));
        $vendor = new VendorInfo('mysql');
        $vendor->setParameter('Collation', 'latin1_german2_ci');
        $column->addVendorInfo($vendor);
        $expected = '`foo` TEXT COLLATE \'latin1_german2_ci\'';
        $this->assertEquals($expected, static::getPlatform()->getColumnDDL($column));
    }

    /**
     * @return void
     */
    public function testGetColumnDDLComment()
    {
        $column = new Column('foo');
        $column->getDomain()->copy(static::getPlatform()->getDomainForType('INTEGER'));
        $column->setDescription('This is column Foo');
        $expected = '`foo` INTEGER COMMENT \'This is column Foo\'';
        $this->assertEquals($expected, static::getPlatform()->getColumnDDL($column));
    }

    /**
     * @return void
     */
    public function testGetColumnDDLCharsetNotNull()
    {
        $column = new Column('foo');
        $column->getDomain()->copy(static::getPlatform()->getDomainForType('LONGVARCHAR'));
        $column->setNotNull(true);
        $vendor = new VendorInfo('mysql');
        $vendor->setParameter('Charset', 'greek');
        $column->addVendorInfo($vendor);
        $expected = '`foo` TEXT CHARACTER SET \'greek\' NOT NULL';
        $this->assertEquals($expected, static::getPlatform()->getColumnDDL($column));
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
        $expected = '`foo` DECIMAL(5,6) DEFAULT 123 NOT NULL';
        $this->assertEquals($expected, static::getPlatform()->getColumnDDL($column));
    }

    /**
     * @return void
     */
    public function testGetPrimaryKeyDDLSimpleKey()
    {
        $table = new Table('foo');
        $table->setIdentifierQuoting(true);
        $column = new Column('bar');
        $column->setPrimaryKey(true);
        $table->addColumn($column);
        $expected = 'PRIMARY KEY (`bar`)';
        $this->assertEquals($expected, static::getPlatform()->getPrimaryKeyDDL($table));
    }

    /**
     * @return void
     */
    public function testGetPrimaryKeyDDLCompositeKey()
    {
        $table = new Table('foo');
        $table->setIdentifierQuoting(true);
        $column1 = new Column('bar1');
        $column1->setPrimaryKey(true);
        $table->addColumn($column1);
        $column2 = new Column('bar2');
        $column2->setPrimaryKey(true);
        $table->addColumn($column2);
        $expected = 'PRIMARY KEY (`bar1`,`bar2`)';
        $this->assertEquals($expected, static::getPlatform()->getPrimaryKeyDDL($table));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestPrimaryKeyDDL')]
    public function testGetDropPrimaryKeyDDL($table)
    {
        $expected = "
ALTER TABLE `foo` DROP PRIMARY KEY;
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
ALTER TABLE `foo` ADD PRIMARY KEY (`bar`);
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
CREATE INDEX `babar` ON `foo` (`bar1`, `bar2`);

CREATE INDEX `foo_index` ON `foo` (`bar1`);
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
CREATE INDEX `babar` ON `foo` (`bar1`, `bar2`);
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
DROP INDEX `babar` ON `foo`;
";
        $this->assertEquals($expected, static::getPlatform()->getDropIndexDDL($index));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetIndexDDL')]
    public function testGetIndexDDL($index)
    {
        $expected = 'INDEX `babar` (`bar1`, `bar2`)';
        $this->assertEquals($expected, static::getPlatform()->getIndexDDL($index));
    }

    /**
     * @return void
     */
    public function testGetIndexDDLKeySize()
    {
        $table = new Table('foo');
        $table->setIdentifierQuoting(true);
        $column1 = new Column('bar1');
        $column1->getDomain()->copy(static::getPlatform()->getDomainForType('VARCHAR'));
        $column1->setSize(5);
        $table->addColumn($column1);
        $index = new Index('bar_index');
        $index->addColumn($column1);
        $table->addIndex($index);
        $expected = 'INDEX `bar_index` (`bar1`(5))';
        $this->assertEquals($expected, static::getPlatform()->getIndexDDL($index));
    }

    /**
     * @return void
     */
    public function testGetIndexDDLFulltext()
    {
        $table = new Table('foo');
        $table->setIdentifierQuoting(true);
        $column1 = new Column('bar1');
        $column1->getDomain()->copy(static::getPlatform()->getDomainForType('LONGVARCHAR'));
        $table->addColumn($column1);
        $index = new Index('bar_index');
        $index->addColumn($column1);
        $vendor = new VendorInfo('mysql');
        $vendor->setParameter('Index_type', 'FULLTEXT');
        $index->addVendorInfo($vendor);
        $table->addIndex($index);
        $expected = 'FULLTEXT INDEX `bar_index` (`bar1`)';
        $this->assertEquals($expected, static::getPlatform()->getIndexDDL($index));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetUniqueDDL')]
    public function testGetUniqueDDL($index)
    {
        $expected = 'UNIQUE INDEX `babar` (`bar1`, `bar2`)';
        $this->assertEquals($expected, static::getPlatform()->getUniqueDDL($index));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestGetForeignKeysDDL')]
    public function testGetAddForeignKeysDDL($table)
    {
        $expected = "
ALTER TABLE `foo` ADD CONSTRAINT `foo_bar_fk`
    FOREIGN KEY (`bar_id`)
    REFERENCES `bar` (`id`)
    ON DELETE CASCADE;

ALTER TABLE `foo` ADD CONSTRAINT `foo_baz_fk`
    FOREIGN KEY (`baz_id`)
    REFERENCES `baz` (`id`)
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
ALTER TABLE `foo` ADD CONSTRAINT `foo_bar_fk`
    FOREIGN KEY (`bar_id`)
    REFERENCES `bar` (`id`)
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
ALTER TABLE `foo` DROP FOREIGN KEY `foo_bar_fk`;
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
        $expected = "CONSTRAINT `foo_bar_fk`
    FOREIGN KEY (`bar_id`)
    REFERENCES `bar` (`id`)
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
-- ---------------------------------------------------------------------
-- foo bar
-- ---------------------------------------------------------------------
";
        $this->assertEquals($expected, static::getPlatform()->getCommentBlockDDL('foo bar'));
    }

    /**
     * @return void
     */
    public function testAddExtraIndicesForeignKeys()
    {
        $schema = '
<database name="test1" identifierQuoting="true">
  <table name="foo">
    <column name="subid" type="INTEGER"/>
    <column name="id" type="INTEGER"/>
  </table>
  <table name="bar">

    <column name="subid" type="INTEGER"/>
    <column name="id" type="INTEGER"/>

    <foreign-key foreignTable="foo">
      <reference local="id" foreign="id"/>
      <reference local="subid" foreign="subid"/>
    </foreign-key>
  </table>
</database>
';

        $expectedRelationSql = "
CREATE TABLE `bar`
(
    `subid` INTEGER,
    `id` INTEGER,
    INDEX `bar_fi_bb8268` (`id`, `subid`),
    CONSTRAINT `bar_fk_bb8268`
        FOREIGN KEY (`id`,`subid`)
        REFERENCES `foo` (`id`,`subid`)
) ENGINE=InnoDB;
";

        $table = $this->getDatabaseFromSchema($schema)->getTable('bar');
        $relationTableSql = static::getPlatform()->getAddTableDDL($table);

        $this->assertEquals($expectedRelationSql, $relationTableSql);
    }

    /**
     * @return void
     */
    public function testGetAddTableDDLComplexPK()
    {
        $schema = <<<EOF
<database name="test" identifierQuoting="true">
    <table name="foo">
        <column name="id" primaryKey="true" type="INTEGER"/>
        <column name="second_id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
        <column name="third_id" primaryKey="true" type="INTEGER"/>
        <column name="bar" type="VARCHAR" size="255"/>
    </table>
</database>
EOF;
        $table = $this->getTableFromSchema($schema);
        $expected = "
CREATE TABLE `foo`
(
    `id` INTEGER NOT NULL,
    `second_id` INTEGER NOT NULL AUTO_INCREMENT,
    `third_id` INTEGER NOT NULL,
    `bar` VARCHAR(255),
    PRIMARY KEY (`second_id`,`id`,`third_id`)
) ENGINE=InnoDB;
";
        $this->assertEquals($expected, static::getPlatform()->getAddTableDDL($table));
    }

    /**
     * @return void
     */
    public function testVendorOptionsQuoting()
    {
        $schema = <<<EOF
<database name="test" identifierQuoting="true">
    <table name="foo">
        <column name="id" primaryKey="true" type="INTEGER"/>
        <vendor type="mysql">
            <parameter name="AutoIncrement" value="100"/>
            <parameter name="AvgRowLength" value="50"/>
            <parameter name="Charset" value="utf8"/>
            <parameter name="Checksum" value="1"/>
            <parameter name="Collate" value="utf8_unicode_ci"/>
            <parameter name="Connection" value="mysql://foo@bar.host:9306/federated/test_table"/>
            <parameter name="DataDirectory" value="/tmp/mysql-foo-table/"/>
            <parameter name="DelayKeyWrite" value="1"/>
            <parameter name="IndexDirectory" value="/tmp/mysql-foo-table-idx/"/>
            <parameter name="InsertMethod" value="LAST"/>
            <parameter name="KeyBlockSize" value="5"/>
            <parameter name="MaxRows" value="5000"/>
            <parameter name="MinRows" value="0"/>
            <parameter name="Pack_Keys" value="DEFAULT"/>
            <parameter name="PackKeys" value="1"/>
            <parameter name="RowFormat" value="COMPRESSED"/>
            <parameter name="Union" value="other_table"/>
        </vendor>
    </table>
</database>
EOF;
        $table = $this->getTableFromSchema($schema);
        $expected = "
CREATE TABLE `foo`
(
    `id` INTEGER NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=100 AVG_ROW_LENGTH=50 CHARACTER SET='utf8' CHECKSUM=1 COLLATE='utf8_unicode_ci' CONNECTION='mysql://foo@bar.host:9306/federated/test_table' DATA DIRECTORY='/tmp/mysql-foo-table/' DELAY_KEY_WRITE=1 INDEX DIRECTORY='/tmp/mysql-foo-table-idx/' INSERT_METHOD=LAST KEY_BLOCK_SIZE=5 MAX_ROWS=5000 MIN_ROWS=0 PACK_KEYS=DEFAULT PACK_KEYS=1 ROW_FORMAT=COMPRESSED UNION='other_table';
";
        $this->assertEquals($expected, static::getPlatform()->getAddTableDDL($table));
    }

    /**
     * @return void
     */
    public function testNormalizeTable()
    {
        $column = new Column('price', 'DECIMAL');
        $column->getDomain()->copy(static::getPlatform()->getDomainForType('DECIMAL'));
        $column->setSize(10);
        $column->setScale(3);
        $table = new Table('prices');
        $table->addColumns([$column]);
        static::getPlatform()->normalizeTable($table);
        $this->assertEquals('`price` DECIMAL(10,3)', static::getPlatform()->getColumnDDL($column));
    }
    
    public static function typeMappingDataProvider()
    {
        return [
            [PropelTypes::DATETIME, 'DATETIME'],
            [PropelTypes::TIMESTAMP, 'TIMESTAMP'],
        ];
    }
    
    #[\PHPUnit\Framework\Attributes\DataProvider('typeMappingDataProvider')]
    public function testTypeMapping(string $propelDataType, string $expectedMysqlDataType){
        $actualMysqlDataType = static::getPlatform()->getDomainForType($propelDataType)->getSqlType();
        $this->assertEquals($expectedMysqlDataType, $actualMysqlDataType);
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForTestCreateSchemaWithUuidColumns')]
    public function testCreateSchemaWithUuidColumns($schema)
    {
        $expected = "
CREATE TABLE `foo`
(
    `uuid` BINARY(16) DEFAULT vendor_specific_default() NOT NULL,
    `other_uuid` BINARY(16),
    PRIMARY KEY (`uuid`)
) ENGINE=InnoDB;
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
CREATE TABLE `foo`
(
    `uuid-bin` BINARY(16) DEFAULT vendor_specific_default() NOT NULL,
    `other_uuid-bin` BINARY(16),
    PRIMARY KEY (`uuid-bin`)
) ENGINE=InnoDB;
";

        $this->assertCreateTableMatches($expected, $schema);
    }

    /**
     * @return void
     */
    public function testUuidColumnTypeDefaultsToBinary()
    {
        $platform = new MysqlPlatform();

        $uuidSqlType = $platform->getDomainForType(PropelTypes::UUID)->getSqlType();
        $this->assertEquals(PropelTypes::BINARY, $uuidSqlType);
    }

    /**
     * @return void
     */
    public function testEnableUuidNativeType()
    {
        $platform = new MysqlPlatform();

        $configProp['propel.database.adapters.mysql.uuidColumnType'] = 'native';
        $config = new GeneratorConfig(__DIR__ . '/../../../../Fixtures/bookstore', $configProp);
        $platform->setGeneratorConfig($config);

        $uuidSqlType = $platform->getDomainForType(PropelTypes::UUID)->getSqlType();
        $this->assertEquals(PropelTypes::UUID, $uuidSqlType);
    }
}
