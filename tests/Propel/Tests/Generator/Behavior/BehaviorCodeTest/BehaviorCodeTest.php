<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Behavior\BehaviorCodeTest;

use Propel\Generator\Builder\Om\AbstractOMBuilder;
use Propel\Generator\Builder\Om\BuilderType;
use Propel\Generator\Builder\Om\ObjectBuilder;
use Propel\Generator\Config\QuickGeneratorConfig;
use Propel\Generator\Platform\PlatformInterface;
use Propel\Tests\Attributes\ComparesGeneratedFile;
use Propel\Tests\CompareGeneratedCodeTestCase;
use RuntimeException;

class BehaviorCodeTest extends CompareGeneratedCodeTestCase
{
    /**
     * @return array<array<string>>
     */
    #[ComparesGeneratedFile(textBuilder: 'buildBehaviorCode')]
    public static function BehaviorDataProvider(): array
    {
        $refDir = __DIR__ . '/expected_behavior_code';
        $accessor = new CompareGeneratedCodeTestCase('');

        return [ // [schema, array{fileName, builder}]
            [
                [static::AGGREGATE_COLUMN_SCHEMA, 'aggregated_table', BuilderType::ObjectBase,],
                "$refDir/aggregate_column---target_model_reference.txt",
            ],
            [
                [static::AGGREGATE_COLUMN_SCHEMA, 'source_table', BuilderType::ObjectBase,[
                    'objectFilter' => fn(ObjectBuilder $builder, string &$script) => $accessor->getObjectPropertyValue($builder, 'fkRelationCodeProducers')[0]->addMethods($script)
                ]],
                "$refDir/aggregate_column---source_model_reference.txt",
            ],
            [
                [static::AGGREGATE_MULTIPLE_COLUMNS_SCHEMA, 'aggregated_table', BuilderType::ObjectBase,],
                "$refDir/aggregate_multiple_columns---target_model_reference.txt",
            ],
            [
                [static::ARCHIVABLE_SCHEMA, 'table', BuilderType::ObjectBase,],
                "$refDir/archivable---model_reference.txt",
            ],
            [
                [static::ARCHIVABLE_SCHEMA, 'table', BuilderType::QueryBase,],
                "$refDir/archivable---query_reference.txt",
            ],
            [
                [static::CONCRETE_INHERITANCE_SCHEMA, 'parent', BuilderType::ObjectBase,],
                "$refDir/concrete-inheritance---parent-model_reference.txt",
            ],
            [
                [static::CONCRETE_INHERITANCE_SCHEMA, 'child', BuilderType::ObjectBase,],
                "$refDir/concrete-inheritance---child-model_reference.txt",
            ],
            [
                [static::DELEGATE_SCHEMA, 'delegator', BuilderType::ObjectBase, [
                    'objectFilter' => fn(ObjectBuilder $builder, string &$script) => $accessor->callMethod($builder, 'addToArray', [&$script])
                ]],
                "$refDir/delegate---delegator-model_reference.txt",
            ],
            [
                [static::DELEGATE_SCHEMA, 'delegator', BuilderType::QueryBase,],
                "$refDir/delegate---delegator-query_reference.txt",
            ],
            [
                [static::I18N_SCHEMA, 'table', BuilderType::ObjectBase, [
                    'objectFilter' => fn(ObjectBuilder $builder, string &$script) => $accessor->getObjectPropertyValue($builder, 'incomingRelationCodeProducers')[0]->addMethods($script)
                ]],
                "$refDir/i18n---object_reference.txt",
            ],
            [
                [static::I18N_SCHEMA, 'table', BuilderType::QueryBase,],
                "$refDir/i18n---query_reference.txt",
            ],
            [
                [static::NESTED_SET_SCHEMA, 'table', BuilderType::ObjectBase,],
                "$refDir/nested_set---object_reference.txt",
            ],
            [
                [static::NESTED_SET_SCHEMA, 'table', BuilderType::QueryBase,],
                "$refDir/nested_set---query_reference.txt",
            ],
            [
                [static::OUTPUT_GROUP_SCHEMA, 'table', BuilderType::ObjectBase,],
                "$refDir/output_group---object_reference.txt",
            ],
            [
                [static::OUTPUT_GROUP_SCHEMA, 'table', BuilderType::TableMap,],
                "$refDir/output_group---tablemap_reference.txt",
            ],
            [
                [static::QUERY_CACHE_SCHEMA, 'table', BuilderType::QueryBase,],
                "$refDir/query_cache---query_reference.txt",
            ],
            [
                [static::SLUGGABLE_SCHEMA, 'table', BuilderType::ObjectBase,],
                "$refDir/sluggable---model_reference.txt",
            ],
            [
                [static::SLUGGABLE_SCHEMA, 'table', BuilderType::QueryBase,],
                "$refDir/sluggable---query_reference.txt",
            ],
            [
                [static::SORTABLE_SCHEMA, 'table', BuilderType::ObjectBase,],
                "$refDir/sortable---object_reference.txt",
            ],
            [
                [static::SORTABLE_SCHEMA, 'table', BuilderType::QueryBase,],
                "$refDir/sortable---query_reference.txt",
            ],
            [
                [static::SORTABLE_SCHEMA, 'table', BuilderType::TableMap,],
                "$refDir/sortable---tablemap_reference.txt",
            ],
            [
                [static::TIMESTAMPABLE_SCHEMA, 'table', BuilderType::ObjectBase,],
                "$refDir/timestampable---object_reference.txt",
            ],
            [
                [static::TIMESTAMPABLE_SCHEMA, 'table', BuilderType::QueryBase,],
                "$refDir/timestampable---query_reference.txt",
            ],
            [
                [static::VALIDATEABLE_SCHEMA, 'table', BuilderType::ObjectBase,],
                "$refDir/validateable---object_reference.txt",
            ],
            [
                [static::VERSIONABLE_SCHEMA, 'table', BuilderType::ObjectBase,],
                "$refDir/versionable---object_reference.txt",
            ],
            [
                [static::VERSIONABLE_SCHEMA, 'table', BuilderType::QueryBase,],
                "$refDir/versionable---query_reference.txt",
            ],
        ];
    }

