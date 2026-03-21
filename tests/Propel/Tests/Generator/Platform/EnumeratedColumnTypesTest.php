<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Platform;

use Propel\Generator\Builder\Om\AbstractOMBuilder;
use Propel\Generator\Builder\Om\BuilderType;
use Propel\Generator\Builder\Om\ObjectBuilder;
use Propel\Generator\Builder\Om\ObjectBuilder\ColumnTypes\ColumnCodeProducer;
use Propel\Generator\Builder\Om\QueryBuilder;
use Propel\Generator\Config\GeneratorConfig;
use Propel\Generator\Config\QuickGeneratorConfig;
use Propel\Generator\Model\Column;
use Propel\Generator\Model\PropelTypes;
use Propel\Generator\Platform\DefaultPlatform;
use Propel\Generator\Platform\MssqlPlatform;
use Propel\Generator\Platform\MysqlPlatform;
use Propel\Generator\Platform\OraclePlatform;
use Propel\Generator\Platform\PgsqlPlatform;
use Propel\Generator\Platform\PlatformInterface;
use Propel\Generator\Platform\SqlitePlatform;
use Propel\Tests\Helpers\ColorsBackedEnum;
use Propel\Tests\Helpers\ColorsBasicEnum;
use Propel\Tests\TestCase;

class EnumeratedColumnTypesTest extends TestCase
{
    public function EnumAliasProvider(): array
    {
        $platformsWithoutNativeType = [
            DefaultPlatform::class,
            MssqlPlatform::class,
            OraclePlatform::class,
            SqlitePlatform::class,
        ];

        $platformsWithNativeType = [
            MysqlPlatform::class,
            PgsqlPlatform::class,
        ];

        $data = [];
        foreach (array_merge($platformsWithoutNativeType, $platformsWithNativeType) as $platform) {
            $data[] = [$platform, false, PropelTypes::ENUM , PropelTypes::ENUM_BINARY];
            $data[] = [$platform, false, PropelTypes::SET, PropelTypes::SET_BINARY];
        }
        foreach ($platformsWithoutNativeType as $platform) {
            $data[] = [$platform, true, PropelTypes::ENUM , PropelTypes::ENUM_BINARY];
            $data[] = [$platform, true, PropelTypes::SET, PropelTypes::SET_BINARY];
        }
        foreach ($platformsWithNativeType as $platform) {
            $data[] = [$platform, true, PropelTypes::ENUM , PropelTypes::ENUM_NATIVE];
            $data[] = [$platform, true, PropelTypes::SET, PropelTypes::SET_NATIVE];
        }

        return $data;
    }

    /**
     * @dataProvider EnumAliasProvider
     *
     * @param class-string<PlatformInterface> $platform
     * @param bool $defaultToNative
     * @param string $columnType
     * @param string $expectedColumnType
     *
     * @return void
     */
    public function testEnumAliasOnPlatform(string $platformClass, bool $defaultToNative, string $columnType, string $expectedColumnType): void
    {
        $sqlTypeAttr = (
            $platformClass === PgsqlPlatform::class
            && in_array($columnType, [PropelTypes::ENUM, PropelTypes::SET], true)
            && $defaultToNative
        ) ? ' sqlType="column_type"' : '';
        $columnXml = '<column name="column" type="' . $columnType . '" valueSet="A,B"' . $sqlTypeAttr . '/>';
        $column = $this->buildColumnFromSchema(new $platformClass, $defaultToNative, $columnXml);
        $actualColumnType = $column->getType();

        $this->assertSame($expectedColumnType, $actualColumnType);
    }

