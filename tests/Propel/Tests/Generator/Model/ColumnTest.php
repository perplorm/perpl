<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Model;

use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Propel\Generator\Exception\EngineException;
use Propel\Generator\Exception\SchemaException;
use Propel\Generator\Model\Column;
use Propel\Generator\Model\Database;
use Propel\Generator\Model\Datatype\ColumnType;
use Propel\Generator\Model\IdMethod;
use Propel\Generator\Model\Table;
use Propel\Generator\Model\TypeMapping;
use Propel\Generator\Platform\DefaultPlatform;
use Propel\Generator\Platform\MysqlPlatform;
use Propel\Tests\Helpers\ColorsBackedEnum;
use Propel\Tests\Helpers\ColorsUnitEnum;
use Propel\Tests\TestCase;
use const PHP_INT_SIZE;

/**
 * Tests for package handling.
 *
 * @author Hugo Hamon <webmaster@apprendre-php.com>
 */
class ColumnTest extends ModelTestCase
{
    /**
     * @return void
     */
    public function testCreateNewColumn()
    {
        $column = new Column('title');

        $this->assertSame('title', $column->getName());
        $this->assertEmpty($column->buildAutoIncrementString());
        $this->assertSame('COL_TITLE', $column->getConstantName());
        $this->assertSame('public', $column->getMutatorVisibility());
        $this->assertSame('public', $column->getAccessorVisibility());
        $this->assertNull($column->getSize());
        $this->assertFalse($column->hasPlatform());
        $this->assertFalse($column->hasReferrers());
        $this->assertFalse($column->isAutoIncrement());
        $this->assertFalse($column->isEnumeratedClasses());
        $this->assertFalse($column->isLazyLoad());
        $this->assertFalse($column->isNamePlural());
        $this->assertFalse($column->isNestedSetLeftKey());
        $this->assertFalse($column->isNestedSetRightKey());
        $this->assertFalse($column->isNotNull());
        $this->assertFalse($column->isNodeKey());
        $this->assertFalse($column->isPrimaryKey());
        $this->assertFalse($column->isPrimaryString());
        $this->assertFalse($column->isTreeScopeKey());
        $this->assertFalse($column->isUnique());
    }

    /**
     * @return void
     */
    public function testSetupObjectWithoutPlatformTypeAndDomain()
    {
        $database = $this->getDatabaseMock('bookstore');

        $table = $this->getTableMock('books', ['database' => $database]);

        $column = new Column('');
        $column->setTable($table);
        $column->loadMapping(['name' => 'title']);

        $this->assertSame('title', $column->getName());
        $this->assertSame(ColumnType::VARCHAR, $column->getTypeMapping()->getColumnType());
    }

    /**
     * @return void
     */
    public function testSetupObjectWithPlatformOnly()
    {
        $database = $this->getDatabaseMock('bookstore');
        $platform = $this->getPlatformMock();
        $platform = new DefaultPlatform();

        $table = $this->getTableMock('books', [
            'database' => $database,
            'platform' => $platform,
        ]);

        $domain = $this->getDomainMock('VARCHAR');
        $domain
            ->expects($this->any())
            ->method('getColumnType')
            ->will($this->returnValue(ColumnType::VARCHAR));

        $column = new Column('');
        $column->setTable($table);
        $column->setTypeMapping($domain);
        $column->loadMapping(['name' => 'title']);

        $this->assertSame('title', $column->getName());
    }

    /**
     * @return void
     */
    public function testSetupObjectWithPlatformAndType()
    {
        $database = $this->getDatabaseMock('bookstore');
        $platform = new DefaultPlatform();

        $table = $this->getTableMock('books', [
            'database' => $database,
            'platform' => $platform,
        ]);

        $domain = $this->getDomainMock('VARCHAR');
        $domain
            ->expects($this->any())
            ->method('getColumnType')
            ->will($this->returnValue(ColumnType::DATE));

        $column = new Column('');
        $column->setTable($table);
        $column->setTypeMapping($domain);
        $column->loadMapping([
            'type' => 'date',
            'name' => 'created_at',
            'defaultExpr' => 'NOW()',
        ]);

        $this->assertSame('created_at', $column->getName());
    }

