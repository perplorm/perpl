<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Config;

use Propel\Common\Config\ConfigurationManager;
use Propel\Common\Config\Exception\InvalidConfigurationException;
use Propel\Common\Config\PropelConfiguration;
use Propel\Common\Pluralizer\SimpleEnglishPluralizer;
use Propel\Common\Pluralizer\StandardEnglishPluralizer;
use Propel\Generator\Builder\Om\BuilderType;
use Propel\Generator\Builder\Om\QueryBuilder;
use Propel\Generator\Config\GeneratorConfig;
use Propel\Generator\Exception\BuildException;
use Propel\Generator\Exception\ClassNotFoundException;
use Propel\Generator\Exception\InvalidArgumentException;
use Propel\Generator\Model\Table;
use Propel\Generator\Platform\MysqlPlatform;
use Propel\Generator\Platform\PgsqlPlatform;
use Propel\Generator\Platform\SqlitePlatform;
use Propel\Generator\Reverse\PgsqlSchemaParser;
use Propel\Generator\Reverse\SqliteSchemaParser;
use Propel\Generator\Util\BehaviorLocator;
use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Tests\TestCase;
use Propel\Generator\Util\VfsTrait;
use ReflectionClass;

class GeneratorConfigTest extends TestCase
{
    use VfsTrait;

    protected $generatorConfig;

    /**
     * @return void
     */
    public function setConfig($config)
    {
        $this->setObjectPropertyValue($this->generatorConfig, 'config', $config, ConfigurationManager::class);
    }

    /**
     * @return void
     */
    public function setUp(): void
    {
        $tmpDir = sys_get_temp_dir();
        $config = [
            'propel' => [
                'database' => [
                    'connections' => [
                        'mysource' => [
                            'adapter' => 'sqlite',
                            'classname' => 'Propel\\Runtime\\Connection\\DebugPDO',
                            'dsn' => "sqlite:$tmpDir/mydb",
                            'user' => 'root',
                            'password' => '',
                            'model_paths' => [
                                'src',
                                'vendor',
                            ],
                        ],
                        'yoursource' => [
                            'adapter' => 'mysql',
                            'classname' => 'Propel\\Runtime\\Connection\\DebugPDO',
                            'dsn' => 'mysql:host=localhost;dbname=yourdb',
                            'user' => 'root',
                            'password' => '',
                            'model_paths' => [
                                'src',
                                'vendor',
                            ],
                        ],
                    ],
                ],
                'runtime' => [
                    'defaultConnection' => 'mysource',
                    'connections' => ['mysource', 'yoursource'],
                ],
                'generator' => [
                    'defaultConnection' => 'mysource',
                    'connections' => ['mysource', 'yoursource'],
                ],
            ]
        ];
        $this->generatorConfig = new GeneratorConfig(null, $config);
    }

    /**
     * @return void
     */
    public function testGetConfiguredPlatformDefault()
    {
        $actual = $this->generatorConfig->getConfiguredPlatform();

        $this->assertInstanceOf(SqlitePlatform::class, $actual);
    }

    /**
     * @return void
     */
    public function testGetConfiguredPlatformGivenDatabaseName()
    {
        $actual = $this->generatorConfig->getConfiguredPlatform(null, 'yoursource');

        $this->assertInstanceOf(MysqlPlatform::class, $actual);
    }

    /**
     * @return void
     */
    public function testGetConfiguredPlatform()
    {
        $this->setConfig(['generator' => ['platformClass' => PgsqlPlatform::class]]);
        $actual = $this->generatorConfig->getConfiguredPlatform();
        $this->assertInstanceOf(PgsqlPlatform::class, $actual);
    }

    /**
     * @return void
     */
    public function testGetConfiguredPlatformGivenBadDatabaseNameThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Database connection `badsource` is not a registered connection.

Update configuration or choose one of [`mysource`, `yoursource`]');

