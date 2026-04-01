<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Builder\Om\ObjectBuilder\RelationCodeProducer;

use Propel\Tests\Attributes\ComparesGeneratedFile;
use Propel\Tests\CompareGeneratedCodeTestCase;

/**
 * Builds RelationCodeProducer output into a single string and compares it against file content.
 *
 * Call tests/bin/rebuild-reference-files to update content of files.
 */
class CrossRelationCodeProducerCodeTest extends CompareGeneratedCodeTestCase
{
    /**
     * @return array<array{string, string}>
     */
    #[ComparesGeneratedFile(textBuilder: 'buildCodeForCrossRelation')]
    public static function CrossRelationCodeDataProvider(): array
    {
        return [ // [schema, expected code file name]
            [static::MANY_TO_MANY_RELATION_CODE_SCHEMA, __DIR__ . '/expected_relation_code/many_to_many_relation_code_reference.txt'],
            [static::MANY_TO_MANY_RELATION_WITH_PARAMETER_CODE_SCHEMA, __DIR__ . '/expected_relation_code/many_to_many_relation_with_parameter_code_reference.txt'],
            [static::MANY_TO_MANY_NAMED_RELATION_CODE_SCHEMA, __DIR__ . '/expected_relation_code/many_to_many_named_relation_code_reference.txt'],
            [static::TERNARY_NAMED_RELATION_CODE_SCHEMA, __DIR__ . '/expected_relation_code/ternary_named_relation_code_reference.txt'],
            [static::TERNARY_RELATION_CODE_SCHEMA, __DIR__ . '/expected_relation_code/ternary_relation_code_reference.txt'],
        ];
    }

    /**
     * Build relation code of table `user` from given schema.
     *
     * @param string $schema
     *
     * @return string
     */
    public function buildCodeForCrossRelation(string $schema): string
    {
        $objectBuilder = FkRelationCodeProducerCodeTest::createObjectBuilder($schema, 'user');
        /** @var \Propel\Generator\Builder\Om\ObjectBuilder\RelationCodeProducer\AbstractManyToManyCodeProducer $codeProducer */
        $codeProducer = $this->getObjectPropertyValue($objectBuilder, 'crossRelationCodeProducers')[0];

        return $this->generateCodeFileContentScript($codeProducer, [
            'addAttributes',
            'addScheduledForDeletionAttribute',
            'addMethods',
            'addOnReloadCode',
            'addDeleteScheduledItemsCode',
            'addClearReferencesCode',
        ]);
    }

    /**
     *
     * @param string $schema
     * @param string $fileName
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('CrossRelationCodeDataProvider')]
    public function testCrossRelationCode(string $schema, string $fileName): void
    {
        $code = $this->buildCodeForCrossRelation($schema);
        $this->assertStringEqualsFile($fileName, $code, CompareGeneratedCodeTestCase::HOW_TO_UPDATE_MESSAGE);
    }

    /* 
     * User <---n--- member of ---m---> Team
     */
    protected const MANY_TO_MANY_RELATION_CODE_SCHEMA = <<<EOF
<database>

    <table name="user">
        <column name="id" type="INTEGER" primaryKey="true" autoIncrement="true"/>
    </table>

    <table name="team_user" isCrossRef="true">
        <column name="team_id" type="INTEGER" primaryKey="true" />
        <column name="user_id" type="INTEGER" primaryKey="true" />

        <foreign-key foreignTable="team">
            <reference local="team_id" foreign="id" />
        </foreign-key>
        <foreign-key foreignTable="user">
            <reference local="user_id" foreign="id" />
        </foreign-key>
    </table>

    <table name="team">
        <column name="id" type="INTEGER" autoIncrement="true" primaryKey="true" required="true"/>
    </table>
</database>
EOF;

    /* 
     * fk relations are named (phpName)
     */
    protected const MANY_TO_MANY_NAMED_RELATION_CODE_SCHEMA = <<<EOF
<database>
    <table name="user">
        <column name="id" type="INTEGER" primaryKey="true" autoIncrement="true"/>
    </table>

    <table name="team_user" isCrossRef="true">
        <column name="team_id" type="INTEGER" primaryKey="true" />
        <column name="user_id" type="INTEGER" primaryKey="true" />