    /**
     * @return string[][]
     */
    public function ColumnCodeDataProvider(): array
    {
        return [ // [type, expected code file name]
            [PropelTypes::ENUM_BINARY, __DIR__ . '/ExpectedColumnCode/EnumBinaryColumnCode.txt'],
            [PropelTypes::ENUM_NATIVE, __DIR__ . '/ExpectedColumnCode/EnumNativeColumnCode.txt'],
            [PropelTypes::SET_BINARY, __DIR__ . '/ExpectedColumnCode/SetBinaryColumnCode.txt'],
            [PropelTypes::SET_NATIVE, __DIR__ . '/ExpectedColumnCode/SetNativeColumnCode.txt']
        ];
    }

    /**
     * @dataProvider ColumnCodeDataProvider
     *
     * @param string $columnType
     * @param string $fileName
     *
     * @return void
     */
    public function testEnumeratedColumnObjectCode(string $columnType, string $fileName): void
    {
        /** @var ObjectBuilder $builder */
        $builder = $this->buildCodeBuilder($columnType, BuilderType::ObjectBase);
        /** @var ColumnCodeProducer $builder */
        $codeProducer = $this->getObjectPropertyValue($builder, 'columnCodeProducers')[0];

        $script = '';
        $codeProducer->addColumnAttributes($script);
        $codeProducer->addAccessor($script);
        $codeProducer->addMutator($script);

        $this->assertStringEqualsFile($fileName, $script);
    }

    /**
     * @return string[][]
     */
    public function QueryCodeDataProvider(): array
    {
        return [ // [type, expected code file name]
            [PropelTypes::ENUM_BINARY, __DIR__ . '/ExpectedColumnCode/EnumBinaryQueryCode.txt'],
            [PropelTypes::ENUM_NATIVE, __DIR__ . '/ExpectedColumnCode/EnumNativeQueryCode.txt'],
            [PropelTypes::SET_BINARY, __DIR__ . '/ExpectedColumnCode/SetBinaryQueryCode.txt'],
            [PropelTypes::SET_NATIVE, __DIR__ . '/ExpectedColumnCode/SetNativeQueryCode.txt'],
        ];
    }

    /**
     * @dataProvider QueryCodeDataProvider
     *
     * @param string $columnType
     * @param string $fileName
     *
     * @return void
     */
    public function testEnumeratedColumnQueryCode(string $columnType, string $fileName): void
    {
        /** @var QueryBuilder $builder */
        $builder = $this->buildCodeBuilder($columnType, BuilderType::QueryBase);

        $script = '';
        $this->callMethod($builder, 'addColumnCode', [&$script, $builder->getTable()->getColumns()[0]]);

        $this->assertStringEqualsFile($fileName, $script);
    }

    /**
     * @return string[][]
     */
    public function SqlTypeDataProvider(): array
    {
        return [
            [PropelTypes::ENUM_BINARY, 'A,B', PropelTypes::TINYINT],
            [PropelTypes::ENUM_NATIVE, 'A,B', "ENUM('A','B')"],
            [PropelTypes::SET_BINARY, 'A,B', PropelTypes::INTEGER],
            [PropelTypes::SET_NATIVE, 'A,B', "SET('A','B')"],
        ];
    }

    /**
     * @dataProvider SqlTypeDataProvider
     *
     * @param string $columnType
     * @param string $valueSetCsv
     * @param string $expectedSqlType
     *
     * @return void
     */
    public function testSqlType(string $columnType, string $valueSetCsv, string $expectedSqlType): void
    {
        $columnXml = '<column name="enumerated_column" type="' . $columnType . '" valueSet="' . $valueSetCsv . '"/>';
        $column = $this->buildColumnFromSchema(new MysqlPlatform(), false, $columnXml);

        $this->assertSame($column->getSqlType(), $expectedSqlType);
    }

    /**
     * @return array<class-string<\UnitEnum>, string>[]
     */
    public function GetEnumItemsDataProvider(): array
    {
        return [
            [ColorsBasicEnum::class, "`foo` ENUM('Red','Blue','Yellow')"],
            [ColorsBackedEnum::class, "`foo` ENUM('red','blue','yellow')"],
        ];
    }

