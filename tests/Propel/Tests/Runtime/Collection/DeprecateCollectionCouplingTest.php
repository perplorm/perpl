<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Runtime\Collection;

use Propel\Generator\Builder\Om\TableMapBuilder;
use Propel\Generator\Config\GeneratorConfig;
use Propel\Generator\Exception\DeprecatedUsageException;
use Propel\Generator\Util\QuickBuilder;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Propel;
use Propel\Tests\TestCase;

/**
 */
class DeprecateCollectionCouplingTest extends TestCase
{
    /**
     * @return void
     */
    public function testExceptionOnImplicitCoupling()
    {
        $ns = __NAMESPACE__;
$schema = <<< EOF
<database namespace="$ns">
    <table name="extending_test"></table>
</database>
EOF;
        $tableMapBuilder = $this->createTableMapBuilder($schema, 'extending_test');
        $this->expectException(DeprecatedUsageException::class);
        $tableMapBuilder->build();
    }

    /**
     * @return void
     */
    public function testNoExceptionOnNonCollection()
    {
        $ns = __NAMESPACE__;
$schema = <<< EOF
<database namespace="$ns">
    <table name="non_extending_test"></table>
</database>
EOF;
        $tableMapBuilder = $this->createTableMapBuilder($schema, 'non_extending_test');
        try{
            $tableMapBuilder->build();
        } catch (DeprecatedUsageException $e) {
            $this->fail('should detect that class with name collision does not extend Collection');
        }
        $this->assertTrue(true);
    }
    /**
     * @return void
     */
    public function testNoExceptionOnDeclatedClass()
    {
        $ns = __NAMESPACE__;
$schema = <<< EOF
<database namespace="$ns">
    <table
        name="extending_test"
        collection-class="\Propel\Tests\Runtime\Collection\ExtendingTestCollection"
    ></table>
</database>
EOF;
        $tableMapBuilder = $this->createTableMapBuilder($schema, 'extending_test');

        try{
            $tableMapBuilder->build();
        } catch (DeprecatedUsageException $e) {
            $this->fail('registered collection is not an implicit coupling');
        }
        $this->assertTrue(true);
    }


    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return \Propel\Generator\Builder\Om\TableMapBuilder
     */
    protected function createTableMapBuilder(string $schema, string $tableName): TableMapBuilder
    {
        $database = QuickBuilder::parseSchema($schema);
        $table = $database->getTable($tableName);
        $objectBuilder = new TableMapBuilder($table);
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

class ExtendingTestCollection extends ObjectCollection
{
}

class NonExtendingTestCollection
{
}
