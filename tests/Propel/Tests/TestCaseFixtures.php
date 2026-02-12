<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Finder\Finder;

/**
 * This test case class is used when the fixtures are needed. It takes care that
 * those files (model classes and -conf.php files) are created.
 *
 * If you need additional to that also database's tables use TestCaseFixturesDatabase instead.
 */
class TestCaseFixtures extends TestCase
{
    /**
     * File at this location will be used to indicate the version of currently build fixures.
     *
     * @var string
     */
    protected const INDICATOR_FILE_LOCATION = __DIR__ . '/../../Fixtures/fixtures_built';

    /**
     * If setUp() should also initial database's schema.
     *
     * @var bool
     */
    protected static $withDatabaseSchema = false;

    /**
     * Depending on this type we return the correct runninOn* results,
     * also getSql() and getDriver() is based on that.
     *
     * @var \Propel\Runtime\Connection\ConnectionInterface
     */
    protected $con;

    /**
     * DSN during last build
     *
     * @var string
     */
    protected static $lastBuildDsn;

    /**
     * Indicates if fixtures were build with database
     *
     * @var string
     */
    protected static $lastBuildMode;

    /**
     * Combination of $lastBuildDsn and $lastBuildMode, indicating build version of currently active configurations
     *
     * @var string|null
     */
    protected static $activeConfigsVersion;

    /**
     * Setup fixture.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        static::checkInit();
    }

    /**
     * @return void
     */
    protected static function checkInit(): void
    {
        $dsn = static::getFixturesConnectionDsn();
        if ($dsn !== static::getBuiltDsn() || (static::$withDatabaseSchema && static::getLastBuildMode() !== 'fixtures-database')) {
            static::initializeFixtures($dsn);
        }

        static::readAllRuntimeConfigs();
    }

    /**
     * @param string $dsn
     *
     * @return void
     */
    protected static function initializeFixtures(string $dsn): void
    {
        $input = static::buildInputOptions($dsn);
        $output = new BufferedOutput();
        $app = static::buildApp();

        if ($app->run($input, $output) === 0) { // Command::SUCCESS is not available on CI
            static::registerBuildStatus($dsn);
        } else {
            throw new RuntimeException('Cannot initialize fixtures: ' . $output->fetch());
        }
    }

    /**
     * @param string $dsn
     *
     * @return void
     */
    protected static function registerBuildStatus(string $dsn): void
    {
        $mode = (static::$withDatabaseSchema) ? 'fixtures-database' : 'fixtures-only';

        $buildIndicatorFileContent = "$dsn\n$mode\nFixtures has been created. Delete this file to let the test suite regenerate all fixtures.";
        file_put_contents(static::INDICATOR_FILE_LOCATION, $buildIndicatorFileContent);

        static::$lastBuildDsn = $dsn;
        static::$lastBuildMode = $mode;
        static::$activeConfigsVersion = null;
    }

    /**
     * @param string $dsn
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con
     *
     * @return \Symfony\Component\Console\Input\ArrayInput
     */
    protected static function buildInputOptions(string $dsn, ?ConnectionInterface $con = null): ArrayInput
    {
        $options = [
            'command' => 'test:prepare',
            '--vendor' => static::loadDatabaseDriverName(),
            '--dsn' => $dsn,
            '--verbose' => true,
        ];

        if (!static::$withDatabaseSchema) {
            $options['--exclude-database'] = true;
        }
        if (strpos($dsn, 'sqlite:') !== 0) {
            $options['--user'] = getenv('DB_USER') ?: 'root';
        }

        if (getenv('DB_PW') !== false) {
            $options['--password'] = getenv('DB_PW');
        }

        return new ArrayInput($options);
    }