    /**
     * @return void
     */
    public function testSetupObjectWithDomain()
    {
        $database = $this->getDatabaseMock('bookstore');
        $database
            ->expects($this->once())
            ->method('getTypeMapping')
            ->with($this->equalTo('BOOLEAN'))
            ->will($this->returnValue(new TypeMapping(ColumnType::INTEGER)));

        $table = $this->getTableMock('books', ['database' => $database]);

        $column = new Column('');
        $column->setTable($table);
        $column->loadMapping([
            'domain' => 'BOOLEAN',
            'name' => 'is_published',
            'phpName' => 'IsPublished',
            'phpType' => 'boolean',
            'tableMapName' => 'IS_PUBLISHED',
            'prefix' => 'col_',
            'accessorVisibility' => 'public',
            'mutatorVisibility' => 'public',
            'primaryString' => 'false',
            'primaryKey' => 'false',
            'nodeKey' => 'false',
            'nestedSetLeftKey' => 'false',
            'nestedSetRightKey' => 'false',
            'treeScopeKey' => 'false',
            'required' => 'false',
            'autoIncrement' => 'false',
            'lazyLoad' => 'true',
            'sqlType' => 'TINYINT',
            'size' => 1,
            'defaultValue' => 'true',
            'valueSet' => 'FOO, BAR, BAZ',
        ]);

        $this->assertSame('is_published', $column->getName());
        $this->assertSame('IsPublished', $column->getPhpName());
        $this->assertSame('boolean', $column->getPhpType());
        $this->assertSame('IS_PUBLISHED', $column->getCustomColumnIdentifier());
        $this->assertSame('public', $column->getAccessorVisibility());
        $this->assertSame('public', $column->getMutatorVisibility());
        $this->assertFalse($column->isPrimaryString());
        $this->assertFalse($column->isPrimaryKey());
        $this->assertFalse($column->isNodeKey());
        $this->assertFalse($column->isNestedSetLeftKey());
        $this->assertFalse($column->isNestedSetRightKey());
        $this->assertFalse($column->isTreeScopeKey());
        $this->assertTrue($column->isLazyLoad());
        $this->assertCount(3, $column->getValueSet());
    }

    /**
     * @return void
     */
    public function testSetPosition()
    {
        $column = new Column('');
        $column->setPosition(2);

        $this->assertSame(2, $column->getPosition());
    }

