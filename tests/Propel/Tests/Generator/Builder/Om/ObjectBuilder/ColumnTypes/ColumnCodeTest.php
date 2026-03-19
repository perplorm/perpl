<?php

declare(strict_types = 1);

namespace Propel\Tests\Generator\Builder\Om\ObjectBuilder\ColumnTypes;

use Propel\Generator\Builder\Om\AbstractOMBuilder;
use Propel\Generator\Builder\Om\BuilderType;
use Propel\Generator\Config\QuickGeneratorConfig;
use Propel\Generator\Model\Column;
use Propel\Generator\Platform\MysqlPlatform;
use Propel\Generator\Platform\PlatformInterface;
use Propel\Tests\Attributes\ComparesGeneratedFile;
use Propel\Tests\CompareGeneratedCodeTestCase;

class ColumnCodeTest extends CompareGeneratedCodeTestCase
{
 /**
     * @return array<array>
     */
    #[ComparesGeneratedFile(textBuilder: 'buildObjectClassCode')]
    public function ColumnCodeDataProvider(): array
    {
        $mysqlPlatform = new MysqlPlatform();

        return [ // [[column xml, config, platform], expected code file name]
            [['<column name="array_column" type="ARRAY"/>', null, null], __DIR__ . '/expected_column_code/array_model_reference.txt'],
            [['<column name="bool_column" type="BOOLEAN" defaultValue="true"/>', null, null], __DIR__ . '/expected_column_code/bool_model_reference.txt'],
            [['<column name="integer_column" type="INTEGER"  defaultValue="42"/>', null, null], __DIR__ . '/expected_column_code/integer_model_reference.txt'],
            [['<column name="manually_typed_column" type="INTEGER" phpName="MyCol" phpType="FooType"  defaultValue="42"/>', null, null], __DIR__ . '/expected_column_code/manually_typed_model_reference.txt'],
            [['<column name="var_char_column" type="VARCHAR" size="42" defaultValue="Bar"/>', null, null], __DIR__ . '/expected_column_code/varchar_model_reference.txt'],
            [['<column name="temporal_column" type="DATETIME" defaultValue="2026-03-19T01:09:00"/>', null, null], __DIR__ . '/expected_column_code/temporal_model_reference.txt'],
            [['<column name="object_column" type="OBJECT"/>', null, null], __DIR__ . '/expected_column_code/object_model_reference.txt'],
            [['<column name="json_column" type="JSON"/>', null, null], __DIR__ . '/expected_column_code/json_model_reference.txt'],
            [['<column name="blob_column" type="BLOB"/>', null, null], __DIR__ . '/expected_column_code/lob_model_reference.txt'],
            [['<column name="uuid_column" type="uuid"/>', null, null], __DIR__ . '/expected_column_code/uuid_model_reference.txt'],
            [['<column name="bin_enum_column" type="ENUM_BINARY" valueSet="foo,bar"/>', null, null], __DIR__ . '/expected_column_code/enum_binary_model_reference.txt'],
            [['<column name="bin_set_column" type="SET_BINARY" valueSet="foo,bar"/>', null, null], __DIR__ . '/expected_column_code/set_binary_model_reference.txt'],
            [['<column name="native_enum_column" type="ENUM_NATIVE" valueSet="foo,bar"/>', null, $mysqlPlatform], __DIR__ . '/expected_column_code/enum_native_model_reference.txt'],
            [['<column name="native_set_column" type="SET_NATIVE" valueSet="foo,bar"/>', null, $mysqlPlatform], __DIR__ . '/expected_column_code/set_native_model_reference.txt'],
        ];
    }

    /**
     * @param string $columnXml
     * @param array $config
     * @param \Propel\Generator\Platform\PlatformInterface $platform
     *
     * @return string
     */
    public function buildObjectClassCode(string $columnXml, array|null $config, PlatformInterface|null $platform): string
    {
        /** @var \Propel\Generator\Builder\Om\ObjectBuilder $builder */
        $builder = $this->buildCodeBuilder($columnXml, BuilderType::ObjectBase, $config, $platform);
        /** @var \Propel\Generator\Builder\Om\ObjectBuilder\ColumnTypes\ColumnCodeProducer $builder */
        $codeProducer = $this->getObjectPropertyValue($builder, 'columnCodeProducers')[0];

        $script = $this->generateCodeFileContentScript($codeProducer, [
            'addColumnAttributes',
            'addAccessor',
            'addMutator',
        ])
        . $this->buildCodeFileContent('getDefaultValueString', $codeProducer->getDefaultValueString())
        . $this->buildCodeFileContent('getApplyDefaultValueStatement', $codeProducer->getApplyDefaultValueStatement())
        . $this->buildCodeFileContent('buildCreateFromFilterValueExpression', $codeProducer->buildCreateFromFilterValueExpression('$value'));

        return $script;
    }

    /**
     * @dataProvider ColumnCodeDataProvider
     *
     * @param array{string, (array | null), (\Propel\Generator\Platform\PlatformInterface | null)}|array $builderArgs
     * @param string $fileName
     *
     * @return void
     */
    public function testEnumeratedColumnObjectCode(array $builderArgs, string $fileName): void
    {
        $objectClassCode = $this->buildObjectClassCode(...$builderArgs);
        $this->assertStringEqualsFile($fileName, $objectClassCode);
    }

