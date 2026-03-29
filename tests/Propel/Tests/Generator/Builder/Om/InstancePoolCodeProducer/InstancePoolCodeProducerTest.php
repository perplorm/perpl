<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Builder\Om\InstancePoolCodeProducer;

use Propel\Generator\Builder\Om\InstancePoolCodeProducer\InstancePoolCodeProducer;
use Propel\Generator\Builder\Om\TableMapBuilder;
use Propel\Generator\Config\QuickGeneratorConfig;
use Propel\Generator\Model\Table;
use Propel\Tests\TestCase;

class InstancePoolCodeProducerTest extends TestCase
{
    public function ColumnValueToStringExpressionDataProvider(): array
    {
        return [ // string $columnXml, string $varName, string $expected
            ['', '$foo', '$foo'],
            ['type="VARCHAR"', '$var', '$var'],
            ['type="INTEGER"', '$var', '(string)$var'],
            ['type="ENUM_BINARY"', '$var', '(string)array_search($var, static::getValueSet(static::COL_FOOCOL))'],
            ['type="SET_BINARY"', '$foo', '(string)SetColumnConverter::convertToBitmask($foo, static::getValueSet(static::COL_FOOCOL))'],
            ['type="OBJECT"', '$var', 'is_callable([$var, \'__toString\']) ? (string)$var : $var'],
            ['type="ARRAY"', '$var', 'Table::serializeArray($var)'],
            ['type="DATE"', '$var', '$var->format(\'Y-m-d\')'],
            ['type="UUID_BINARY"', '$var', 'UuidConverter::uuidToBin($var, true)'],
        ];
    }

    /**
     * @dataProvider ColumnValueToStringExpressionDataProvider
     */
    public function testColumnValueToStringExpression(string $columnXml, string $varName, string $expected): void
    {
        $this->assertBuildsSameColumnString(
            $columnXml,
            'buildColumnValueToStringExpression',
            fn($col) => [$varName, $col],
            $expected
        );
    }

    public function RowValueToStringExpressionDataProvider(): array
    {
        return [ // string $columnXml, string $varName, string $expected
            ['', '$foo', '$foo'],
            ['type="VARCHAR"', '$var', '$var'],
            ['type="INTEGER"', '$var', '(string)$var'],
            ['type="ENUM_BINARY"', '$var', '(is_numeric($var) ? $var : (string)array_search($var, static::getValueSet(static::COL_FOOCOL)))'],
            ['type="SET_BINARY"', '$foo', '(is_numeric($foo) ? $foo : (string)SetColumnConverter::convertToBitmask($foo, static::getValueSet(static::COL_FOOCOL)))'],
            ['type="ARRAY"', '$var', '(is_string($var) ? $var : Table::serializeArray($var))'],
            ['type="DATE"', '$var', '(is_string($var) ? $var : $var->format(\'Y-m-d\'))'],
            ['type="UUID_BINARY"', '$var', 'UuidConverter::uuidToBin($var, true)'],
        ];
    }

    /**
     * @dataProvider RowValueToStringExpressionDataProvider
     */
    public function testRowValueToStringExpression(string $columnXml, string $varName, string $expected): void
    {
        $this->assertBuildsSameColumnString(
            $columnXml,
            'buildPossiblyUnconvertedValueToStringExpression',
            fn($col) => [$varName, $col],
            $expected
        );
    }

    protected function setupBuilder(Table $table): InstancePoolCodeProducer
    {
        $tableMapBuilder = new TableMapBuilder($table);
        $tableMapBuilder->setGeneratorConfig(new QuickGeneratorConfig());

        return new InstancePoolCodeProducer($table, $tableMapBuilder);
    }

    /**
     * @dataProvider RowValueToStringExpressionDataProvider
     *
     * @param callable $argBuilder(Column): array
     */
    public function assertBuildsSameColumnString(string $columnXml, string $method, callable $argBuilder, string $expected): void
    {
        $column = $this->buildColumnFromSchema("<column name='FooCol' $columnXml />");
        $builder = $this->setupBuilder($column->getTable());
        $expression = $this->callMethod($builder, $method, $argBuilder($column));

        $this->assertSame($expected, $expression);
    }

    public function BuildPoolKeyFromVariableDataProvider(): array
    {
        return [ // array $varToColumnXml, bool $possiblyUnconverted, string $expected
            [['$foo' => 'type="DATE"'], false, '$foo->format(\'Y-m-d\')'],
            [['$foo' => 'type="DATE"'], true, '(is_string($foo) ? $foo : $foo->format(\'Y-m-d\'))'],
            [['$foo' => 'type="INTEGER"', '$bar' => ''], true, 'serialize([(string)$foo, $bar])'],
        ];
    }

    /**
     * @dataProvider BuildPoolKeyFromVariableDataProvider
     */
    public function testBuildPoolKeyFromVariable(array $varToColumnXml, bool $possiblyUnconverted, string $expected): void
    {
        $varToColumn = array_map(fn ($columnXml) =>$this->buildColumnFromSchema("<column name='FooCol' $columnXml />"), $varToColumnXml);

        $builder = $this->setupBuilder(reset($varToColumn)->getTable());
        $expression = $builder->buildPoolKeyFromAccessorMap($varToColumn, $possiblyUnconverted);

        $this->assertSame($expected, $expression);
    }
}
