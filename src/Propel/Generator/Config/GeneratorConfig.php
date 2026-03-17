<?php

declare(strict_types = 1);

namespace Propel\Generator\Config;

use Propel\Common\Config\Exception\InvalidConfigurationException;
use Propel\Common\Pluralizer\PluralizerInterface;
use Propel\Generator\Exception\ClassNotFoundException;
use Propel\Generator\Exception\InvalidArgumentException;
use Propel\Generator\Platform\PlatformInterface;
use Propel\Generator\Reverse\SchemaParserInterface;
use Propel\Runtime\Adapter\AdapterFactory;
use Propel\Runtime\Connection\ConnectionFactory;
use Propel\Runtime\Connection\ConnectionInterface;
use function array_find;
use function array_key_exists;
use function array_keys;
use function class_exists;
use function implode;
use function in_array;
use function is_array;
use function strtolower;
use function ucfirst;
use function var_export;

/**
 * A class that holds build properties and provide a class loading mechanism for
 * the generator.
 */
class GeneratorConfig extends AbstractGeneratorConfig
{
    protected const PLURALIZER = PluralizerInterface::class;

    /**
     * Connections configured in the `generator` section of the configuration file
     *
     * @var array<array{classname: class-string<\Propel\Runtime\Connection\ConnectionInterface>, adapter: string, dsn: string, user: string, password: string, options?: array}>|null
     */
    protected array|null $buildConnections = null;

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getConfiguredPlatform(?ConnectionInterface $con = null, ?string $databaseName = null): ?PlatformInterface
    {
        $platformClass = $this->resolveClassByNameOrVendor('generator.platformClass', $databaseName, '\\Propel\\Generator\\Platform\\', 'Platform');

        /** @var \Propel\Generator\Platform\DefaultPlatform $platform */
        $platform = $this->createInstance($platformClass);
        $platform->setConnection($con);
        $platform->setGeneratorConfig($this);

        return $platform;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getConfiguredSchemaParser(?ConnectionInterface $con = null, $databaseName = null): ?SchemaParserInterface
    {
        $parserClass = $this->resolveClassByNameOrVendor('migrations.parserClass', $databaseName, '\\Propel\\Generator\\Reverse\\', 'SchemaParser');

        /** @var \Propel\Generator\Reverse\AbstractSchemaParser $parser */
        $parser = $this->createInstance($parserClass, null, SchemaParserInterface::class);
        $parser->setConnection($con);
        $parser->setMigrationTable($this->getConfigPropertyString('migrations.tableName', true));
        $parser->setGeneratorConfig($this);

        return $parser;
    }

    /**
     * @param string $configKey
     * @param string $databaseName
     * @param string $baseNamespace
     * @param string $classId
     *
     * @throws \Propel\Generator\Exception\ClassNotFoundException
     *
     * @return string
     */
    protected function resolveClassByNameOrVendor(string $configKey, string|null $databaseName, string $baseNamespace, string $classId): string
    {
        $classFragment = $this->getConfigPropertyString($configKey) ?? $this->getBuildConnection($databaseName)['adapter'];
        $classOptions = [
            $classFragment,
            $baseNamespace . $classFragment,
            $baseNamespace . ucfirst($classFragment),
            $baseNamespace . ucfirst(strtolower($classFragment)) . $classId,
        ];
        $className = array_find($classOptions, fn (string $classNameFragment) => class_exists($classNameFragment));
        if ($className) {
            return $className;
        }
        $connectionName = $databaseName ? "'$databaseName'" : 'default';

        throw new ClassNotFoundException("Could not resolve $classId class for `$classFragment`. Update `$configKey` or use a known adapter in $connectionName connection.");
    }

    /**
     * Returns a configured Pluralizer class.
     *
     * @return \Propel\Common\Pluralizer\PluralizerInterface
     */
    #[\Override]
    public function getConfiguredPluralizer(): PluralizerInterface
    {
        $classname = $this->getConfigPropertyString('generator.objectModel.pluralizerClass', true);

        /** @var \Propel\Common\Pluralizer\PluralizerInterface $pluralizer */
        $pluralizer = $this->createInstance($classname, null, static::PLURALIZER);

        return $pluralizer;
    }

    /**
     * Return an array of all configured connection properties, from `generator` and `reverse`
     * sections of the configuration.
     *
     * @throws \Propel\Common\Config\Exception\InvalidConfigurationException
     *
     * @return array<array{classname: class-string<\Propel\Runtime\Connection\ConnectionInterface>, adapter: string, dsn: string, user: string, password: string, options?: array}>
     */
    public function getBuildConnections(): array
    {
        if ($this->buildConnections !== null) {
            return $this->buildConnections;
        }

        $this->buildConnections = [];
        $connectionNames = $this->getConfigPropertyRequired('generator.connections');
        if (!is_array($connectionNames)) {
            throw new InvalidConfigurationException('Configuration item `generator.connections` is expected to be an array, but is ' . var_export($connectionNames, true));
        }

        $reverseConnectionName = $this->getConfigProperty('reverse.connection');
        if ($reverseConnectionName && !in_array($reverseConnectionName, $connectionNames, true)) {
            $connectionNames[] = $reverseConnectionName;
        }

        foreach ($connectionNames as $name) {
            /** @var array{classname: class-string<\Propel\Runtime\Connection\ConnectionInterface>, adapter: string, dsn: string, user: string, password: string, options?: array} $definition */
            $definition = $this->getConfigPropertyRequired("database.connections.$name");
            $this->buildConnections[$name] = $definition;
        }

        return $this->buildConnections;
    }

    /**
     * Return the connection properties array, of a given database name.
     * If the database name is null, it returns the default connection properties
     *
     * @param string|null $databaseName
     *
     * @throws \Propel\Generator\Exception\InvalidArgumentException if wrong database name
     *
     * @return array{classname: class-string<\Propel\Runtime\Connection\ConnectionInterface>, adapter: string, dsn: string, user: string, password: string, options?: array}
     */
    public function getBuildConnection(?string $databaseName = null): array
    {
        if ($databaseName === null) {
            $databaseName = $this->getConfigPropertyString('generator.defaultConnection', true);
        }

        $connections = $this->getBuildConnections();
        if (array_key_exists($databaseName, $connections)) {
            return $connections[$databaseName];
        }

        $availableConnections = array_keys($connections);
        $connectionsCsv = implode('`, `', $availableConnections);
        $message = "Database connection `$databaseName` is not a registered connection.\n\nUpdate configuration or choose one of [`$connectionsCsv`]";

        throw new InvalidArgumentException($message);
    }

    /**
     * Return a connection object of a given database name
     *
     * @param string|null $database
     *
     * @return \Propel\Runtime\Connection\ConnectionInterface
     */
    public function getConnection(?string $database = null): ConnectionInterface
    {
        $connectionData = $this->getBuildConnection($database);
        $configuration = [
            'dsn' => $connectionData['dsn'],
            'user' => !empty($connectionData['user']) ? $connectionData['user'] : null,
            'password' => !empty($connectionData['password']) ? $connectionData['password'] : null,
            'options' => isset($connectionData['options']) ? (array)$connectionData['options'] : null,
        ];
        $adapter = AdapterFactory::create($connectionData['adapter']);

        return ConnectionFactory::create($configuration, $adapter);
    }
}
