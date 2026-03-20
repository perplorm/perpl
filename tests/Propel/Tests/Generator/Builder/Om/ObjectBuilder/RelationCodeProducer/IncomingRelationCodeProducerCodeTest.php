<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Builder\Om\ObjectBuilder\RelationCodeProducer;

use Propel\Generator\Builder\Om\ObjectBuilder\RelationCodeProducer\AbstractIncomingRelationCode;
use Propel\Tests\Attributes\ComparesGeneratedFile;
use Propel\Tests\CompareGeneratedCodeTestCase;

/**
 * Builds RelationCodeProducer output into a single string and compares it against file content.
 *
 * Call tests/bin/rebuild-reference-files to update content of files.
 */
class IncomingRelationCodeProducerCodeTest extends CompareGeneratedCodeTestCase
{
    /**
     * @return string[][]
     */
    #[ComparesGeneratedFile(textBuilder: 'buildCodeForIncomingRelation')]
    public function IncomingRelationCodeDataProvider(): array
    {
        return [ // [schema, expected code file name]
            [static::MANY_TO_ONE_RELATION_CODE_SCHEMA, __DIR__ . '/expected_relation_code/many_to_one_relation_code_reference.txt'],
            [static::MANY_TO_ONE_NAMED_RELATION_CODE_SCHEMA, __DIR__ . '/expected_relation_code/many_to_one_named_relation_code_reference.txt'],
            [static::ONE_TO_ONE_RELATION_CODE_SCHEMA, __DIR__ . '/expected_relation_code/one_to_one_relation_code_reference.txt'],
        ];
    }

    /**
     * Build relation code of table `user` from given schema.
     *
     * @param string $schema
     *
     * @return string
     */
    public function buildCodeForIncomingRelation(string $schema): string
    {
        $objectBuilder = FkRelationCodeProducerCodeTest::createObjectBuilder($schema, 'user');
        /** @var AbstractIncomingRelationCode $codeProducer */
        $codeProducer = $this->getObjectPropertyValue($objectBuilder, 'incomingRelationCodeProducers')[0];

        $code = $this->generateCodeFileContentScript($codeProducer, [
            'addAttributes',
            'addOnReloadCode',
            'addScheduledForDeletionAttribute',
            'addDeleteScheduledItemsCode',
            'addClearReferencesCode',
            'addMethods',
        ]) ;

        $initRelationsCode = '';
        AbstractIncomingRelationCode::addInitRelations($initRelationsCode, $codeProducer->getTable()->getReferrers(), $codeProducer->getPluralizer());
        $code .= $this->buildCodeFileContent('addInitRelations', $initRelationsCode);

        return $code;
    }

    /**
     * @dataProvider IncomingRelationCodeDataProvider
     *
     * @param string $schema
     * @param string $fileName
     *
     * @return void
     */
    public function testIncomingRelationCode(string $schema, string $fileName): void
    {
        $code = $this->buildCodeForIncomingRelation($schema);
        $this->assertStringEqualsFile($fileName, $code, CompareGeneratedCodeTestCase::HOW_TO_UPDATE_MESSAGE);
    }

    protected const MANY_TO_ONE_RELATION_CODE_SCHEMA = <<<EOF
<database>
    <table name="user">
        <column name="id" type="INTEGER" primaryKey="true" autoIncrement="true"/>
    </table>

    <table name="team">
        <column name="id" type="INTEGER" autoIncrement="true" primaryKey="true" required="true"/>
        <column name="contact_user_id" type="INTEGER" />

        <foreign-key foreignTable="user">
            <reference local="contact_user_id" foreign="id" />
        </foreign-key>
    </table>
EOF;

    protected const MANY_TO_ONE_NAMED_RELATION_CODE_SCHEMA = <<<EOF
<database>
    <table name="user">
        <column name="id" type="INTEGER" primaryKey="true" autoIncrement="true"/>
    </table>

    <table name="team">
        <column name="id" type="INTEGER" autoIncrement="true" primaryKey="true" required="true"/>
        <column name="contact_user_id" type="INTEGER" />

        <foreign-key foreignTable="user" refPhpName="LeTeam">
            <reference local="contact_user_id" foreign="id" />
        </foreign-key>
    </table>
EOF;

    /**
     * Local FK column is part of PK
     *
     * @var string
     */
    protected const ONE_TO_ONE_RELATION_CODE_SCHEMA = <<<EOF
<database>
    <table name="user">
        <column name="id" type="INTEGER" primaryKey="true" autoIncrement="true"/>
    </table>

    <table name="team">
        <column name="id" type="INTEGER" autoIncrement="true" primaryKey="true" required="true"/>
        <column name="contact_user_id" type="INTEGER" primaryKey="true"/>

        <foreign-key foreignTable="user">
            <reference local="contact_user_id" foreign="id" />
        </foreign-key>
    </table>
EOF;

}
