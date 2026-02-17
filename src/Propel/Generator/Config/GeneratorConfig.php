<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Propel\Generator\Config;

use Propel\Common\Config\ConfigurationManager;
use Propel\Common\Config\Exception\InvalidConfigurationException;
use Propel\Common\Pluralizer\PluralizerInterface;
use Propel\Generator\Builder\Om\AbstractOMBuilder;
use Propel\Generator\Exception\BuildException;
use Propel\Generator\Exception\ClassNotFoundException;
use Propel\Generator\Exception\InvalidArgumentException;
use Propel\Generator\Model\Table;
use Propel\Generator\Platform\PlatformInterface;
use Propel\Generator\Reverse\SchemaParserInterface;
use Propel\Generator\Util\BehaviorLocator;
use Propel\Runtime\Adapter\AdapterFactory;
use Propel\Runtime\Connection\ConnectionFactory;
use Propel\Runtime\Connection\ConnectionInterface;
use function array_key_exists;
use function array_keys;
use function class_exists;
use function implode;
use function in_array;
use function interface_exists;
use function is_array;
use function is_int;
use function is_string;
use function sprintf;
use function strtolower;
use function ucfirst;
use function var_export;

/**
 * A class that holds build properties and provide a class loading mechanism for
 * the generator.
 *
 * @author Hans Lellelid <hans@xmpl.org>
 * @author Cristiano Cinotti
 */
class GeneratorConfig extends ConfigurationManager implements GeneratorConfigInterface
{
    protected const PLURALIZER = PluralizerInterface::class;

    /**
     * Config key for name of object class (base class).
     *
     * @var string
     */
    public const KEY_OBJECT_BASE = 'object';

    /**
     * Config key for name of object stub class (model class).
     *
     * @var string
     */
    public const KEY_OBJECT_STUB = 'objectstub';

    /**
     * Config key for name of query class (base class).
     *
     * @var string
     */
    public const KEY_QUERY_BASE = 'query';

    /**
     * Config key for name of query stub.
     *
     * @var string
     */
    public const KEY_QUERY_STUB = 'querystub';

    /**
     * Config key for name of table mapclass.
     *
     * @var string
     */
    public const KEY_TABLEMAP = 'tablemap';

    /**
     * @var \Propel\Generator\Util\BehaviorLocator
     */
    protected $behaviorLocator;

    /**
     * Config key for name of query class (base class).
     *
     * @var string
     */
    public const KEY_COLLECTION = 'collection';

    /**
     * Connections configured in the `generator` section of the configuration file
     *
     * @var array<array<string, string>>
     */
    protected $buildConnections;

    /**
     * @inheritDoc
     *
     * @throws \Propel\Generator\Exception\ClassNotFoundException
     */
    #[\Override]
    public function getConfiguredPlatform(?ConnectionInterface $con = null, ?string $database = null): ?PlatformInterface
    {
        $platform = $this->getConfigPropertyString('generator.platformClass');

        if ($platform === null) {
            if ($database) {
                $platform = $this->getBuildConnection($database)['adapter'];
            }

            if (!$platform) {
                $platform = '\\Propel\\Generator\\Platform\\MysqlPlatform';
            }
        }

        $classes = [
            $platform,
            '\\Propel\\Generator\\Platform\\' . $platform,
            '\\Propel\\Generator\\Platform\\' . ucfirst($platform),
            '\\Propel\\Generator\\Platform\\' . ucfirst(strtolower($platform)) . 'Platform',
        ];

        $platformClass = null;

        foreach ($classes as $class) {
            if (class_exists($class)) {
                $platformClass = $class;

                break;
            }
        }

        if ($platformClass === null) {
            throw new ClassNotFoundException(sprintf('Platform class for `%s` not found.', $platform));
        }

        /** @var \Propel\Generator\Platform\DefaultPlatform $platform */
        $platform = $this->getInstance($platformClass);
        $platform->setConnection($con);
        $platform->setGeneratorConfig($this);

        return $platform;
    }

    /**
     * @inheritDoc
     *
     * @throws \Propel\Generator\Exception\ClassNotFoundException
     * @throws \Propel\Common\Config\Exception\InvalidConfigurationException
     */
    #[\Override]
    public function getConfiguredSchemaParser(?ConnectionInterface $con = null, $database = null): ?SchemaParserInterface
    {
        $reverseParserClass = $this->getConfigPropertyString('migrations.parserClass');

        if ($reverseParserClass === null) {
            if ($database) {
                $reverseParserClass = $this->getBuildConnection($database)['adapter'];
            } else {
                $connections = $this->getBuildConnections();
                $defaultConnection = $this->getConfigProperty('generator.defaultConnection', true);
                if (!is_string($defaultConnection) && !is_int($defaultConnection)) {
                    throw new InvalidConfigurationException('Configuration key `generator.defaultConnection` must be string or int.');
                }
                $adapterName = isset($connections[$defaultConnection])
                    ? ucfirst($connections[$defaultConnection]['adapter'])
                    : 'Mysql';
                $reverseParserClass = "\\Propel\\Generator\\Reverse\\{$adapterName}SchemaParser";
            }
        }

        $classes = [
            $reverseParserClass,
            '\\Propel\\Generator\\Reverse\\' . $reverseParserClass,
            '\\Propel\\Generator\\Reverse\\' . ucfirst($reverseParserClass),
            '\\Propel\\Generator\\Reverse\\' . ucfirst(strtolower($reverseParserClass)) . 'SchemaParser',
        ];

        $reverseClass = null;

        foreach ($classes as $class) {
            if (class_exists($class)) {
                $reverseClass = $class;

                break;
            }
        }

        if ($reverseClass === null) {
            throw new ClassNotFoundException(sprintf('Reverse SchemaParser class for `%s` not found.', $reverseParserClass));
        }

        /** @var \Propel\Generator\Reverse\AbstractSchemaParser $parser */
        $parser = $this->getInstance($reverseClass, null, '\\Propel\\Generator\\Reverse\\SchemaParserInterface');
        $parser->setConnection($con);
        $parser->setMigrationTable($this->getConfigPropertyString('migrations.tableName', true));
        $parser->setGeneratorConfig($this);

        return $parser;
    }