    /**
     * @dataProvider GetEnumItemsDataProvider
     */
    public function testSetValuesFromPhpEnum(string $enumClass, string $expectedColumnDdl): void
    {
        $columnXml = '<column name="foo" type="ENUM_NATIVE" valueEnum="' . $enumClass . '"/>';
        $platform = new MysqlPlatform();
        $column = $this->buildColumnFromSchema($platform, false, $columnXml);
        $ddl = $platform->getColumnDDL($column);

        $this->assertEquals($expectedColumnDdl, $ddl);
    }

    public function testIsPhpBackedEnumType(): void
    {
        $this->assertTrue(PropelTypes::isPhpBackedEnumType(ColorsBackedEnum::class));
        $this->assertFalse(PropelTypes::isPhpBackedEnumType(ColorsBasicEnum::class));
        $this->assertFalse(PropelTypes::isPhpBackedEnumType('string'));
        $this->assertFalse(PropelTypes::isPhpBackedEnumType(\stdClass::class));
    }

    public function testColumnWithPhpTypeBackedEnum(): void
    {
        $columnXml = '<column name="color" type="VARCHAR" size="16" phpType="' . ColorsBackedEnum::class . '"/>';
        $column = $this->buildColumnFromSchema(new DefaultPlatform(), false, $columnXml);

        $this->assertTrue($column->isPhpBackedEnumType());
        $this->assertTrue($column->isPhpObjectType());
    }

    public function testColumnWithPhpTypeRegularClassIsNotBackedEnum(): void
    {
        $columnXml = '<column name="amount" type="DECIMAL" phpType="\stdClass"/>';
        $column = $this->buildColumnFromSchema(new DefaultPlatform(), false, $columnXml);

        $this->assertFalse($column->isPhpBackedEnumType());
        $this->assertTrue($column->isPhpObjectType());
    }

    public function testPgsqlEnumNativeUsesSqlType(): void
    {
        $columnXml = '<column name="status" type="ENUM_NATIVE" valueEnum="' . ColorsBackedEnum::class . '" sqlType="status_type"/>';
        $platform = new PgsqlPlatform();
        $column = $this->buildColumnFromSchema($platform, false, $columnXml);

        $this->assertSame('status_type', $column->getDomain()->getSqlType());
    }

    public function testPgsqlEnumNativeWithoutSqlTypeFallsBackToVarchar(): void
    {
        $columnXml = '<column name="status" type="ENUM_NATIVE" valueEnum="' . ColorsBackedEnum::class . '"/>';
        $platform = new PgsqlPlatform();
        $column = $this->buildColumnFromSchema($platform, false, $columnXml);

        $this->assertSame('VARCHAR', $column->getDomain()->getSqlType());
    }

    /**
     * @param PlatformInterface $platform
     * @param bool $defaultToNative
     * @param string $columnXml
     *
     * @return Column
     */
    public function buildColumnFromSchema(PlatformInterface $platform, bool $defaultToNative, string $columnXml): Column
    {
        $schema = <<<EOF
                <database>
                    <table name="table">
                        $columnXml
                    </table>
                </database>
EOF;
        $extraConfig = ['propel' => ['generator' => ['defaultToNativeEnumeratedColumnTypes' => $defaultToNative]]];
        $schema = $this->buildDatabaseFromSchema($schema, $extraConfig, $platform);

        return $schema->getTable('table')->getColumns()[0];
    }

    /**
     * @param string $columnType
     * @param BuilderType $builderType
     *
     * @return AbstractOMBuilder
     */
    public function buildCodeBuilder(string $columnType, BuilderType $builderType): AbstractOMBuilder
    {
        $columnXml = '<column name="enumerated_column" type="' . $columnType . '" valueSet="A,B"/>';
        $column = $this->buildColumnFromSchema(new MysqlPlatform(), false, $columnXml);
        $config = new QuickGeneratorConfig();

        return $config->loadConfiguredBuilder($column->getTable(), $builderType);
    }
}