    /**
     * @param string $schemaXml
     * @param array $config
     * @param \Propel\Generator\Platform\PlatformInterface $platform
     *
     * @return string
     */
    public function buildBehaviorCode(string $schemaXml, string $tableName, BuilderType $builderType, array $hookInput = []): string
    {
        $builder = $this->buildCodeBuilder($schemaXml, $builderType, $tableName);
        $code = '';

        foreach (static::getHooks($builderType) as $hook) {
            $script = '';
            if (array_key_exists($hook, $hookInput)) {
                $hookInput[$hook]($builder, $script);
            }
            $builder->applyBehaviorModifier($hook, $script, '');
            if (!$script) {
                continue;
            }
            $code .= $this->buildCodeFileContent($hook, $script);
        }

        return $code;
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('BehaviorDataProvider')]
    public function testEnumeratedColumnQueryCode(array $builderArgs, string $fileName): void
    {
        $code = $this->buildBehaviorCode(...$builderArgs);
        $this->assertStringEqualsFile($fileName, $code, CompareGeneratedCodeTestCase::HOW_TO_UPDATE_MESSAGE);
    }

    /**
     * @param string $schemaXml
     * @param \Propel\Generator\Builder\Om\BuilderType $builderType
     *
     * @return \Propel\Generator\Builder\Om\AbstractOMBuilder
     */
    public function buildCodeBuilder(
        string $schemaXml,
        BuilderType $builderType,
        string $tableName,
        array|null $extraConfig = null,
        PlatformInterface|null $platform = null
    ): AbstractOMBuilder {
        $database = static::buildDatabaseFromSchema($schemaXml, $extraConfig, $platform);
        $config = new QuickGeneratorConfig($extraConfig);
        $table = $database->getTable($tableName);

        return $config->loadConfiguredBuilder($table, $builderType);
    }

