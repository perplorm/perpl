<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests;

use ErrorException;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Propel\Generator\Builder\Util\SchemaReader;
use Propel\Generator\Config\QuickGeneratorConfig;
use Propel\Generator\Model\Column;
use Propel\Generator\Model\Database;
use Propel\Generator\Platform\PlatformInterface;
use Propel\Generator\Platform\SqlitePlatform;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Tests\Helpers\Assertion\MatchesFormattedVendorSql;
use ReflectionClass;
use ReflectionProperty;
use function is_object;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;

class TestCase extends PHPUnitTestCase
{
    /**
     * @return string
     */
    protected static function getDriver(): string
    {
        return 'sqlite';
    }

    /**
     * @deprecated Use aptly named {@see static::toVendorSql}
     *
     * Makes the sql compatible with the current database.
     * Means: replaces ` etc.
     *
     * @param string $sql
     * @param string $source
     * @param string|null $target
     *
     * @return mixed
     */
    protected static function getSql($sql, $source = 'mysql', $target = null)
    {
        return static::toVendorSql($sql, $source, $target);
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
    public static function toVendorSql($sql, $source = 'mysql', $target = null)
    {
        $target ??= static::getDriver();

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

    final protected static function assertVendorSql(string $expectedSql, Criteria $query, string $message = '')
    {
        $sql = $query->createSelectSql($params);
        static::assertThat($sql, new MatchesFormattedVendorSql($expectedSql), $message);
    }

    /**
     * Returns true if the current driver in the connection ($this->con) is $db.
     *
     * @param string $db
     *
     * @return bool
     */
    protected static function isDb($db = 'mysql')
    {
        return static::getDriver() == $db;
    }

    /**
     * @return bool
     */
    protected static function runningOnPostgreSQL()
    {
        return static::isDb('pgsql');
    }

    /**
     * @return bool
     */
    protected static function runningOnMySQL()
    {
        return static::isDb('mysql');
    }

    /**
     * @return bool
     */
    protected static function runningOnSQLite()
    {
        return static::isDb('sqlite');
    }

    /**
     * @return bool
     */
    protected static function runningOnOracle()
    {
        return static::isDb('oracle');
    }

    /**
     * @return bool
     */
    protected static function runningOnMSSQL()
    {
        return static::isDb('mssql');
    }

    /**
     * @return \Propel\Generator\Platform\PlatformInterface
     */
    protected static function getPlatform(): PlatformInterface
    {
        $className = sprintf('\\Propel\\Generator\\Platform\\%sPlatform', ucfirst(static::getDriver()));

        return new $className();
    }

    /**
     * @param \Propel\Runtime\Connection\ConnectionInterface $con
     *
     * @return \Propel\Generator\Reverse\SchemaParserInterface
     */
    protected static function getParser($con)
    {
        $className = sprintf('\\Propel\\Generator\\Reverse\\%sSchemaParser', ucfirst(static::getDriver()));

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
     *
     * @return mixed
     */
    public function getObjectPropertyValue($obj, string $name)
    {
        $getValueParam = is_object($obj) ? $obj : null;

        return $this->getReflectionProperty($obj, $name)->getValue($getValueParam);
    }

    /**
     * Assert object property matches value.
     *
     * @param mixed $expected
     * @param class-string|object $obj Instance with protected or private property
     * @param string $name Name of the protected or private property
     * @param string $message
     *
     * @return mixed
     */
    public function assertObjectPropertyValue($expected, $obj, string $propertyName, string $message = ''): void
    {
        $value = $this->getObjectPropertyValue($obj, $propertyName);
        $this->assertEquals($expected, $value, $message);
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
    public static function buildDatabaseFromSchema(
        string $schema,
        array|null $additionalConfig = null,
        PlatformInterface|null $platform = null,
    ): Database {
        $config = new QuickGeneratorConfig($additionalConfig);
        $platform ??= new SqlitePlatform();
        $platform->setGeneratorConfig($config);
        $schemaReader = new SchemaReader($platform);
        $schemaReader->setGeneratorConfig($config);
        $schema = $schemaReader->parseString($schema);

        return $schema->getDatabase(); // does final initialization
    }

    /**
     * @param PlatformInterface $platform
     * @param bool $defaultToNative
     * @param string $schema
     * @return Column|null
     */
    public static function buildColumnFromSchema(
        string $columnXml,
        array|null $additionalConfig = null,
        PlatformInterface|null $platform = null,
    ): Column {
        $schema = "<database><table name='table'>$columnXml</table></database";
        $database = static::buildDatabaseFromSchema($schema, $additionalConfig, $platform);

        return $database->getTable('table')->getColumns()[0];
    }

    /**
     * Expect an error with a specific error level. Catches non-error errors like E_USER_DEPRECATED:
     * <code>$this->expectErrorLevel(E_USER_DEPRECATED, fn() => $childQuery->populateMedia(), $message)</code>
     *
     * @param int $errorLevels
     * @param callable $op
     * @param string|null $message
     *
     * @return void
     */
    public function expectErrorLevel(int $errorLevels, callable $op, string|null $message = null)
    {
        set_error_handler(static function (int $errno, string $errstr) use ($errorLevels) {
            static::assertSame($errorLevels, $errno);
            throw new ErrorException($errstr, $errno);
        }, $errorLevels);

        $this->expectException(ErrorException::class);
        if ($message) {
            $this->expectExceptionMessage($message);
        }
    
        try {
            $op();
        } finally {
            restore_error_handler();
        }
    }
}
