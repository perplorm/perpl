<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Builder\Om\ObjectBuilder\RelationCodeProducer;

use Propel\Generator\Builder\Om\ObjectBuilder;
use Propel\Generator\Config\QuickGeneratorConfig;
use Propel\Generator\Util\QuickBuilder;
use Propel\Tests\Attributes\ComparesGeneratedFile;
use Propel\Tests\CompareGeneratedCodeTestCase;

/**
 * Builds RelationCodeProducer output into a single string and compares it against file content.
 *
 * Call tests/bin/rebuild-reference-files to update content of files.
 */
class FkRelationCodeProducerCodeTest extends CompareGeneratedCodeTestCase
{
    /**
     * @return string[][]
     */
    #[ComparesGeneratedFile(textBuilder: 'buildCodeForRelation')]
    public static function RelationCodeDataProvider(): array
    {
        return [ // [schema, expected code file name]
            [static::ONE_TO_MANY_RELATION_CODE_SCHEMA, __DIR__ . '/expected_relation_code/one_to_many_relation_code_reference.txt'],
            [static::ONE_TO_MANY_NAMED_RELATION_CODE_SCHEMA, __DIR__ . '/expected_relation_code/one_to_many_named_relation_code_reference.txt'],
        ];
    }

    /**
     * Build relation code of table `user` from given schema.
     *
     * @param string $schema
     *
     * @return string
     */
    public function buildCodeForRelation(string $schema): string
    {
        $objectBuilder = $this->createObjectBuilder($schema, 'user');
        $codeProducer = $this->getObjectPropertyValue($objectBuilder, 'fkRelationCodeProducers')[0];

        return $this->generateCodeFileContentScript($codeProducer, [
            'addAttributes',
            'addOnReloadCode',
            'addDeleteScheduledItemsCode',
            'addClearReferencesCode',
            'addMethods',
        ]);
    }

    /**
     *
     * @param string $schema
     * @param string $fileName
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('RelationCodeDataProvider')]
    public function testFkRelationCode(string $schema, string $fileName): void
    {
        $code = $this->buildCodeForRelation($schema);
        $this->assertStringEqualsFile($fileName, $code, CompareGeneratedCodeTestCase::HOW_TO_UPDATE_MESSAGE);
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return \Propel\Generator\Builder\Om\ObjectBuilder
     */
    public static function createObjectBuilder(string $schema, string $tableName): ObjectBuilder
    {
        $database = QuickBuilder::parseSchema($schema);
        $table = $database->getTable($tableName);
        $objectBuilder = new ObjectBuilder($table);
        $objectBuilder->setGeneratorConfig(new QuickGeneratorConfig());

        return $objectBuilder;
    }

    protected const ONE_TO_MANY_RELATION_CODE_SCHEMA = <<<EOF
<database>
    <table name="user">
        <column name="id" type="INTEGER" primaryKey="true" autoIncrement="true"/>
        <column name="main_team_id" type="INTEGER"/>

        <foreign-key foreignTable="team">
            <reference local="main_team_id" foreign="id" />
        </foreign-key>
    </table>

    <table name="team">
        <column name="id" type="INTEGER" autoIncrement="true" primaryKey="true" required="true"/>
    </table>
EOF;

    protected const ONE_TO_MANY_NAMED_RELATION_CODE_SCHEMA = <<<EOF
<database>
    <table name="user">
        <column name="id" type="INTEGER" primaryKey="true" autoIncrement="true"/>
        <column name="main_team_id" type="INTEGER"/>

        <foreign-key foreignTable="team" phpName="LeTeam">
            <reference local="main_team_id" foreign="id" />
        </foreign-key>
    </table>

    <table name="team">
        <column name="id" type="INTEGER" autoIncrement="true" primaryKey="true" required="true"/>
    </table>
EOF;
}