    public static function getHooks(BuilderType $builderType): array
    {
        return match ($builderType) {
            BuilderType::ObjectBase => [
                'objectMethods',
                'objectFilter',
                'objectAttributes',
                'postHydrate',
                'preDelete',
                'postDelete',
                'preSave',
                'preInsert',
                'preUpdate',
                'postSave',
                'postUpdate',
                'postInsert',
                'objectClearReferences',
                'objectCall',
            ],
            BuilderType::QueryBase => [
                'queryAttributes',
                'staticConstants',
                'staticAttributes',
                'staticMethods',
                'queryAttributes',
                'queryMethods',
                'queryFilter',
                'preSelectQuery',
                'preDeleteQuery',
                'postDeleteQuery',
                'preUpdateQuery',
                'postUpdateQuery',
            ],
            BuilderType::TableMap => [
                'staticConstants',
                'staticAttributes',
                'staticMethods',
                'tableMapFilter',
            ],
            BuilderType::QueryStub => [
                'extensionQueryFilter'
            ],
            BuilderType::ObjectStub => [
                'extensionObjectFilter'
            ],
            BuilderType::Collection => [
                'addObjectCollectionMethods',

            ],
            default => throw new RuntimeException("Builder type not registered: " . $builderType->name)
        };
    }

    protected const AGGREGATE_COLUMN_SCHEMA = <<<XML
    <database>
        <table name="aggregated_table">
            <column name="id" primaryKey="true" type="INTEGER"/>
            <behavior name="aggregate_column">
                <parameter name="foreign_table" value="source_table"/>
                <parameter name="name" value="related_entries"/>
                <parameter name="expression" value="COUNT(id)"/>
            </behavior>
        </table>

        <table name="source_table">
            <column name="id" primaryKey="true" type="INTEGER"/>
            <column name="fk" type="INTEGER"/>
            <foreign-key foreignTable="aggregated_table">
                <reference local="fk" foreign="id"/>
            </foreign-key>
        </table>
    </database>
XML;

    protected const AGGREGATE_MULTIPLE_COLUMNS_SCHEMA = <<<XML
    <database>
        <table name="aggregated_table">
            <column name="id" primaryKey="true" type="INTEGER"/>
            <behavior name="aggregate_multiple_columns">
                <parameter name="foreign_table" value="source_table"/>
                <parameter-list name="columns">
                    <parameter-list-item>
                        <parameter name="column_name" value="total_score" />
                        <parameter name="expression" value="SUM(score)" />
                    </parameter-list-item>
                    <parameter-list-item>
                        <parameter name="column_name" value="number_of_scores" />
                        <parameter name="expression" value="COUNT(score)" />
                    </parameter-list-item>
                </parameter-list>
            </behavior>
        </table>

        <table name="source_table">
            <column name="id" primaryKey="true" type="INTEGER"/>
            <column name="score" type="INTEGER"/>
            <column name="fk" type="INTEGER"/>
            <foreign-key foreignTable="aggregated_table">
                <reference local="fk" foreign="id"/>
            </foreign-key>
        </table>
    </database>
XML;

    protected const ARCHIVABLE_SCHEMA = <<<XML
    <database>
        <table name="table">
            <column name="id" primaryKey="true" type="INTEGER"/>
            <column name="title" type="VARCHAR" size="100" primaryString="true"/>
            <behavior name="archivable">
                <parameter name="archive_table" value="table_archive"/>
                <parameter name="archive_on_insert" value="true"/>
                <parameter name="archive_on_update" value="true"/>
                <parameter name="archive_on_delete" value="true"/>
            </behavior>
        </table>
    </database>
XML;

    protected const CONCRETE_INHERITANCE_SCHEMA = <<<XML
    <database>
        <table name="parent">
            <column name="id" primaryKey="true" type="INTEGER"/>
            <column name="title" type="VARCHAR" size="100"/>
        </table>
        <table name="child">
            <behavior name="concrete_inheritance">
                <parameter name="extends" value="parent"/>
            </behavior>
        </table>
    </database>
XML;