    /**
     * @return void
     */
    public function testGetNullDefaultValueString()
    {
        $domain = $this->getDomainMock();
        $domain
            ->expects($this->any())
            ->method('getDefaultValue')
            ->will($this->returnValue(null));

        $column = new Column('');
        $column->setTypeMapping($domain);

        $this->assertSame('null', $column->getDefaultValueString());
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideDefaultValues')]
    public function testGetDefaultValueString($mappingType, $value, $expected)
    {
        $defaultValue = $this
            ->getMockBuilder('Propel\Generator\Model\ColumnDefaultValue')
            ->disableOriginalConstructor()
            ->getMock();

        $defaultValue
            ->expects($this->any())
            ->method('getValue')
            ->will($this->returnValue((string)$value));

        $domain = $this->getDomainMock();
        $domain
            ->expects($this->any())
            ->method('getDefaultValue')
            ->will($this->returnValue($defaultValue));
        $domain
            ->expects($this->any())
            ->method('setDefaultValue');
        $domain
            ->expects($this->any())
            ->method('getColumnType')
            ->will($this->returnValue($mappingType));

        $column = new Column('');
        $column->setTypeMapping($domain);
        $column->setDefaultValue('foo');          // Test with a scalar
        $column->setDefaultValue($defaultValue);  // Test with an object

        $this->assertSame($expected, $column->getDefaultValueString());
    }

    public static function provideDefaultValues()
    {
        return [
            [ColumnType::DOUBLE, 3.14, '3.14'],
            [ColumnType::VARCHAR, 'hello', "'hello'"],
            [ColumnType::VARCHAR, "john's bike", "'john\\'s bike'"],
            [ColumnType::BOOLEAN, 1, 'true'],
            [ColumnType::BOOLEAN, 0, 'false'],
            [ColumnType::ENUM, 'foo,bar', "'foo,bar'"],
        ];
    }

    /**
     * @return void
     */
    public function testAddInheritance()
    {
        $column = new Column('');

        $inheritance = $this
            ->getMockBuilder('Propel\Generator\Model\Inheritance')
            ->disableOriginalConstructor()
            ->getMock();
        $inheritance
            ->expects($this->any())
            ->method('setColumn')
            ->with($this->equalTo($column));

        $column->addInheritance($inheritance);

        $this->assertTrue($column->isEnumeratedClasses());
        $this->assertCount(1, $column->getChildren());

        $column->clearInheritanceList();
        $this->assertCount(0, $column->getChildren());
    }

    /**
     * @return void
     */
    public function testAddArrayInheritance()
    {
        $column = new Column('');

        $column->addInheritance([
            'key' => 'baz',
            'extends' => 'BaseObject',
            'class' => 'Foo\Bar',
            'package' => 'Foo',
        ]);

        $column->addInheritance([
            'key' => 'foo',
            'extends' => 'BaseObject',
            'class' => 'Acme\Foo',
            'package' => 'Acme',
        ]);

        $this->assertCount(2, $column->getChildren());
    }

    /**
     * @return void
     */
    public function testClearForeignKeys()
    {
        $fks = [
            $this->getMockBuilder('Propel\Generator\Model\ForeignKey')->getMock(),
            $this->getMockBuilder('Propel\Generator\Model\ForeignKey')->getMock(),
        ];

        $table = $this->getTableMock('books');
        $table
            ->expects($this->any())
            ->method('getColumnForeignKeys')
            ->with('author_id')
            ->will($this->returnValue($fks));

        $column = new Column('author_id');
        $column->setTable($table);
        $column->addReferrer($fks[0]);
        $column->addReferrer($fks[1]);

        $this->assertTrue($column->isForeignKey());
        $this->assertTrue($column->hasMultipleFK());
        $this->assertTrue($column->hasReferrers());
        $this->assertTrue($column->hasReferrer($fks[0]));
        $this->assertCount(2, $column->getReferrers());

        // Clone the current column
        $clone = clone $column;

        $column->clearReferrers();
        $this->assertCount(0, $column->getReferrers());
        $this->assertCount(0, $clone->getReferrers());
    }

    /**
     * @return void
     */
    public function testIsDefaultSqlTypeFromDomain()
    {
        $platform = new MysqlPlatform();

        $column = new Column('');
        $column->setTable($this->getTableMock('books', [
            'platform' => $platform,
        ]));
        $column->setUpTypeMapping(ColumnType::BOOLEAN);

        $this->assertTrue($column->isDefaultSqlType($platform));
    }

    /**
     * @return void
     */
    public function testIsDefaultSqlType()
    {
        $column = new Column('');

        $this->assertTrue($column->isDefaultSqlType());
    }

    /**
     * @return void
     */
    public function testGetNotNullString()
    {
        $platform = $this->getPlatformMock();
        $platform
            ->expects($this->once())
            ->method('getNullString')
            ->will($this->returnValue('NOT NULL'));

        $table = $this->getTableMock('books', ['platform' => $platform]);

        $column = new Column('');
        $column->setTable($table);
        $column->setNotNull(true);

        $this->assertSame('NOT NULL', $column->getNotNullString());
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providePdoTypes')]
    public function testGetPdoType($mappingType, $pdoType)
    {
        $domain = $this->getDomainMock();
        $domain
            ->expects($this->any())
            ->method('getColumnType')
            ->will($this->returnValue($mappingType));

        $column = new Column('');
        $column->setTypeMapping($domain);
        $column->setType($mappingType);

        $this->assertSame($pdoType, $column->getPdoType());
    }

    public static function providePdoTypes()
    {
        return [
            [ColumnType::CHAR, PDO::PARAM_STR],
            [ColumnType::VARCHAR, PDO::PARAM_STR],
            [ColumnType::LONGVARCHAR, PDO::PARAM_STR],
            [ColumnType::CLOB, PDO::PARAM_STR],
            [ColumnType::CLOB_EMU, PDO::PARAM_STR],
            [ColumnType::NUMERIC, PDO::PARAM_STR],
            [ColumnType::DECIMAL, PDO::PARAM_STR],
            [ColumnType::TINYINT, PDO::PARAM_INT],
            [ColumnType::SMALLINT, PDO::PARAM_INT],
            [ColumnType::INTEGER, PDO::PARAM_INT],
            [ColumnType::BIGINT, PDO::PARAM_INT],
            [ColumnType::REAL, PDO::PARAM_STR],
            [ColumnType::FLOAT, PDO::PARAM_STR],
            [ColumnType::DOUBLE, PDO::PARAM_STR],
            [ColumnType::BINARY, PDO::PARAM_STR],
            [ColumnType::VARBINARY, PDO::PARAM_LOB],
            [ColumnType::LONGVARBINARY, PDO::PARAM_LOB],
            [ColumnType::BLOB, PDO::PARAM_LOB],
            [ColumnType::DATE, PDO::PARAM_STR],
            [ColumnType::TIME, PDO::PARAM_STR],
            [ColumnType::TIMESTAMP, PDO::PARAM_STR],
            [ColumnType::BOOLEAN, PDO::PARAM_BOOL],
            [ColumnType::BOOLEAN_EMU, PDO::PARAM_INT],
            [ColumnType::OBJECT, PDO::PARAM_LOB],
            [ColumnType::ARRAY, PDO::PARAM_STR],
            [ColumnType::ENUM_BINARY, PDO::PARAM_INT],
            [ColumnType::SET_BINARY, PDO::PARAM_INT],
            [ColumnType::ENUM_NATIVE, PDO::PARAM_STR],
            [ColumnType::SET_NATIVE, PDO::PARAM_STR],
            [ColumnType::BU_DATE, PDO::PARAM_STR],
            [ColumnType::BU_TIMESTAMP, PDO::PARAM_STR],
            [ColumnType::UUID, PDO::PARAM_STR],
            [ColumnType::UUID_BINARY, PDO::PARAM_LOB],
        ];
    }

    /**
     * @return void
     */
    public function testBinaryEnumType()
    {
        $domain = new TypeMapping(ColumnType::ENUM_BINARY);
        $column = new Column('');
        $column->setTypeMapping($domain);
        $column->setType(ColumnType::ENUM_BINARY);
        $column->setValueSet(['FOO', 'BAR']);

        $this->assertSame('int', $column->getPhpType());
        $this->assertTrue($column->isPhpPrimitiveType());
        $this->assertTrue($column->isBinaryEnumType());
        $this->assertContains('FOO', $column->getValueSet());
        $this->assertContains('BAR', $column->getValueSet());
    }

    /**
     * @return void
     */
    public function testBinarySetType()
    {
        $domain = new TypeMapping(ColumnType::SET_BINARY);

        $column = new Column('');
        $column->setTypeMapping($domain);
        $column->setType(ColumnType::SET_BINARY);
        $column->setValueSet(['FOO', 'BAR']);

        $this->assertSame('int', $column->getPhpType());
        $this->assertTrue($column->isPhpPrimitiveType());
        $this->assertTrue($column->isBinarySetType());
        $this->assertContains('FOO', $column->getValueSet());
        $this->assertContains('BAR', $column->getValueSet());
    }

    /**
     * @return void
     */
    public function testSetStringValueSet()
    {
        $column = new Column('');
        $column->setValueSet(' FOO , BAR , BAZ');

        $this->assertContains('FOO', $column->getValueSet());
        $this->assertContains('BAR', $column->getValueSet());
        $this->assertContains('BAZ', $column->getValueSet());
    }

    /**
     * @return void
     */
    public function testPhpObjectType()
    {
        $domain = $this->getDomainMock();
        $domain
            ->expects($this->any())
            ->method('getColumnType')
            ->will($this->returnValue(ColumnType::OBJECT));

        $column = new Column('');
        $column->setTypeMapping($domain);
        $column->setType(ColumnType::OBJECT);

        $this->assertFalse($column->isPhpPrimitiveType());
        $this->assertTrue($column->isPhpObjectType());
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideMappingTemporalTypes')]
    public function testTemporalType($columnType)
    {
        $domain = new TypeMapping($columnType);
        $column = new Column('');
        $column->setTypeMapping($domain);

        $this->assertSame('string', $column->getPhpType());
        $this->assertTrue($column->isPhpPrimitiveType());
        $this->assertTrue($column->isTemporalType());
    }

    public static function provideMappingTemporalTypes()
    {
        return [
            [ColumnType::DATE],
            [ColumnType::TIME],
            [ColumnType::TIMESTAMP],
            [ColumnType::BU_DATE],
            [ColumnType::BU_TIMESTAMP],
        ];
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideMappingLobTypes')]
    public function testLobType($columnType, $phpType, $isPhpPrimitiveType)
    {
        $domain = new TypeMapping($columnType);
        $column = new Column('');
        $column->setTypeMapping($domain);

        $this->assertSame($phpType, $column->getPhpType());
        $this->assertSame($isPhpPrimitiveType, $column->isPhpPrimitiveType());
        $this->assertTrue($column->isLobType());
    }

    public static function provideMappingLobTypes()
    {
        return [
            [ColumnType::VARBINARY, 'string', true],
            [ColumnType::LONGVARBINARY, 'string', true],
            [ColumnType::BLOB, 'resource', false],
        ];
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideMappingBooleanTypes')]
    public function testBooleanType($columnType)
    {
        $domain = new TypeMapping($columnType);

        $column = new Column('');
        $column->setTypeMapping($domain);

        $this->assertSame('bool', $column->getPhpType());
        $this->assertTrue($column->isPhpPrimitiveType());
        $this->assertTrue($column->isBooleanType());
    }

    public static function provideMappingBooleanTypes()
    {
        return [
            [ColumnType::BOOLEAN],
            [ColumnType::BOOLEAN_EMU],
        ];
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideMappingNumericTypes')]
    public function testNumericType($columnType, $phpType, $isPrimitiveNumericType)
    {
        $domain = new TypeMapping($columnType);
        $column = new Column('');
        $column->setTypeMapping($domain);

        $this->assertSame($phpType, $column->getPhpType());
        $this->assertTrue($column->isPhpPrimitiveType());
        $this->assertSame($isPrimitiveNumericType, $column->isPhpPrimitiveNumericType());
        $this->assertTrue($column->isNumericType());
    }

    public static function provideMappingNumericTypes()
    {
        return [
            [ColumnType::SMALLINT, 'int', true],
            [ColumnType::TINYINT, 'int', true],
            [ColumnType::INTEGER, 'int', true],
            [ColumnType::BIGINT, PHP_INT_SIZE === 8 ? 'int' : 'string', PHP_INT_SIZE === 8],
            [ColumnType::FLOAT, 'float', true],
            [ColumnType::DOUBLE, 'float', true],
            [ColumnType::NUMERIC, 'string', false],
            [ColumnType::DECIMAL, 'string', false],
            [ColumnType::REAL, 'float', true],
        ];
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideMappingUuidTypes')]
    public function testUuidType(ColumnType $columnType, string $phpType)
    {
        $domain = new TypeMapping($columnType);
        $column = new Column('');
        $column->setTypeMapping($domain);
        $column->setType($columnType);

        $this->assertSame($phpType, $column->getPhpType());
        $this->assertTrue($column->isPhpPrimitiveType());
        $this->assertTrue($column->isUuidType());
    }

    public static function provideMappingUuidTypes()
    {
        return [
            // column type, php type,
            [ColumnType::UUID, 'string'],
            [ColumnType::UUID_BINARY, 'string'],
        ];
    }


    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideMappingTextTypes')]
    public function testTextType(ColumnType $columnType)
    {
        $domain = new TypeMapping($columnType);

        $column = new Column('');
        $column->setTypeMapping($domain);
        $column->setType($columnType);

        $this->assertSame('string', $column->getPhpType());
        $this->assertTrue($column->isPhpPrimitiveType());
        $this->assertTrue($column->isTextType());
    }

    public static function provideMappingTextTypes()
    {
        return [
            [ColumnType::CHAR],
            [ColumnType::VARCHAR],
            [ColumnType::LONGVARCHAR],
            [ColumnType::CLOB],
            [ColumnType::DATE],
            [ColumnType::TIME],
            [ColumnType::TIMESTAMP],
            [ColumnType::BU_DATE],
            [ColumnType::BU_TIMESTAMP],
        ];
    }

    /**
     * @return void
     */
    public function testGetSizeDefinition()
    {
        $domain = $this->getDomainMock();
        $domain
            ->expects($this->once())
            ->method('getSizeDefinition')
            ->will($this->returnValue('(10,2)'));

        $column = new Column('');
        $column->setTypeMapping($domain);

        $this->assertSame('(10,2)', $column->getSizeDefinition());
    }

    /**
     * @return void
     */
    public function testGetConstantName()
    {
        $table = $this->getTableMock('article');
        $table
            ->expects($this->once())
            ->method('getPhpName')
            ->will($this->returnValue('Article'));

        $column = new Column('created_at');
        $column->setTable($table);
        $column->setTableMapName('created_at');

        $this->assertSame('created_at', $column->getCustomColumnIdentifier());
        $this->assertSame('COL_CREATED_AT', $column->getConstantName());
        $this->assertSame('ArticleTableMap::COL_CREATED_AT', $column->getFQConstantName());
    }

    /**
     * @return void
     */
    public function testSetDefaultPhpName()
    {
        $column = new Column('created_at');

        $this->assertSame('CreatedAt', $column->getPhpName());
        $this->assertSame('createdAt', $column->getCamelCaseName());
    }

    /**
     * @return void
     */
    public function testSetCustomPhpName()
    {
        $column = new Column('created_at');
        $column->setPhpName('CreatedAt');

        $this->assertSame('CreatedAt', $column->getPhpName());
        $this->assertSame('createdAt', $column->getCamelCaseName());
    }

    /**
     * @return void
     */
    public function testSetDefaultMutatorAndAccessorMethodsVisibility()
    {
        $column = new Column('');
        $column->setAccessorVisibility('foo');
        $column->setMutatorVisibility('bar');

        $this->assertSame('public', $column->getAccessorVisibility());
        $this->assertSame('public', $column->getMutatorVisibility());
    }

    /**
     * @return void
     */
    public function testSetMutatorAndAccessorMethodsVisibility()
    {
        $column = new Column('');
        $column->setAccessorVisibility('private');
        $column->setMutatorVisibility('private');

        $this->assertSame('private', $column->getAccessorVisibility());
        $this->assertSame('private', $column->getMutatorVisibility());
    }

    /**
     * @return void
     */
    public function testGetPhpDefaultValue()
    {
        $domain = $this->getDomainMock();
        $domain
            ->expects($this->once())
            ->method('getPhpDefaultValue')
            ->will($this->returnValue(true));

        $column = new Column('');
        $column->setTypeMapping($domain);

        $this->assertTrue($column->getPhpDefaultValue());
    }

    /**
     * @return void
     */
    public function testGetAutoIncrementStringThrowsEngineException()
    {
        $table = $this->getTableMock('books', ['platform' => new DefaultPlatform()]);
        $table
            ->expects($this->once())
            ->method('getIdMethod')
            ->will($this->returnValue(IdMethod::NO_ID_METHOD));

        $column = new Column('');
        $column->setTable($table);
        $column->setAutoIncrement(true);

        $this->expectException(EngineException::class);
        $column->buildAutoIncrementString();
    }

    /**
     * @return void
     */
    public function testGetNativeAutoIncrementString()
    {
        $platform = new MysqlPlatform();
        $db = new Database(null, $platform);
        $table = new Table('book');
        $db->addTable($table);

        $column = new Column('');
        $column->setAutoIncrement(true);
        $column->setTable($table);

        $this->assertEquals('AUTO_INCREMENT', $column->buildAutoIncrementString());
    }

    /**
     * @return void
     */
    public function testGetFullyQualifiedName()
    {
        $column = new Column('title');
        $column->setTable($this->getTableMock('books'));

        $this->assertSame('books.TITLE', $column->getFullyQualifiedName());
    }

    /**
     * @return void
     */
    public function testHasPlatform()
    {
        $table = $this->getTableMock('books', [
            'platform' => $this->getPlatformMock(),
        ]);

        $column = new Column('');
        $column->setTable($table);

        $this->assertTrue($column->hasPlatform());
        $this->assertInstanceOf('Propel\Generator\Platform\PlatformInterface', $column->getPlatform());
    }

    /**
     * @return void
     */
    public function testIsPhpArrayType()
    {
        $column = new Column('');
        $column->setType(ColumnType::ARRAY);
        $this->assertTrue($column->isPhpArrayType());
    }

    /**
     * @return void
     */
    public function testSetSize()
    {
        $domain = $this->getDomainMock();
        $domain
            ->expects($this->once())
            ->method('setSize')
            ->with($this->equalTo(50));
        $domain
            ->expects($this->once())
            ->method('getSize')
            ->will($this->returnValue(50));

        $column = new Column('');
        $column->setTypeMapping($domain);
        $column->setSize(50);

        $this->assertSame(50, $column->getSize());
    }

    /**
     * @return void
     */
    public function testSetScale()
    {
        $domain = $this->getDomainMock();
        $domain
            ->expects($this->once())
            ->method('setScale')
            ->with($this->equalTo(2));
        $domain
            ->expects($this->once())
            ->method('getScale')
            ->will($this->returnValue(2));

        $column = new Column('');
        $column->setTypeMapping($domain);
        $column->setScale(2);

        $this->assertSame(2, $column->getScale());
    }

    /**
     * @return void
     */
    public function testGetDefaultDomain()
    {
        $column = new Column('');

        $this->assertInstanceOf(TypeMapping::class, $column->getTypeMapping());
    }

    /**
     * @return void
     */
    public function testGetSingularName()
    {
        $column = new Column('titles');

        $this->assertSame('title', $column->getSingularName());
        $this->assertTrue($column->isNamePlural());
    }

    /**
     * @return void
     */
    public function testSetTable()
    {
        $column = new Column('');
        $column->setTable($this->getTableMock('books'));

        $this->assertInstanceOf(Table::class, $column->getTable());
        $this->assertSame('books', $column->getTableName());
    }

    /**
     * @return void
     */
    public function testSetDomain()
    {
        $column = new Column('');
        $column->setTypeMapping($this->getDomainMock());

        $this->assertInstanceOf(TypeMapping::class, $column->getTypeMapping());
    }

    /**
     * @return void
     */
    public function testSetDescription()
    {
        $column = new Column('');
        $column->setDescription('Some description');

        $this->assertSame('Some description', $column->getDescription());
    }

    /**
     * @return void
     */
    public function testSetNestedSetLeftKey()
    {
        $column = new Column('');
        $column->setNestedSetLeftKey(true);
        $column->setNodeKeySep(',');
        $column->setNodeKey(true);

        $this->assertTrue($column->isNestedSetLeftKey());
        $this->assertTrue($column->isNodeKey());
        $this->assertSame(',', $column->getNodeKeySep());
    }

    /**
     * @return void
     */
    public function testSetNestedSetRightKey()
    {
        $column = new Column('');
        $column->setNestedSetRightKey(true);

        $this->assertTrue($column->isNestedSetRightKey());
    }

    /**
     * @return void
     */
    public function testSetTreeScopeKey()
    {
        $column = new Column('');
        $column->setTreeScopeKey(true);

        $this->assertTrue($column->isTreeScopeKey());
    }

    /**
     * @return void
     */
    public function testSetAutoIncrement()
    {
        $column = new Column('');
        $column->setAutoIncrement(true);

        $this->assertTrue($column->isAutoIncrement());
    }

    /**
     * @return void
     */
    public function testSetPrimaryString()
    {
        $column = new Column('');
        $column->setPrimaryString(true);

        $this->assertTrue($column->isPrimaryString());
    }

    /**
     * @return void
     */
    public function testSetNotNull()
    {
        $column = new Column('');
        $column->setNotNull(true);

        $this->assertTrue($column->isNotNull());
    }

    /**
     * @return void
     */
    public function testPhpSingularName()
    {
        $column = new Column('');
        $column->setPhpName('Aliases');

        $this->assertEquals($column->getPhpName(), 'Aliases');
        $this->assertEquals($column->getPhpSingularName(), 'Aliase');

        $column = new Column('');
        $column->setPhpName('Aliases');
        $column->setPhpSingularName('Alias');

        $this->assertEquals($column->getPhpName(), 'Aliases');
        $this->assertEquals($column->getPhpSingularName(), 'Alias');
    }

    public function testNoBinaryEnumWithPhpBackedEnum()
    {
        $this->expectException(SchemaException::class);
        $this->expectExceptionMessage('Column `table.foo`: Combining binary ENUM/SET type with a PHP enum type (`phpType="Propel\Tests\Helpers\ColorsBackedEnum"` is not supported');
        $this->buildColumnFromSchema('<column name="foo" type="ENUM_BINARY" phpType="' . ColorsBackedEnum::class. '"/>');
    }

    public function testNoBinaryEnumWithPhpUnitEnum()
    {
        $this->expectException(SchemaException::class);
        $this->expectExceptionMessage('Column `table.foo`: Combining binary ENUM/SET type with a PHP enum type (`phpType="Propel\Tests\Helpers\ColorsUnitEnum"` is not supported');
        $this->buildColumnFromSchema('<column name="foo" type="ENUM_BINARY" phpType="' . ColorsUnitEnum::class. '"/>');
    }

    public static function HasCustomPhpNameDataProvider(): array
    {
        /** @var array<array{Column, bool, string}> */
        $data = [];

        $data[] = [new Column('foo'), false, 'Default name is not custom.'];

        $column = new Column('foo');
        $column->setPhpName('Foo');
        $data[] = [$column, false, 'Manually set default name is still default.'];

        $column = new Column('foo');
        $column->setPhpName(null);
        $data[] = [$column, false, 'No PhpName'];

        $column = new Column('foo');
        $column->setPhpName('foo');
        $data[] = [$column, true, 'Custom name should be case sensitive.'];

        $column = new Column('foo');
        $column->setPhpName('NotFoo');
        $data[] = [$column, true, 'Has custom value.'];

        $column = new Column('foo');
        (new TestCase(''))->setObjectPropertyValue($column, 'namePrefix', 'col');
        $data[] = [$column, false, 'Prefixed name is not default.'];

        return $data;
    }

    #[DataProvider('HasCustomPhpNameDataProvider')]
    public function testHasCustomPhpName(Column $column, bool $expected, string $description): void
    {
        $this->assertSame($expected, $column->hasCustomPhpName(), $description);
    }

    /**
     * @return void
     */
    public function testMissingIdMethod(): void
    {
        $column = new Column('foo');
        $column->setAutoIncrement(true);

        $table = new Table('t');
        $table->setIdMethod(IdMethod::NO_ID_METHOD);
        $table->addColumn($column);

        $this->expectException(EngineException::class);
        $this->expectExceptionMessage('Column `t.FOO` uses auto increment but no idMethod is set on database or table.');

        $column->buildAutoIncrementString();
    }
}
