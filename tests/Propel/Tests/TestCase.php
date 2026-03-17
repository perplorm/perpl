<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Propel\Generator\Builder\Util\SchemaReader;
use Propel\Generator\Config\QuickGeneratorConfig;
use Propel\Generator\Model\Database;
use Propel\Generator\Platform\PlatformInterface;
use Propel\Generator\Platform\SqlitePlatform;
use ReflectionClass;
use ReflectionProperty;
use function is_object;
use function sprintf;

class TestCase extends PHPUnitTestCase
{
    /**
     * @return string
     */
    protected function getDriver()
    {
        return 'sqlite';
    }

    /**
     * Makes the sql compatible with the current database.
     * Means: replaces ` etc.
     *
     * @param string $sql
     * @param string $source
     * @param string|null $target
     *
     * @return mixed
     */
    protected function getSql($sql, $source = 'mysql', $target = null)
    {
        if (!$target) {
            $target = $this->getDriver();
        }

        if ($target === 'sqlite' && $source === 'mysql') {
            return preg_replace('/`([^`]*)`/', '[$1]', $sql);
        }
        if ($target === 'pgsql' && $source === 'mysql') {
            return preg_replace('/`([^`]*)`/', '"$1"', $sql);
        }
        if ($target !== 'mysql' && $source === 'mysql') {
            return str_replace('`', '', $sql);
        }

        return $sql;
    }

    /**
     * Returns true if the current driver in the connection ($this->con) is $db.
     *
     * @param string $db
     *
     * @return bool
     */
    protected function isDb($db = 'mysql')
    {
        return $this->getDriver() == $db;
    }

    /**
     * @return bool
     */
    protected function runningOnPostgreSQL()
    {
        return $this->isDb('pgsql');
    }

    /**
     * @return bool
     */
    protected function runningOnMySQL()
    {
        return $this->isDb('mysql');
    }

    /**
     * @return bool
     */
    protected function runningOnSQLite()
    {
        return $this->isDb('sqlite');
    }

    /**
     * @return bool
     */
    protected function runningOnOracle()
    {
        return $this->isDb('oracle');
    }

    /**
     * @return bool
     */
    protected function runningOnMSSQL()
    {
        return $this->isDb('mssql');
    }

    /**
     * @return \Propel\Generator\Platform\PlatformInterface
     */
    protected function getPlatform(): PlatformInterface
    {
        $className = sprintf('\\Propel\\Generator\\Platform\\%sPlatform', ucfirst($this->getDriver()));

        return new $className();
    }

    /**
     * @param \Propel\Runtime\Connection\ConnectionInterface $con
     *
     * @return \Propel\Generator\Reverse\SchemaParserInterface
     */
    protected function getParser($con)
    {
        $className = sprintf('\\Propel\\Generator\\Reverse\\%sSchemaParser', ucfirst($this->getDriver()));

        return new $className($con);
    }

    /**
     * Call private or protected method.
     *
     * @see https://stackoverflow.com/questions/249664/best-practices-to-test-protected-methods-with-phpunit
     *
     * @param class-string|object $obj Instance with protected or private methods
     * @param string $name Name of the protected or private method
     * @param array $args Argumens for method
     * @param class-string|null $referenceClass Optional parent class owning the property
     *
     * @return mixed Result of method call
     */
    public function callMethod(string|object $obj, string $name, array $args = [], string|null $referenceClass = null)
    {
        $class = new ReflectionClass($referenceClass ?? $obj);
        $method = $class->getMethod($name);
        if (version_compare(PHP_VERSION, '8.1.0', '<')) {
            $method->setAccessible(true); // Use this if you are running PHP older than 8.1.0
        }

        return $method->invokeArgs(is_object($obj) ? $obj : null, $args);
    }

    /**
     * Get private or protected property.
     *
     * @param class-string|object $obj Instance with protected or private property
     * @param string $name Name of the protected or private property
     *
     * @return ReflectionProperty
     */
    protected function getReflectionProperty($obj, string $name): ReflectionProperty
    {
        $reflection = new ReflectionClass($obj);
        $property = $reflection->getProperty($name);
        if (version_compare(PHP_VERSION, '8.1.0', '<')) {
            $property->setAccessible(true);
        }

        return $property;
    }

    /**
     * Get private or protected property value.
     *
     * @param class-string|object $obj Instance with protected or private property
     * @param string $name Name of the protected or private property
     * @param class-string|null $referenceClass Optional parent class owning the property
     *
     * @return mixed
     */
    public function getObjectPropertyValue($obj, string $name, string|null $referenceClass = null)
    {
        $getValueParam = is_object($obj) ? $obj : null;

        return $this->getReflectionProperty($referenceClass ?? $obj, $name)->getValue($getValueParam);
    }

    /**
     * Set private or protected property
     *
     * @param class-string|object $obj Instance with protected or private property
     * @param string $name Name of the protected or private property
     * @param mixed $value New value for property
     * @param class-string|null $referenceClass Optional parent class owning the property
     *
     * @return void
     */
    public function setObjectPropertyValue($obj, string $name, $value, string|null $referenceClass = null): void
    {
        $this->getReflectionProperty($referenceClass ?? $obj, $name)->setValue($obj, $value);
    }

    /**
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        // restore instance pool default
        \Propel\Runtime\Propel::enableInstancePooling();

        parent::setUpBeforeClass();

        //static::printFileNameOnStart();
    }

    /**
     * Seems stupid, but incredible helpful on DB lock errors.
     *
     * @return void
     */
    public static function printFileNameOnStart(): void
    {
        $bt = debug_backtrace();
        foreach ($bt as $call) {
            if (!isset($call['object'])) {
                continue;
            }
            echo "\n doing " . $call['object']->getName() . "\n";
            return;
        }
        var_dump($bt);
    }

    /**
     * @param PlatformInterface $platform
     * @param bool $defaultToNative
     * @param string $schema
     * @return Database|null
     */
    public function buildDatabaseFromSchema(
        string $schema,
        array|null $additionalConfig,
        PlatformInterface|null $platform,
    ): Database {
        $config = new QuickGeneratorConfig($additionalConfig);
        $platform ??= new SqlitePlatform();
        $platform->setGeneratorConfig($config);
        $schemaReader = new SchemaReader($platform);
        $schemaReader->setGeneratorConfig($config);
        $schema = $schemaReader->parseString($schema);

        return $schema->getDatabase(); // does final initialization
    }
}
