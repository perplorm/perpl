<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Builder\Om;

use Propel\Generator\Builder\Om\ObjectCollectionBuilder;


/**
 * Utility class for QueryBuilder.
 */
class ObjectCollectionBuilderTest extends AgnosticBuilderTestCase
{
    /**
     * @dataProvider NoSkipDataProvider
     *
     * @param string $schema
     * @param string $tableName
     * @param bool $expectSkip
     *
     * @return void
     */
    public function testSkip(string $description, string $schema, string $tableName, bool $expectNoSkip ): void
    {
        $builder = $this->createBuilder($schema, $tableName);

        $this->assertSame(!$expectNoSkip, $builder->skip(), $description);
    }

    /**
     * @return array<array{string, string, string, bool}>
     */
    public function NoSkipDataProvider(): array
    {
        return [
            [
                'default no skip',
                '<database><table name="user"></table></database>',
                'user',
                true
            ], [
                'disabled by param on table',
                '<database>
                    <table name="user"
                        generate-collection="false"
                    ></table>
                </database>',
                'user',
                false
            ], [
                'enabled by param on table',
                '<database>
                    <table name="user"
                        generate-collection="true"
                    ></table>
                </database>',
                'user',
                true
            ], [
                'disabled by param on database',
                '<database generate-collection="false">
                    <table name="user"></table>
                </database>',
                'user',
                false
            ], [
                'overriding param on database',
                '<database generate-collection="false">
                    <table name="user"
                        generate-collection="true"
                    ></table>
                </database>',
                'user',
                true
            ],
        ];
    }

    /**
     * @dataProvider ClassNameAndTypeDataProvider
     *
     * @param string $description
     * @param string $schema
     * @param string $tableName
     * @param string $expectedClassName
     * @param string $expectedType
     *
     * @return void
     */
    public function testCollectionClassNameAndType(string $description, string $schema, string $tableName, string $expectedClassName, string $expectedType): void
    {
        $builder = $this->createBuilder($schema, $tableName);

        $className = $this->callMethod($builder, 'resolveParentCollectionClassNameFq');
        $this->assertSame($expectedClassName, $className, 'Class name on ' . $description);

        $type = $this->callMethod($builder, 'resolveParentCollectionType');
        $this->assertSame($expectedType, $type, 'Type on ' . $description);
    }

    /**
     * @return array<array{string, string, string, string, string}>
     */
    public function ClassNameAndTypeDataProvider(): array
    {
        return [
            [
                'defaults to ObjectCollection',
                '<database>
                    <table name="user"></table>
                </database>',
                'user',
                '\Propel\Runtime\Collection\ObjectCollection',
                '\Propel\Runtime\Collection\ObjectCollection<\User>'
            ], [
                'set by collection-class parameter on table',
                '<database collection-class="\Not\Foo\Collection">
                    <table name="user" collection-class="\Foo\Collection"></table>
                </database>',
                'user',
                '\Foo\Collection',
                '\Foo\Collection'
            ], [
                'set by collection-class parameter on database',
                '<database collection-class="\Foo\Collection">
                    <table name="user"></table>
                </database>',
                'user',
                '\Foo\Collection',
                '\Foo\Collection'
            ]
        ];
    }

    /**
     * @dataProvider MappingDataProvider
     *
     * @param string $description
     * @param string $schema
     * @param string $tableName
     * @param array $expectedMapping
     *
     * @return void
     */
    public function testRelationTableTypes(string $description, string $schema, string $tableName, array $expectedMapping): void
    {
        $builder = $this->createBuilder($schema, $tableName);

        $mapping = $this->callMethod($builder, 'buildCollectionMappings')[0];
        $this->assertEquals($expectedMapping, $mapping, $description);

    }

    /**
     * @return array<array{string, string, string, string, string}>
     */
    public function MappingDataProvider(): array
    {
        return [
            [
                'defaults to generated collection',
                '<database>
                    <table name="user">
                        <column name="team_id" />
                        <foreign-key foreignTable="team">
                            <reference local="team_id" foreign="id" />
                        </foreign-key>
                    </table>
                    <table name="team">
                        <column name="id"/>
                    </table>
                </database>',
                'user',
                [
                    'relationIdentifier' => 'Team',
                    'relationIdentifierInMethod' => 'Team',
                    'collectionClassType' => '\Base\Collection\TeamCollection',
                    'collectionClassName' => 'TeamCollection',
                    'collectionClassNameFq' => '\Base\Collection\TeamCollection',
                ]
            ], [
                'uses skip',
                '<database>
                    <table name="user">
                        <column name="team_id" />
                        <foreign-key foreignTable="team" phpName="LeTeam">
                            <reference local="team_id" foreign="id"/>
                        </foreign-key>
                    </table>
                    <table name="team" generate-collection="false">
                        <column name="id"/>
                    </table>
                </database>',
                'user',
                [
                    'relationIdentifier' => 'LeTeam',
                    'relationIdentifierInMethod' => 'LeTeam',
                    'collectionClassType' => '\Propel\Runtime\Collection\ObjectCollection<Team>',
                    'collectionClassName' => 'ObjectCollection',
                    'collectionClassNameFq' => '\Propel\Runtime\Collection\ObjectCollection',
                ]
            ],
        ];
    }

    /**
     * @param string $schema
     * @param string $tableName
     *
     * @return ObjectCollectionBuilder
     */
    protected function createBuilder(string $schema, string $tableName): ObjectCollectionBuilder
    {
        return $this->setupBuilder(ObjectCollectionBuilder::class, $schema, $tableName);
    }
}
