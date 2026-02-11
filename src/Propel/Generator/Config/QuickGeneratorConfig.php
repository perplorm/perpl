<?php

declare(strict_types = 1);

namespace Propel\Generator\Config;

use Propel\Common\Pluralizer\PluralizerInterface;
use Propel\Common\Pluralizer\StandardEnglishPluralizer;
use Propel\Generator\Platform\PlatformInterface;
use Propel\Generator\Reverse\SchemaParserInterface;
use Propel\Runtime\Connection\ConnectionInterface;
use function array_replace_recursive;

class QuickGeneratorConfig extends AbstractGeneratorConfig
{
    /**
     * @param array|null $extraConf
     */
    public function __construct(?array $extraConf = [])
    {
        if ($extraConf === null) {
            $extraConf = [];
        }

        //Creates a GeneratorConfig based on Propel default values plus the following
        $configs = [
           'propel' => [
               'database' => [
                   'connections' => [
                       'default' => [
                           'adapter' => 'sqlite',
                           'classname' => 'Propel\Runtime\Connection\DebugPDO',
                           'dsn' => 'sqlite::memory:',
                           'user' => '',
                           'password' => '',
                       ],
                   ],
               ],
               'runtime' => [
                   'defaultConnection' => 'default',
                   'connections' => ['default'],
               ],
               'generator' => [
                   'defaultConnection' => 'default',
                   'connections' => ['default'],
               ],
           ],
        ];

        $configs = array_replace_recursive($configs, $extraConf);
        $this->process($configs);
    }

    /**
     * Returns a configured Pluralizer class.
     *
     * @return \Propel\Common\Pluralizer\PluralizerInterface
     */
    #[\Override]
    public function getConfiguredPluralizer(): PluralizerInterface
    {
        return new StandardEnglishPluralizer();
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getConfiguredPlatform(?ConnectionInterface $con = null, ?string $databaseName = null): ?PlatformInterface
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getConfiguredSchemaParser(?ConnectionInterface $con = null, ?string $databaseName = null): ?SchemaParserInterface
    {
        return null;
    }
}
