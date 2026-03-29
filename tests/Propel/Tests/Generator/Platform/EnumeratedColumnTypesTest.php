<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Platform;

use Propel\Generator\Builder\Om\AbstractOMBuilder;
use Propel\Generator\Builder\Om\BuilderType;
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
            PgsqlPlatform::class,
            SqlitePlatform::class,
        ];

        $platformsWithNativeType = [
            MysqlPlatform::class,
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
        $columnXml = '<column name="column" type="' . $columnType . '" valueSet="A,B"/>';
        $column = $this->buildColumnForPlatform(new $platformClass, $defaultToNative, $columnXml);
        $actualColumnType = $column->getType();

        $this->assertSame($expectedColumnType, $actualColumnType);
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
        $column = $this->buildColumnForPlatform(new MysqlPlatform(), false, $columnXml);

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
        $column = $this->buildColumnForPlatform($platform, false, $columnXml);
        $ddl = $platform->getColumnDDL($column);

        $this->assertEquals($expectedColumnDdl, $ddl);
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
