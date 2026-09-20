<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Platform;

use PHPUnit\Framework\Attributes\DataProvider;
use Propel\Generator\Builder\Om\AbstractOMBuilder;
use Propel\Generator\Builder\Om\BuilderType;
use Propel\Generator\Config\QuickGeneratorConfig;
use Propel\Generator\Model\Column;
use Propel\Generator\Model\Datatype\ColumnType;
use Propel\Generator\Model\Datatype\PhpDatatype;
use Propel\Generator\Platform\DefaultPlatform;
use Propel\Generator\Platform\MssqlPlatform;
use Propel\Generator\Platform\MysqlPlatform;
use Propel\Generator\Platform\OraclePlatform;
use Propel\Generator\Platform\PgsqlPlatform;
use Propel\Generator\Platform\PlatformInterface;
use Propel\Generator\Platform\SqlitePlatform;
use Propel\Tests\Helpers\ColorsBackedEnum;
use Propel\Tests\Helpers\ColorsUnitEnum;
use Propel\Tests\TestCase;

class EnumeratedColumnTypesTest extends TestCase
{
    public static function EnumAliasProvider(): array
    {
        $platformsWithoutNativeType = [
            DefaultPlatform::class,
            MssqlPlatform::class,
            OraclePlatform::class,
            PgsqlPlatform::class,
            SqlitePlatform::class,
        ];

        $platformsWithNativeType = [
            MysqlPlatform::class,
        ];

        $data = [];
        foreach (array_merge($platformsWithoutNativeType, $platformsWithNativeType) as $platform) {
            $data[] = [$platform, false, ColumnType::ENUM , ColumnType::ENUM_BINARY];
            $data[] = [$platform, false, ColumnType::SET, ColumnType::SET_BINARY];
        }
        foreach ($platformsWithoutNativeType as $platform) {
            $data[] = [$platform, true, ColumnType::ENUM , ColumnType::ENUM_BINARY];
            $data[] = [$platform, true, ColumnType::SET, ColumnType::SET_BINARY];
        }
        foreach ($platformsWithNativeType as $platform) {
            $data[] = [$platform, true, ColumnType::ENUM , ColumnType::ENUM_NATIVE];
            $data[] = [$platform, true, ColumnType::SET, ColumnType::SET_NATIVE];
        }

        return $data;
    }

    /**
     *
     * @param class-string<PlatformInterface> $platform
     * @param bool $defaultToNative
     * @param ColumnType $columnType
     * @param string $expectedColumnType
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('EnumAliasProvider')]
    public function testEnumAliasOnPlatform(string $platformClass, bool $defaultToNative, ColumnType $columnType, ColumnType $expectedColumnType): void
    {
        $columnXml = '<column name="column" type="' . $columnType->name . '" valueSet="A,B"/>';
        $column = $this->buildColumnForPlatform(new $platformClass, $defaultToNative, $columnXml);
        $actualColumnType = $column->getColumnType();

        $this->assertSame($expectedColumnType, $actualColumnType);
    }

    /**
     * @return string[][]
     */
    public static function SqlTypeDataProvider(): array
    {
        return [
            [ColumnType::ENUM_BINARY, 'A,B', 'TINYINT'],
            [ColumnType::ENUM_NATIVE, 'A,B', "ENUM('A','B')"],
            [ColumnType::SET_BINARY, 'A,B', 'INTEGER'],
            [ColumnType::SET_NATIVE, 'A,B', "SET('A','B')"],
        ];
    }

    /**
     *
     * @param ColumnType $columnType
     * @param string $valueSetCsv
     * @param string $expectedSqlType
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('SqlTypeDataProvider')]
    public function testSqlType(ColumnType $columnType, string $valueSetCsv, string $expectedSqlType): void
    {
        $columnXml = '<column name="enumerated_column" type="' . $columnType->name . '" valueSet="' . $valueSetCsv . '"/>';
        $column = $this->buildColumnForPlatform(new MysqlPlatform(), false, $columnXml);

        $this->assertSame($column->resolveSqlTypeName(), $expectedSqlType);
    }

    /**
     * @return array<class-string<\UnitEnum>, string>[]
     */
    public static function GetEnumItemsDataProvider(): array
    {
        return [
            [ColorsUnitEnum::class, "`foo` ENUM('Red','Blue','Yellow')"],
            [ColorsBackedEnum::class, "`foo` ENUM('red','blue','yellow')"],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('GetEnumItemsDataProvider')]
    public function testSetValuesFromPhpEnum(string $enumClass, string $expectedColumnDdl): void
    {
        $columnXml = '<column name="foo" type="ENUM_NATIVE" valueEnum="' . $enumClass . '"/>';
        $platform = new MysqlPlatform();
        $column = $this->buildColumnForPlatform($platform, false, $columnXml);
        $ddl = $platform->buildColumnDdl($column);

        $this->assertEquals($expectedColumnDdl, $ddl);
    }

    public function testIsPhpBackedEnumType(): void
    {
        $this->assertTrue(PhpDatatype::isPhpBackedEnumType(ColorsBackedEnum::class));
        $this->assertFalse(PhpDatatype::isPhpBackedEnumType(ColorsUnitEnum::class));
        $this->assertFalse(PhpDatatype::isPhpBackedEnumType('string'));
        $this->assertFalse(PhpDatatype::isPhpBackedEnumType(\stdClass::class));
    }

    public function testIsPhpUnitEnumType(): void
    {
        $this->assertTrue(PhpDatatype::isPhpUnitEnumType(ColorsUnitEnum::class));
        $this->assertFalse(PhpDatatype::isPhpUnitEnumType(ColorsBackedEnum::class));
        $this->assertFalse(PhpDatatype::isPhpUnitEnumType('string'));
        $this->assertFalse(PhpDatatype::isPhpUnitEnumType(\stdClass::class));
    }


    public static function EnumTypedColumnXmlDataProvider(): array
    {
        return [ // column xml, is object type, is backed enum type, is unit enum type
            ['<column name="color" type="VARCHAR" size="16" phpType="' . ColorsBackedEnum::class . '"/>', true, true, false],
            ['<column name="color" type="VARCHAR" size="16" phpType="' . ColorsUnitEnum::class . '"/>', true, false, true],
            ['<column name="amount" type="DECIMAL" phpType="\stdClass"/>', true, false, false],
            ['<column name="enum" type="ENUM_BINARY"/>', false, false, false],
        ];
    }

    #[DataProvider('EnumTypedColumnXmlDataProvider')]
    public function testColumnPhpTypeDetection(string $columnXml, bool $isObject, bool $isBackedEnum, bool $isUnitEnum): void
    {
        $column = $this->buildColumnForPlatform(new DefaultPlatform(), false, $columnXml);

        $this->assertSame($isObject, $column->isPhpObjectType());
        $this->assertSame($isBackedEnum, $column->isPhpBackedEnumType());
        $this->assertSame($isUnitEnum, $column->isPhpUnitEnumType());
    }

    /**
     * @param PlatformInterface $platform
     * @param bool $defaultToNative
     * @param string $columnXml
     *
     * @return Column
     */
    public function buildColumnForPlatform(PlatformInterface $platform, bool $defaultToNative, string $columnXml): Column
    {
        return $this->buildColumnFromSchema($columnXml, ['propel.generator.defaultToNativeEnumeratedColumnTypes' => $defaultToNative], $platform);
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
        $column = $this->buildColumnForPlatform(new MysqlPlatform(), false, $columnXml);
        $config = new QuickGeneratorConfig();

        return $config->loadConfiguredBuilder($column->getTable(), $builderType);
    }
}
