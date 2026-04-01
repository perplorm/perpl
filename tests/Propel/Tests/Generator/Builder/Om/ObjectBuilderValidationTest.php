<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Builder\Om;

use Propel\Generator\Builder\Om\ObjectBuilder;
use Propel\Generator\Config\GeneratorConfig;
use Propel\Generator\Exception\EngineException;
use Propel\Generator\Util\QuickBuilder;
use Propel\Tests\TestCase;

class ObjectBuilderValidationTest extends TestCase
{
    /**
     *
     * @param string $description
     * @param string $schema
     * @param string $tableName
     * @param string $exceptionClass
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('ExceptionSchemaDataProducer')]
    public function testExceptionIsThrown(string $description, string $tableName, string $schema, string $exceptionClass): void
    {
        $objectBuilder = $this->createObjectBuilder($schema, $tableName);
        $this->expectException($exceptionClass);
        $this->callMethod($objectBuilder, 'validateModel');
    }

    public static function ExceptionSchemaDataProducer(): array
    {
        return [
            [
                /*
                    user <-----> member of <-----> team
                      ------- has main team ---------^
                */
                'Same identifier on cross ref and fk',
                'user',
                '
                <database>
                    <table name="user">
                        <column name="id" type="INTEGER" primaryKey="true" autoIncrement="true"/>
                        <column name="main_team_id" type="INTEGER"/>

                        <foreign-key foreignTable="team">
                            <reference local="main_team_id" foreign="id" />
                        </foreign-key>

                    </table>

                    <table name="team_user" isCrossRef="true">
                        <column name="team_id" type="INTEGER" primaryKey="true" />
                        <column name="user_id" type="INTEGER" primaryKey="true" />

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
                ',
                EngineException::class
            ],[
                /*
                    user <----> member of <----> team
                      ^-------- leads -------------
                */
                'Same identifier on cross ref and back fk',
                'user',
                '
                <database>
                    <table name="user">
                        <column name="id" type="INTEGER" primaryKey="true" autoIncrement="true"/>
                    </table>

                    <table name="team_user" isCrossRef="true">
                        <column name="team_id" type="INTEGER" primaryKey="true" />
                        <column name="user_id" type="INTEGER" primaryKey="true" />

                        <foreign-key foreignTable="user">
                            <reference local="user_id" foreign="id" />
                        </foreign-key>

                        <foreign-key foreignTable="team">
                            <reference local="team_id" foreign="id" />
                        </foreign-key>
                    </table>

                    <table name="team">
                        <column name="id" type="INTEGER" autoIncrement="true" primaryKey="true" required="true"/>
                        <column name="leader_id" type="INTEGER"/>

                        <foreign-key foreignTable="user">
                            <reference local="leader_id" foreign="id" />
                        </foreign-key>
                    </table>
                </database>
                ',
                EngineException::class
            ],[
                /*
                    user <-----> member of <----> team
                            ^
                            |
                            ---- backref also called "Team"
                */
                'fk manually named to table name',
                'user',
                '
                <database>
                    <table name="user">
                        <column name="id" type="INTEGER" primaryKey="true" autoIncrement="true"/>
                    </table>

                    <table name="team_user" isCrossRef="true">
                        <column name="team_id" type="INTEGER" primaryKey="true" />
                        <column name="user_id" type="INTEGER" primaryKey="true" />

                        <foreign-key foreignTable="user" refPhpName="Team">
                            <reference local="user_id" foreign="id" />
                        </foreign-key>

                        <foreign-key foreignTable="team">
                            <reference local="team_id" foreign="id" />
                        </foreign-key>
                    </table>

                    <table name="team">
                        <column name="id" type="INTEGER" autoIncrement="true" primaryKey="true" required="true"/>
                        <column name="leader_id" type="INTEGER"/>

                        <foreign-key foreignTable="user">
                            <reference local="leader_id" foreign="id" />
                        </foreign-key>
                    </table>
                </database>
                ',
                EngineException::class
            ],
        ];
    }

    /**
     *
     * @param string $description
     * @param string $schema
     * @param string $tableName
     * @param string $exceptionClass
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('NoExceptionSchemaDataProducer')]
    public function testNoException(string $description, string $tableName, string $schema): void
    {
        $objectBuilder = $this->createObjectBuilder($schema, $tableName);
        $this->callMethod($objectBuilder, 'validateModel');
        $this->assertTrue(true);
    }


    public static function NoExceptionSchemaDataProducer(): array
    {
        return [
            [
                'Same identifier on cross ref and fk',
                'user',
                '
                <database>
                    <table name="user">
                        <column name="id" type="INTEGER" primaryKey="true" autoIncrement="true"/>
                        <column name="main_team_id" type="INTEGER"/>

                        <foreign-key foreignTable="team" phpName="MainTeam">
                            <reference local="main_team_id" foreign="id" />
                        </foreign-key>

                    </table>

                    <table name="team_user" isCrossRef="true">
                        <column name="team_id" type="INTEGER" primaryKey="true" />
                        <column name="user_id" type="INTEGER" primaryKey="true" />

                        <foreign-key foreignTable="user" refPhpName="Team">
                            <reference local="user_id" foreign="id" />
                        </foreign-key>

                        <foreign-key foreignTable="team" phpName="TeamMembership">
                            <reference local="team_id" foreign="id" />
                        </foreign-key>
                    </table>

                    <table name="team">
                        <column name="id" type="INTEGER" autoIncrement="true" primaryKey="true" required="true"/>
                        <column name="leader_id" type="INTEGER"/>

                        <foreign-key foreignTable="user" refPhpName="UserTeams">
                            <reference local="leader_id" foreign="id" />
                        </foreign-key>
                    </table>
                </database>
                ',
            ]
        ];
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return \Propel\Generator\Builder\Om\ObjectBuilder
     */
    protected function createObjectBuilder(string $schema, string $tableName): ObjectBuilder
    {
        $database = QuickBuilder::parseSchema($schema);
        $table = $database->getTable($tableName);
        $objectBuilder = new ObjectBuilder($table);
        $config = new GeneratorConfig(null, static::generatorConfig);
        $objectBuilder->setGeneratorConfig($config);

        return $objectBuilder;
    }

    protected const generatorConfig = [
        'propel' => [
            'database' => [
                'connections' => [
                    'foo' => [
                        'adapter' => 'mysql',
                        'dsn' => 'mysql:foo',
                        'user' => 'foo',
                        'password' => 'foo'
                    ],
                ],
            ],
            'generator' => [
                'defaultConnection' => 'foo',
                'connections' => ['foo'],
            ],
        ]
    ];
}
