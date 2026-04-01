<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Builder\Om;

use Propel\Generator\Builder\Om\BuilderType;
use Propel\Generator\Builder\Om\ExtensionQueryInheritanceBuilder;
use Propel\Generator\Builder\Om\MultiExtendObjectBuilder;
use Propel\Generator\Builder\Om\QueryInheritanceBuilder;
use Propel\Generator\Config\QuickGeneratorConfig;
use Propel\Tests\Attributes\ComparesGeneratedFile;
use Propel\Tests\CompareGeneratedCodeTestCase;

/**
 * Build output into a single string and compares it against file content.
 *
 * Call tests/bin/rebuild-reference-files to update content of files.
 */
class CodeBuilderTest extends CompareGeneratedCodeTestCase
{
    /**
     * @return array<array{array{string,BuilderType,array|null}, string}>
     */
    #[ComparesGeneratedFile(textBuilder: 'buildCode')]
    public static function CodeBuilderDataProvider(): array
    {
        $targetDir =  __DIR__ . '/expected_builder_code';

        return [ // [[table name, builder type, config], code file name]
            [['id_table', BuilderType::ObjectBase, null], "$targetDir/simple_base_model_reference.txt"],
            [['id_table', BuilderType::ObjectStub, null], "$targetDir/simple_stub_model_reference.txt"],
            [['id_table', BuilderType::QueryBase, null], "$targetDir/simple_base_query_reference.txt"],
            [['id_table', BuilderType::QueryStub, null], "$targetDir/simple_stub_query_reference.txt"],
            [['id_table', BuilderType::TableMap, null], "$targetDir/simple_tablemap_reference.txt"],

            [['relation_table', BuilderType::Collection, null], "$targetDir/simple_collection_reference.txt"],

            [['genspec_table', BuilderType::QueryInheritance, null], "$targetDir/spec_query_base_reference.txt"],
            [['genspec_table', BuilderType::QueryInheritanceStub, null], "$targetDir/spec_query_stub_reference.txt"],
            [['genspec_table', BuilderType::ObjectInheritanceStub, null], "$targetDir/spec_model_reference.txt"],
            [['genspec_table', BuilderType::TableMap, null], "$targetDir/genspec_tablemap_reference.txt"],

        ];
    }

    /**
     * @param string $tableName
     * @param BuilderType $builderType
     * @param array|null $config
     *
     * @return string
     */
    public function buildCode(string $tableName, BuilderType $builderType, array|null $config): string
    {
        $database = $this->buildDatabaseFromSchema(static::SCHEMA);
        $table = $database->getTable($tableName);
        $builder = (new QuickGeneratorConfig($config))->loadConfiguredBuilder($table, $builderType);

        if ($builder instanceof MultiExtendObjectBuilder || $builder instanceof QueryInheritanceBuilder || $builder instanceof ExtensionQueryInheritanceBuilder){
            $inheritance = array_reverse($table->getChildrenColumn()->getChildren())[0];
            $builder->setChild($inheritance);
        }

        return $builder->build();
    }

    /**
     *
     * @param array{string,BuilderType,array|null} $builderArgs
     * @param string $fileName
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('CodeBuilderDataProvider')]
    public function testCodeBuilderOutput(array $builderArgs, string $fileName): void
    {
        $code = $this->buildCode(...$builderArgs);
        $this->assertStringEqualsFile($fileName, $code, CompareGeneratedCodeTestCase::HOW_TO_UPDATE_MESSAGE);
    }

    /**
     * Sparse on columns and fks, those are tested separately.
     *
     * @var string
     */
    protected const SCHEMA = <<<EOF
<database>
    <table name="id_table">
        <column name="id" type="INTEGER" primaryKey="true" autoIncrement="true"/>
    </table>

    <table name="relation_table">
        <column name="id" type="INTEGER" primaryKey="true" autoIncrement="true"/>
    </table>
    <table name="fk_table">
        <column name="id" type="INTEGER" primaryKey="true" autoIncrement="true"/>

        <foreign-key foreignTable="relation_table" refPhpName="LeRelatedObject">
            <reference local="id" foreign="id" />
        </foreign-key>
    </table>

    <table name="genspec_table">
        <column name="id" type="INTEGER" primaryKey="true" autoIncrement="true"/>
        <column name="type" type="INTEGER" required="true" default="0" inheritance="single">
            <inheritance key="11" class="Gen"/>
            <inheritance key="22" class="Spec" extends="Gen"/>
        </column>
    </table>

</database>
EOF;
}