    /**
     * @return \Symfony\Component\Console\Application
     */
    protected static function buildApp(): Application
    {
        $finder = new Finder();
        $finder->files()->name('*.php')->in(__DIR__ . '/../../../src/Propel/Generator/Command')->depth(0);

        $commands = [];
        $namespace = '\\Propel\\Generator\\Command\\';
        foreach ($finder as $file) {
            $r = new ReflectionClass($namespace . $file->getBasename('.php'));
            if (!$r->isSubclassOf(Command::class) || $r->isAbstract()) {
                continue;
            }
            $commands[] = $r->newInstance();
        }

        $app = new Application('Propel', Propel::VERSION);
        $app->addCommands($commands);
        $app->setAutoExit(false);

        return $app;
    }

    /**
     * @return string|null
     */
    protected static function getLastBuildMode()
    {
        if (!static::$lastBuildMode && file_exists(static::INDICATOR_FILE_LOCATION) && ($h = fopen(static::INDICATOR_FILE_LOCATION, 'r'))) {
            fgets($h);
            $secondLine = fgets($h);
            static::$lastBuildMode = trim($secondLine);
        }

        return static::$lastBuildMode;
    }

    /**
     * Reads and includes all *-conf.php of Fixtures/ folder.
     *
     * @return void
     */
    protected static function readAllRuntimeConfigs()
    {
        $currentConfigsVersion = static::$lastBuildMode . ':' . static::$lastBuildDsn;
        if (static::$activeConfigsVersion === $currentConfigsVersion) {
            return;
        }

        $finder = new Finder();
        $finder->files()->name('*-conf.php')->in(__DIR__ . '/../../Fixtures/');

        foreach ($finder as $file) {
            include_once($file->getPathname());
        }

        static::$activeConfigsVersion = $currentConfigsVersion;
    }

    /**
     * Returns the used DNS for building the fixtures.
     *
     * @return string
     */
    protected static function getBuiltDsn()
    {
        if (!static::$lastBuildDsn && file_exists(static::INDICATOR_FILE_LOCATION) && ($h = fopen(static::INDICATOR_FILE_LOCATION, 'r'))) {
            $firstLine = fgets($h);

            static::$lastBuildDsn = trim($firstLine);
        }

        return static::$lastBuildDsn;
    }

    /**
     * Returns the current connection DSN.
     *
     * @param string $database
     * @param bool $withCredentials
     *
     * @return string
     */
    protected function getConnectionDsn($database = 'bookstore', $withCredentials = false)
    {
        $serviceContainer = Propel::getServiceContainer();
        /** @var \Propel\Runtime\Connection\ConnectionManagerSingle $manager */
        $manager = $serviceContainer->getConnectionManager($database);
        $configuration = $manager->getConfiguration();
        $dsn = $configuration['dsn'];

        if (substr($dsn, 0, 6) !== 'sqlite' && $withCredentials) {
            $dsn .= ';user=' . $configuration['user'];
            if (isset($configuration['password']) && $configuration['password']) {
                $dsn .= ';password=' . $configuration['password'];
            }
        }

        return $dsn;
    }

    /**
     * Returns the DSN for building the fixtures.
     * They are provided by environment variables.
     *
     * DB, DB_HOSTNAME
     *
     * @return string
     */
    protected static function getFixturesConnectionDsn()
    {
        $db = strtolower(getenv('DB'));

        if ($db === 'sqlite') {
            $dbFilePath = realpath(getenv('DB_FILE') ?: __DIR__ . '/../../test.sq3');

            if (!file_exists($dbFilePath)) {
                touch($dbFilePath);
            }

            return 'sqlite:' . realpath($dbFilePath);
        }

        if (!$db || $db === 'agnostic') {
            $db = 'mysql';
        }

        $host = getenv('DB_HOSTNAME') ?: '127.0.0.1';
        $dbName = getenv('DB_NAME') ?: 'test';

        return "$db:host=$host;dbname=$dbName";
    }

    /**
     * Returns current database driver.
     *
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con
     *
     * @return string
     */
    protected static function loadDatabaseDriverName(?ConnectionInterface $con = null)
    {
        $db = strtolower(getenv('DB'));

        return !$db || 'agnostic' === $db ?  'mysql' : $db;
    }

    /**
     * Returns current database driver.
     *
     * @return string
     */
    protected function getDriver()
    {
        return static::loadDatabaseDriverName();
    }
}