        $this->generatorConfig->getConfiguredPlatform(null, 'badsource');
    }

    /**
     * @return void
     */
    public function testGetConfiguredSchemaParserDefaultClass()
    {
        $stubCon = $this->getMockBuilder(ConnectionWrapper::class)
            ->disableOriginalConstructor()->getMock();

        $actual = $this->generatorConfig->getConfiguredSchemaParser($stubCon);

        $this->assertInstanceOf(SqliteSchemaParser::class, $actual);
    }

    /**
     * @return void
     */
    public function testGetConfiguredSchemaParserGivenClass()
    {
        $this->setConfig([
                'migrations' => [
                    'tableName' => 'propel_migration',
                    'parserClass' => PgsqlSchemaParser::class,
                ]
            ]);
        $stubCon = $this->getMockBuilder(ConnectionWrapper::class)
            ->disableOriginalConstructor()->getMock();

        $actual = $this->generatorConfig->getConfiguredSchemaParser($stubCon);

        $this->assertInstanceOf(PgsqlSchemaParser::class, $actual);
    }

    /**
     * @return void
     */
    public function testGetConfiguredSchemaParserGivenNonSchemaParserClass()
    {
        $this->setConfig([
                'migrations' => [
                    'tableName' => 'propel_migration',
                    'parserClass' => MysqlPlatform::class,
                ]
            ]);

        $this->expectException(BuildException::class);
        $this->expectExceptionMessage('Specified class (Propel\Generator\Platform\MysqlPlatform) does not implement Propel\Generator\Reverse\SchemaParserInterface interface.');

        $this->generatorConfig->getConfiguredSchemaParser();
    }

    /**
     * @return void
     */
    public function testGetConfiguredSchemaParserGivenBadClass()
    {
        $this->setConfig(
            [
                'migrations' => [
                    'tableName' => 'propel_migration',
                    'parserClass' => '\\Propel\\Generator\\Reverse\\BadSchemaParser',
                ]
            ]
        );

        $this->expectException(ClassNotFoundException::class);
        $this->expectExceptionMessage('Could not resolve SchemaParser class for `\Propel\Generator\Reverse\BadSchemaParser`. Update `migrations.parserClass` or use a known adapter in default connection.');

        $this->generatorConfig->getConfiguredSchemaParser();
    }

    /**
     * @return void
     */
    public function testGetConfiguredBuilder()
    {
        $actual = $this->generatorConfig->loadConfiguredBuilder(new Table('foo'), BuilderType::QueryBase);

        $this->assertInstanceOf(QueryBuilder::class, $actual);
    }

    /**
     * @return void
     */
    public function testGetConfiguredPluralizer()
    {
        $actual = $this->generatorConfig->getConfiguredPluralizer();
        $this->assertInstanceOf(StandardEnglishPluralizer::class, $actual);

        $config['generator']['objectModel']['pluralizerClass'] = SimpleEnglishPluralizer::class;
        $this->setConfig($config);

        $actual = $this->generatorConfig->getConfiguredPluralizer();
        $this->assertInstanceOf(SimpleEnglishPluralizer::class, $actual);
    }

    /**
     * @return void
     */
    public function testGetConfiguredPluralizerNonExistentClassThrowsException()
    {
        $config['generator']['objectModel']['pluralizerClass'] = '\\Propel\\Common\\Pluralizer\\WrongEnglishPluralizer';
        $this->setConfig($config);

        $this->expectException(ClassNotFoundException::class);
        $this->expectExceptionMessage('Class \Propel\Common\Pluralizer\WrongEnglishPluralizer not found.');
        $actual = $this->generatorConfig->getConfiguredPluralizer();
    }

    /**
     * @return void
     */
    public function testGetConfiguredPluralizerWrongClassThrowsException()
    {
        $config['generator']['objectModel']['pluralizerClass'] = PropelConfiguration::class;
        $this->setConfig($config);

        $this->expectException(BuildException::class);
        $this->expectExceptionMessage('Specified class (Propel\Common\Config\PropelConfiguration) does not implement');
        $this->generatorConfig->getConfiguredPluralizer();
    }

    /**
     * @return void
     */
    public function testGetBuildConnections()
    {
        $expected = [
            'mysource' => [
                'adapter' => 'sqlite',
                'classname' => 'Propel\\Runtime\\Connection\\DebugPDO',
                'dsn' => 'sqlite:' . sys_get_temp_dir() . '/mydb',
                'user' => 'root',
                'password' => '',
                'model_paths' => [
                    'src',
                    'vendor',
                ],
            ],
            'yoursource' => [
                'adapter' => 'mysql',
                'classname' => 'Propel\\Runtime\\Connection\\DebugPDO',
                'dsn' => 'mysql:host=localhost;dbname=yourdb',
                'user' => 'root',
                'password' => '',
                'model_paths' => [
                    'src',
                    'vendor',
                ],
            ],
        ];

        $actual = $this->generatorConfig->getBuildConnections();

        $this->assertEquals($expected, $actual);
    }

    /**
     * @return void
     */
    public function testGetBuildConnection()
    {
        $expected = [
            'adapter' => 'sqlite',
            'classname' => 'Propel\\Runtime\\Connection\\DebugPDO',
            'dsn' => 'sqlite:' . sys_get_temp_dir() . '/mydb',
            'user' => 'root',
            'password' => '',
            'model_paths' => [
                'src',
                'vendor',
            ],
        ];

        $actual = $this->generatorConfig->getBuildConnection();

        $this->assertEquals($expected, $actual);
    }

    /**
     * @return void
     */
    public function testGetBuildConnectionGivenDatabase()
    {
        $expected = [
            'adapter' => 'mysql',
            'classname' => 'Propel\\Runtime\\Connection\\DebugPDO',
            'dsn' => 'mysql:host=localhost;dbname=yourdb',
            'user' => 'root',
            'password' => '',
            'model_paths' => [
                'src',
                'vendor',
            ],
        ];

        $actual = $this->generatorConfig->getBuildConnection('yoursource');

        $this->assertEquals($expected, $actual);
    }

    /**
     * @return void
     */
    public function testGetBuildConnectionGivenWrongDatabaseThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Database connection `wrongsource` is not a registered connection.

Update configuration or choose one of [`mysource`, `yoursource`]');

        $this->generatorConfig->getBuildConnection('wrongsource');
    }

    /**
     * @return void
     */
    public function testGetConnectionDefault()
    {
        $actual = $this->generatorConfig->getConnection();

        $this->assertInstanceOf(ConnectionWrapper::class, $actual);
    }

    /**
     * @return void
     */
    public function testGetConnection()
    {
        $actual = $this->generatorConfig->getConnection('mysource');

        $this->assertInstanceOf(ConnectionWrapper::class, $actual);
    }

    /**
     * @return void
     */
    public function testGetConnectionWrongDatabaseThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Database connection `badsource` is not a registered connection.

Update configuration or choose one of [`mysource`, `yoursource`]');

        $actual = $this->generatorConfig->getConnection('badsource');
    }

    /**
     * @return void
     */
    public function testGetBehaviorLocator()
    {
        $actual = $this->generatorConfig->getBehaviorLocator();

        $this->assertInstanceOf(BehaviorLocator::class, $actual);
    }

    /**
     * @return void
     */
    public function testGetConfigPropertyThrowsException(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->generatorConfig->getConfigProperty('foo.bar', true);
    }
}
