<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Builder\Om;

use Propel\Generator\Builder\Om\ObjectBuilder;
use Propel\Generator\Builder\Om\ObjectBuilder\ColumnTypes\ColumnCodeProducer;
use Propel\Generator\Config\GeneratorConfig;
use Propel\Generator\Util\QuickBuilder;
use Propel\Tests\TestCase;

/**
 */
class ColumnCodeProducerTest extends TestCase
{
    public function columnAttributeConfigurationDataProvider(): array
    {
        return [
            ['should produce typed column attributes by default', 'protected int|null $id = null;', []],
            ['should produce untyped column attributes if configured', 'protected int|null $id = null;', ['propel' => ['generator' => ['objectModel' => ['typeColumnDataFields' => true]]]]],
        ];
    }

    /**
     * @dataProvider columnAttributeConfigurationDataProvider
     * @return void
     */
    public function testAddDefaultColumnAttributeConfiguration(string $description, $expectedDeclaration, array $additionalGeneratorConfig): void
    {
        $schema = <<<EOT
<database>
    <table name="le_table">
        <column name="id" type="INTEGER"/>
    </table>
</database>
EOT;
        $codeProducer = $this->createColumnCodeProducer($schema, 'le_table', 'id', $additionalGeneratorConfig);
        $script = '';
        $codeProducer->addDefaultColumnAttribute($script);

        $this->assertStringContainsString($expectedDeclaration, $script, $description);
    }


    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return \Propel\Generator\Builder\Om\ObjectBuilder\ColumnTypes\ColumnCodeProducer
     */
    protected function createColumnCodeProducer(string $schema, string $tableName, string $columnName, ?array $additionalGeneratorConfig = null): ColumnCodeProducer
    {
        $database = QuickBuilder::parseSchema($schema);
        $table = $database->getTable($tableName);
        $objectBuilder = new ObjectBuilder($table);
        $configArray = $additionalGeneratorConfig ? array_merge_recursive(static::generatorConfig, $additionalGeneratorConfig) : static::generatorConfig;
        $generatorConfig = new GeneratorConfig(null, $configArray);
        $objectBuilder->setGeneratorConfig($generatorConfig);
        /** @var array<ColumnCodeProducer> */
        $columnCodeProducers = $this->getObjectPropertyValue($objectBuilder, 'columnCodeProducers');
        $codeProducer = array_find($columnCodeProducers, fn($p) => $this->getObjectPropertyValue($p, 'column')->getName() === $columnName);

        return $codeProducer;
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