    /**
     * @return array<array<string>>
     */
    #[ComparesGeneratedFile(textBuilder: 'buildQueryClassCode')]
    public function QueryCodeDataProvider(): array
    {
        $mysqlPlatform = new MysqlPlatform();

        return [ // [[column xml, config, platform], expected code file name]
            [['<column name="array_column" type="ARRAY"/>', null, null], __DIR__ . '/expected_column_code/array_query_reference.txt'],
            [['<column name="bool_column" type="BOOLEAN" defaultValue="true"/>', null, null], __DIR__ . '/expected_column_code/bool_query_reference.txt'],
            [['<column name="integer_column" type="INTEGER"  defaultValue="42"/>', null, null], __DIR__ . '/expected_column_code/integer_query_reference.txt'],
            [['<column name="manually_typed_column" type="INTEGER" phpName="MyCol" phpType="FooType"  defaultValue="42"/>', null, null], __DIR__ . '/expected_column_code/manually_typed_query_reference.txt'],
            [['<column name="var_char_column" type="VARCHAR" size="42" defaultValue="Bar"/>', null, null], __DIR__ . '/expected_column_code/varchar_query_reference.txt'],
            [['<column name="temporal_column" type="DATETIME" defaultValue="2026-03-19T01:09:00"/>', null, null], __DIR__ . '/expected_column_code/temporal_query_reference.txt'],
            [['<column name="object_column" type="OBJECT"/>', null, null], __DIR__ . '/expected_column_code/object_query_reference.txt'],
            [['<column name="json_column" type="JSON"/>', null, null], __DIR__ . '/expected_column_code/json_query_reference.txt'],
            [['<column name="blob_column" type="BLOB"/>', null, null], __DIR__ . '/expected_column_code/lob_query_reference.txt'],
            [['<column name="uuid_column" type="uuid"/>', null, null], __DIR__ . '/expected_column_code/uuid_query_reference.txt'],
            [['<column name="bin_enum_column" type="ENUM_BINARY" valueSet="foo,bar"/>', null, null], __DIR__ . '/expected_column_code/enum_binary_query_reference.txt'],
            [['<column name="bin_set_column" type="SET_BINARY" valueSet="foo,bar"/>', null, null], __DIR__ . '/expected_column_code/set_binary_query_reference.txt'],
            [['<column name="native_enum_column" type="ENUM_NATIVE" valueSet="foo,bar"/>', null, $mysqlPlatform], __DIR__ . '/expected_column_code/enum_native_query_reference.txt'],
            [['<column name="native_set_column" type="SET_NATIVE" valueSet="foo,bar"/>', null, $mysqlPlatform], __DIR__ . '/expected_column_code/set_native_query_reference.txt'],
        ];
    }

    /**
     * @param string $columnXml
     * @param array $config
     * @param \Propel\Generator\Platform\PlatformInterface $platform
     *
     * @return string
     */
    public function buildQueryClassCode(string $columnXml, array|null $config, PlatformInterface|null $platform): string
    {
        /** @var \Propel\Generator\Builder\Om\QueryBuilder $builder */
        $builder = $this->buildCodeBuilder($columnXml, BuilderType::QueryBase, $config, $platform);
        $column = $builder->getTable()->getColumns()[0];

        $script = '';
        $this->callMethod($builder, 'addColumnCode', [&$script, $column]);
        $this->buildCodeFileContent('addColumnCode', $script);

        return $script;
    }

    /**
     * @dataProvider QueryCodeDataProvider
     *
     * @param array{string, (array | null), (\Propel\Generator\Platform\PlatformInterface | null)}|array $builderArgs
     * @param string $fileName
     *
     * @return void
     */
    public function testEnumeratedColumnQueryCode(array $builderArgs, string $fileName): void
    {
        $queryClassCode = $this->buildQueryClassCode(...$builderArgs);
        $this->assertStringEqualsFile($fileName, $queryClassCode);
    }

    /**
     * @param string $columnXml
     * @param \Propel\Generator\Builder\Om\BuilderType $builderType
     *
     * @return \Propel\Generator\Builder\Om\AbstractOMBuilder
     */
    public function buildCodeBuilder(
        string $columnXml,
        BuilderType $builderType,
        array|null $extraConfig = null,
        PlatformInterface|null $platform = null
    ): AbstractOMBuilder {
        $column = $this->buildColumnFromSchema($columnXml);
        $config = new QuickGeneratorConfig($extraConfig);

        return $config->loadConfiguredBuilder($column->getTable(), $builderType);
    }

    /**
     * @param string $columnXml
     * @param array|null $extraConfig
     * @param \Propel\Generator\Platform\PlatformInterface|null $platform
     *
     * @return \Propel\Generator\Model\Column
     */
    public function buildColumnFromSchema(string $columnXml, array|null $extraConfig = null, PlatformInterface|null $platform = null): Column
    {
        $schema = <<<EOF
                <database>
                    <table name="table">
                        $columnXml
                    </table>
                </database>
EOF;
        $schema = $this->buildDatabaseFromSchema($schema, $extraConfig, $platform ?? new MysqlPlatform());

        return $schema->getTable('table')->getColumns()[0];
    }
}