    /**
     * Returns a configured data model builder class for specified table and
     * based on type ('object', 'query', 'tableMap' etc.).
     *
     * @param \Propel\Generator\Model\Table $table
     * @param string $type
     *
     * @throws \Propel\Generator\Exception\InvalidArgumentException
     *
     * @return \Propel\Generator\Builder\Om\AbstractOMBuilder
     */
    #[\Override]
    public function getConfiguredBuilder(Table $table, string $type): AbstractOMBuilder
    {
        $configProperty = 'generator.objectModel.builders.' . $type;
        $classname = $this->getConfigPropertyString($configProperty);
        if ($classname === null) {
            throw new InvalidArgumentException(sprintf('Unable to get config property: "%s"', $configProperty));
        }

        /** @var \Propel\Generator\Builder\Om\AbstractOMBuilder $builder */
        $builder = $this->getInstance($classname, $table);
        $builder->setGeneratorConfig($this);

        return $builder;
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
        $pluralizer = $this->getInstance($classname, null, static::PLURALIZER);

        return $pluralizer;
    }

    /**
     * Return an array of all configured connection properties, from `generator` and `reverse`
     * sections of the configuration.
     *
     * @throws \Propel\Common\Config\Exception\InvalidConfigurationException
     *
     * @return array<array<string, string>>
     */
    public function getBuildConnections(): array
    {
        if ($this->buildConnections !== null) {
            return $this->buildConnections;
        }

        $this->buildConnections = [];
        $connectionNames = $this->getConfigProperty('generator.connections', true);
        if (!is_array($connectionNames)) {
            throw new InvalidConfigurationException('Configuration item `generator.connections` is expected to be an array, but is ' . var_export($connectionNames, true));
        }
        $reverseConnection = $this->getConfigProperty('reverse.connection');

        if ($reverseConnection !== null && !in_array($reverseConnection, $connectionNames, true)) {
            $connectionNames[] = $reverseConnection;
        }

        foreach ($connectionNames as $name) {
            /** @var array<string, string>|null $definition */
            $definition = $this->getConfigProperty("database.connections.$name", true);

            if ($definition) {
                $this->buildConnections[$name] = $definition;
            }
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
     * @return array
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
        $message = "Database connection `$databaseName` is not a registered connection.\n\n"
        . 'Update configuration or choose one of [`' . implode('`, `', $availableConnections) . '`]';

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
        $buildConnection = $this->getBuildConnection($database);

        //Still useful ?
        //$dsn = str_replace("@DB@", $database, $buildConnection['dsn']);
        $dsn = $buildConnection['dsn'];

        // Set user + password to null if they are empty strings or missing
        $username = isset($buildConnection['user']) && $buildConnection['user'] ? $buildConnection['user'] : null;
        $password = isset($buildConnection['password']) && $buildConnection['password'] ? $buildConnection['password'] : null;

        // Get options from and config and default to null
        $options = isset($buildConnection['options']) && is_array($buildConnection['options']) ? $buildConnection['options'] : null;

        $con = ConnectionFactory::create(['dsn' => $dsn, 'user' => $username, 'password' => $password, 'options' => $options], AdapterFactory::create($buildConnection['adapter']));

        return $con;
    }

    /**
     * @return \Propel\Generator\Util\BehaviorLocator
     */
    #[\Override]
    public function getBehaviorLocator(): BehaviorLocator
    {
        if ($this->behaviorLocator === null) {
            $this->behaviorLocator = new BehaviorLocator($this);
        }

        return $this->behaviorLocator;
    }

    /**
     * Return an instance of $className
     *
     * @param string $className The name of the class to return an instance
     * @param mixed|null $arguments
     * @param string|null $interfaceName The name of the interface to be implemented by the returned class
     *
     * @throws \Propel\Generator\Exception\ClassNotFoundException if the class doesn't exists
     * @throws \Propel\Generator\Exception\InvalidArgumentException if the interface doesn't exists
     * @throws \Propel\Generator\Exception\BuildException if the class isn't an implementation of the given interface
     *
     * @return object
     */
    private function getInstance(string $className, $arguments = null, ?string $interfaceName = null): object
    {
        if (!class_exists($className)) {
            throw new ClassNotFoundException("Class $className not found.");
        }

        $object = new $className($arguments);

        if ($interfaceName !== null) {
            if (!interface_exists($interfaceName)) {
                throw new InvalidArgumentException("Interface $interfaceName does not exists.");
            }

            if (!$object instanceof $interfaceName) {
                throw new BuildException("Specified class ($className) does not implement $interfaceName interface.");
            }
        }

        return $object;
    }
}