    protected const DELEGATE_SCHEMA = <<<XML
    <database>
        <table name="resolver">
            <column name="id" primaryKey="true" type="INTEGER"/>
            <column name="delegated_column" type="INTEGER"/>
        </table>

        <table name="delegator">
            <column name="id" primaryKey="true" type="INTEGER"/>
            <behavior name="delegate">
                <parameter name="to" value="resolver"/>
            </behavior>
        </table>
    </database>
XML;

    protected const I18N_SCHEMA = <<<XML
    <database>
        <table name="table">
            <column name="id" primaryKey="true" type="INTEGER"/>
            <column name="bar" type="VARCHAR" size="100"/>
            <behavior name="i18n">
                <parameter name="i18n_columns" value="bar"/>
                <parameter name="locale_column" value="language"/>
                <parameter name="locale_alias" value="culture"/>
            </behavior>
        </table>
    </database>
XML;

    protected const NESTED_SET_SCHEMA = <<<XML
    <database>
        <table name="table">
            <column name="id" required="true" primaryKey="true" type="INTEGER"/>
            <column name="title" type="VARCHAR" size="100"/>

            <behavior name="nested_set"/>
        </table>
    </database>
XML;

    protected const OUTPUT_GROUP_SCHEMA = <<<XML
    <database>
        <behavior name="output_group"/>

        <table name="table">
            <column name="col0" />
            <column name="col1" outputGroup="group2"/>
            <column name="col2" outputGroup="group1"/>
            <column name="col3" outputGroup="group1,group2"/>
        </table>
    </database>
XML;

    protected const QUERY_CACHE_SCHEMA = <<<XML
    <database>
        <table name="table">
            <column name="id" required="true" primaryKey="true" type="INTEGER"/>
            <column name="title" type="VARCHAR" size="100" primaryString="true"/>

            <behavior name="query_cache">
                <parameter name="backend" value="array"/>
            </behavior>
        </table>
    </database>
XML;

    protected const SLUGGABLE_SCHEMA = <<<XML
    <database>
        <table name="table">
            <column name="id" required="true" primaryKey="true" autoIncrement="true" type="INTEGER"/>
            <column name="title" type="VARCHAR" size="100" primaryString="true"/>
            <column name="url" type="VARCHAR" size="100"/>
            <behavior name="sluggable">
                <parameter name="slug_column" value="url"/>
                <parameter name="slug_pattern" value="/foo/{Title}/bar"/>
                <parameter name="replace_pattern" value="/[^\w\/]+/"/>
                <parameter name="separator" value="/"/>
                <parameter name="permanent" value="true"/>
            </behavior>
        </table>
    </database>
XML;

    protected const SORTABLE_SCHEMA = <<<XML
    <database>
        <table name="table">
            <column name="id" required="true" primaryKey="true" autoIncrement="true" type="INTEGER"/>
            <column name="title" type="VARCHAR" size="100" primaryString="true"/>

            <behavior name="sortable"/>
        </table>
    </database>
XML;

    protected const TIMESTAMPABLE_SCHEMA = <<<XML
    <database>
        <table name="table">
            <column name="id" type="INTEGER" primaryKey="true"/>
            <behavior name="timestampable"/>
        </table>
    </database>
XML;

    protected const VALIDATEABLE_SCHEMA = <<<XML
    <database>
        <table name="table">
            <column name="id" required="true" primaryKey="true" autoIncrement="true" type="INTEGER"/>
            <column name="name" type="VARCHAR"/>
            <column name="website" type="VARCHAR" size="24"/>
            <behavior name="validate">
                <parameter name="uniqueName" value="{column: name, validator: Unique}"/>
                <parameter name="sebsiteIsUrl" value="{column: website, validator: Url}"/>
            </behavior>
        </table>
    </database>
XML;

    protected const VERSIONABLE_SCHEMA = <<<XML
    <database>
        <table name="table">
            <column name="id" primaryKey="true" type="INTEGER" autoIncrement="true"/>
            <column name="bar" type="INTEGER"/>

            <behavior name="versionable">
                <parameter name="version_column" value="CustomVersion"/>
            </behavior>
        </table>
    </database>
XML;
}