        <foreign-key foreignTable="user" phpName="LeUser">
            <reference local="user_id" foreign="id" />
        </foreign-key>

        <foreign-key foreignTable="team" phpName="LeTeam">
            <reference local="team_id" foreign="id" />
        </foreign-key>
    </table>

    <table name="team">
        <column name="id" type="INTEGER" autoIncrement="true" primaryKey="true" required="true"/>
    </table>
</database>
EOF;

    /**
     * m2m table has additional pk columns
     */
    protected const MANY_TO_MANY_RELATION_WITH_PARAMETER_CODE_SCHEMA = <<<EOF
<database>

    <table name="user">
        <column name="id" type="INTEGER" primaryKey="true" autoIncrement="true"/>
    </table>

    <table name="team_user" isCrossRef="true">

        <!-- additional PKs not used in FK makes this multiple -->
        <column name="day" type="DATETIME" primaryKey="true" required="true" />
        <column name="type" type="INTEGER" primaryKey="true" required="true" />

        <column name="user_id" type="INTEGER" primaryKey="true" required="true" />
        <column name="team_id" type="INTEGER" primaryKey="true" required="true" />

        <foreign-key foreignTable="user">
            <reference local="user_id" foreign="id" />
        </foreign-key>

        <foreign-key foreignTable="team">
            <reference local="team_id" foreign="id" />
        </foreign-key>
    </table>

    <table name="team">
        <column name="id" type="INTEGER" autoIncrement="true" primaryKey="true" required="true"/>
    </table>
</database>
EOF;

    /**
     *                        /---m---> Team
     * User <---n--- member of
     *                        \---o---> Event
     */
    protected const TERNARY_RELATION_CODE_SCHEMA = <<<EOF
<database>

    <table name="user">
        <column name="id" type="INTEGER" primaryKey="true" autoIncrement="true"/>
    </table>

    <table name="team_user" isCrossRef="true">
        <column name="user_id" type="INTEGER" primaryKey="true" required="true" />
        <column name="team_id" type="INTEGER" primaryKey="true" required="true" />
        <column name="event_id" type="INTEGER" primaryKey="true" required="true" />

        <foreign-key foreignTable="user">
            <reference local="user_id" foreign="id" />
        </foreign-key>

        <foreign-key foreignTable="team">
            <reference local="team_id" foreign="id" />
        </foreign-key>

        <foreign-key foreignTable="event">
            <reference local="event_id" foreign="id" />
        </foreign-key>
    </table>

    <table name="team">
        <column name="id" type="INTEGER" autoIncrement="true" primaryKey="true" required="true"/>
    </table>

    <table name="event">
        <column name="id" type="INTEGER" autoIncrement="true" primaryKey="true" required="true"/>
    </table>
</database>
EOF;

    /**
     * relations are named (phpName)
     */
    protected const TERNARY_NAMED_RELATION_CODE_SCHEMA = <<<EOF
<database>

    <table name="user">
        <column name="id" type="INTEGER" primaryKey="true" autoIncrement="true"/>

    </table>

    <table name="team_user" isCrossRef="true">
        <column name="user_id" type="INTEGER" primaryKey="true" required="true" />
        <column name="team_id" type="INTEGER" primaryKey="true" required="true" />
        <column name="event_id" type="INTEGER" primaryKey="true" required="true" />
        <column name="date" type="datetime" primaryKey="true" required="true" />

        <foreign-key foreignTable="user" phpName="LeUser">
            <reference local="user_id" foreign="id" />
        </foreign-key>

        <foreign-key foreignTable="team" phpName="LeTeam">
            <reference local="team_id" foreign="id" />
        </foreign-key>

        <foreign-key foreignTable="event" phpName="LeEvent">
            <reference local="event_id" foreign="id" />
        </foreign-key>
    </table>

    <table name="team">
        <column name="id" type="INTEGER" autoIncrement="true" primaryKey="true" required="true"/>
    </table>

    <table name="event">
        <column name="id" type="INTEGER" autoIncrement="true" primaryKey="true" required="true"/>
    </table>
</database>
EOF;
}
