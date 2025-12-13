<?php

declare(strict_types = 1);

namespace Propel\Generator\Manager;

use Exception;
use PDO;
use PDOException;
use Propel\Common\Util\PathTrait;
use Propel\Generator\Builder\Util\PropelTemplate;
use Propel\Generator\Exception\InvalidArgumentException;
use Propel\Generator\Model\Column;
use Propel\Generator\Model\Database;
use Propel\Generator\Model\Table;
use Propel\Generator\Platform\PlatformInterface;
use Propel\Generator\Util\SqlParser;
use Propel\Runtime\Adapter\AdapterFactory;
use Propel\Runtime\Connection\ConnectionFactory;
use Propel\Runtime\Connection\ConnectionInterface;
use RuntimeException;
use function addcslashes;
use function array_diff;
use function array_flip;
use function array_intersect;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_pop;
use function array_search;
use function array_shift;
use function array_slice;
use function date;
use function function_exists;
use function in_array;
use function is_dir;
use function posix_getpwuid;
use function posix_getuid;
use function preg_match;
use function preg_replace;
use function scandir;
use function sort;
use function strlen;
use function ucfirst;
use function usort;

/**
 * Service class for preparing and executing migrations
 */
class MigrationManager extends AbstractManager
{
    use PathTrait;

    /**
     * @var string
     */
    protected const COL_VERSION = 'version';

    /**
     * @var string
     */
    protected const COL_EXECUTION_DATETIME = 'execution_datetime';

    /**
     * @var string
     */
    protected const EXECUTION_DATETIME_FORMAT = 'Y-m-d H:i:s';

    /**
     * @var array
     */
    protected $connections = [];

    /**
     * @var array<\Propel\Runtime\Connection\ConnectionInterface>
     */
    protected $adapterConnections = [];

    /**
     * @var string
     */
    protected $migrationTable;

    /**
     * Set the database connection settings
     *
     * @param array $connections
     *
     * @return void
     */
    public function setConnections(array $connections): void
    {
        $this->connections = $connections;
    }

    /**
     * Get the database connection settings
     *
     * @return array
     */
    public function getConnections(): array
    {
        return $this->connections;
    }

    /**
     * @param string $datasource
     *
     * @throws \Propel\Generator\Exception\InvalidArgumentException
     *
     * @return array
     */
    public function getConnection(string $datasource): array
    {
        if (!isset($this->connections[$datasource])) {
            throw new InvalidArgumentException("Unknown datasource `$datasource`");
        }

        return $this->connections[$datasource];
    }

    /**
     * @param string $datasource
     *
     * @return \Propel\Runtime\Connection\ConnectionInterface
     */
    public function getAdapterConnection(string $datasource): ConnectionInterface
    {
        if (!isset($this->adapterConnections[$datasource])) {
            $buildConnection = $this->getConnection($datasource);
            $conn = ConnectionFactory::create($buildConnection, AdapterFactory::create($buildConnection['adapter']));
            $this->adapterConnections[$datasource] = $conn;
        }

        return $this->adapterConnections[$datasource];
    }

    /**
     * @param string $datasource
     *
     * @return \Propel\Generator\Platform\PlatformInterface
     */
    public function getPlatform(string $datasource): PlatformInterface
    {
        $connection = $this->getConnection($datasource);
        $adapter = ucfirst($connection['adapter']);
        $class = '\\Propel\\Generator\\Platform\\' . $adapter . 'Platform';

        /** @var \Propel\Generator\Platform\PlatformInterface $platform */
        $platform = new $class();

        return $platform;
    }

    /**
     * Set the migration table name
     *
     * @param string $migrationTable
     *
     * @return void
     */
    public function setMigrationTable(string $migrationTable): void
    {
        $this->migrationTable = $migrationTable;
    }

    /**
     * get the migration table name
     *
     * @return string
     */
    public function getMigrationTable(): string
    {
        return $this->migrationTable;
    }

    /**
     * @deprecated Use aptly named {@see static::loadExecutedMigrationTimestamps()}
     *
     * @return list<int>
     */
    public function getAllDatabaseVersions(): array
    {
        return $this->loadExecutedMigrationTimestamps();
    }

    /**
     * @throws \Exception
     *
     * @return list<int>
     */
    protected function loadExecutedMigrationTimestamps(): array
    {
        $connections = $this->getConnections();
        if (!$connections) {
            throw new Exception('You must define database connection settings in a buildtime-conf.xml file to use migrations');
        }

        $migrationData = [];
        foreach ($connections as $name => $params) {
            try {
                $migrationData += $this->getMigrationData($name);
            } catch (PDOException $e) {
                $this->createMigrationTable($name);
                $migrationData = [];
            }
        }

        usort($migrationData, fn (array $a, array $b): int => $a[static::COL_EXECUTION_DATETIME] <=> $b[static::COL_EXECUTION_DATETIME]
            ?: $a[static::COL_VERSION] <=> $b[static::COL_VERSION]);

        return array_map(fn (array $migration) => (int)$migration[static::COL_VERSION], $migrationData);
    }

    /**
     * @param string $datasource
     *
     * @return bool
     */
    public function migrationTableExists(string $datasource): bool
    {
        $migrationTable = $this->getMigrationTable();
        $sql = "SELECT version FROM $migrationTable";

        return $this->statementSucceeds($datasource, $sql);
    }

    /**
     * @param string $datasource
     *
     * @throws \Exception
     *
     * @return void
     */
    public function createMigrationTable(string $datasource): void
    {
        /** @var \Propel\Generator\Platform\DefaultPlatform $platform */
        $platform = $this->getPlatform($datasource);
        // modelize the table
        $database = new Database($datasource);
        $database->setPlatform($platform);

        $table = new Table($this->getMigrationTable());
        $database->addTable($table);

        $table->addColumn($this->createVersionColumn($platform));
        $table->addColumn($this->createExecutionDatetimeColumn($platform));

        // insert the table into the database
        $statements = $platform->getAddTableDDL($table);
        $conn = $this->getAdapterConnection($datasource);
        $res = SqlParser::executeString($statements, $conn);

        if (!$res) {
            throw new Exception("Unable to create migration table in datasource `$datasource`");
        }
    }

    /**
     * @param string $datasource
     * @param int $timestamp
     *
     * @return void
     */
    public function removeMigrationTimestamp(string $datasource, int $timestamp): void
    {
        $platform = $this->getPlatform($datasource);
        $conn = $this->getAdapterConnection($datasource);
        $conn->transaction(function () use ($conn, $platform, $timestamp): void {
            $migrationTable = $this->getMigrationTable();
            $versionColumn = $platform->doQuoting('version');

            $sql = "DELETE FROM $migrationTable WHERE $versionColumn = ?";
            $stmt = $conn->prepare($sql);

            if ($stmt === false) {
                throw new RuntimeException('PdoConnection::prepare() failed and did not return statement object for execution.');
            }

            $stmt->bindParam(1, $timestamp, PDO::PARAM_INT);
            $stmt->execute();
        });
    }

    /**
     * @param string $datasource
     * @param int $timestamp
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    public function updateLatestMigrationTimestamp(string $datasource, int $timestamp): void
    {
        $platform = $this->getPlatform($datasource);
        $conn = $this->getAdapterConnection($datasource);

        $this->modifyMigrationTableIfOutdated($datasource);

        $migrationTable = $this->getMigrationTable();
        $versionColumn = $platform->doQuoting(static::COL_VERSION);
        $executionDateColumn = $platform->doQuoting(static::COL_EXECUTION_DATETIME);
        $sql = "INSERT INTO $migrationTable ($versionColumn, $executionDateColumn) VALUES (?, ?)";

        $executionDatetime = date(static::EXECUTION_DATETIME_FORMAT);

        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            throw new RuntimeException('PdoConnection::prepare() failed and did not return statement object for execution.');
        }

        $stmt->bindParam(1, $timestamp, PDO::PARAM_INT);
        $stmt->bindParam(2, $executionDatetime);
        $stmt->execute();
    }

    /**
     * @deprecated Use aptly named {@see static::readMigrationFileTimestamps()}
     *
     * @return array<int>
     */
    public function getMigrationTimestamps(): array
    {
        return $this->readMigrationFileTimestamps();
    }

    /**
     * @return array<int>
     */
    public function readMigrationFileTimestamps(): array
    {
        $path = $this->getWorkingDirectory();
        if (!$path || !is_dir($path)) {
            return [];
        }
        $migrationTimestamps = [];
        $files = scandir($path) ?: [];

        foreach ($files as $file) {
            if (preg_match('/^PropelMigration_(\d+).*\.php$/', $file, $matches)) {
                $migrationTimestamps[] = (int)$matches[1];
            }
        }

        return $migrationTimestamps;
    }

    /**
     * @deprecated Use aptly named {@see static::findUncommittedMigrationFileTimestamps()}
     *
     * @return array<int>
     */
    public function getValidMigrationTimestamps(): array
    {
        return $this->findUncommittedMigrationFileTimestamps();
    }

    /**
     * @return array<int>
     */
    public function findUncommittedMigrationFileTimestamps(): array
    {
        $fileTimestamps = $this->readMigrationFileTimestamps();
        $dbTimestamps = $this->loadExecutedMigrationTimestamps();
        $migrationTimestamps = array_diff($fileTimestamps, $dbTimestamps);
        sort($migrationTimestamps);

        return $migrationTimestamps;
    }

    /**
     * - Gets non executed migrations.
     * - If `version` is provided, filters out values after the given version in the result.
     *
     * @param int|null $version
     *
     * @return array<int>
     */
    public function getNonExecutedMigrationTimestampsByVersion(?int $version = null): array
    {
        $openMigrationTimestamps = $this->findUncommittedMigrationFileTimestamps();

        if ($version === null) {
            return $openMigrationTimestamps;
        }

        $versionIndex = array_search($version, $openMigrationTimestamps, true);

        return $versionIndex === false
            ? $openMigrationTimestamps
            : array_slice($openMigrationTimestamps, 0, (int)$versionIndex + 1);
    }

    /**
     * @return bool
     */
    public function hasPendingMigrations(): bool
    {
        return $this->findUncommittedMigrationFileTimestamps() !== [];
    }

    /**
     * @return list<int>
     */
    public function getAlreadyExecutedMigrationTimestamps(): array
    {
        $fileTimestamps = $this->readMigrationFileTimestamps();
        $dbTimestamps = $this->loadExecutedMigrationTimestamps();
        $migrationTimestamps = array_intersect($fileTimestamps, $dbTimestamps);

        $sortOrder = array_flip($dbTimestamps);
        usort($migrationTimestamps, fn (int $a, int $b): int => $sortOrder[$a] <=> $sortOrder[$b]);

        return $migrationTimestamps;
    }

    /**
     * - Gets already executed migration timestamps.
     * - If `version` is provided, filters out values before the given version in the result.
     *
     * @param int|null $version
     *
     * @return list<int>
     */
    public function getAlreadyExecutedMigrationTimestampsByVersion(?int $version = null): array
    {
        $migrationTimestamps = $this->getAlreadyExecutedMigrationTimestamps();

        if ($version === null) {
            return $migrationTimestamps;
        }

        $versionIndex = array_search($version, $migrationTimestamps, true);
        if ($versionIndex === false) {
            return $migrationTimestamps;
        }

        return array_slice($migrationTimestamps, $versionIndex + 1);
    }

    /**
     * @return int|null
     */
    public function getFirstUpMigrationTimestamp(): ?int
    {
        $validTimestamps = $this->findUncommittedMigrationFileTimestamps();

        return array_shift($validTimestamps);
    }

    /**
     * @return int|null
     */
    public function getFirstDownMigrationTimestamp(): ?int
    {
        return $this->getOldestDatabaseVersion();
    }

    /**
     * @param int $timestamp
     * @param string $suffix
     *
     * @return string
     */
    public function resolveMigrationNameByTimestamp(int $timestamp, string $suffix = ''): string
    {
        $className = "PropelMigration_$timestamp";
        $suffix = $suffix ?: $this->findExistingMigrationFileNameSuffix($timestamp);

        return $suffix ? "{$className}_{$suffix}" : $className;
    }

    /**
     * @param int $timestamp
     *
     * @return string
     */
    private function findExistingMigrationFileNameSuffix(int $timestamp): string
    {
        $path = $this->getWorkingDirectory();
        if (!$path || !is_dir($path)) {
            return '';
        }

        $suffix = '';
        $files = scandir($path) ?: [];
        foreach ($files as $file) {
            if (preg_match('/^PropelMigration_' . $timestamp . '(_)?(.*)\.php$/', $file, $matches)) {
                $suffix = (string)$matches[2];
            }
        }

        return $suffix;
    }

    /**
     * @param int $timestamp
     *
     * @return object
     */
    public function instantiateMigration(int $timestamp): object
    {
        $dir = $this->getWorkingDirectory();
        $fileName = $this->resolveMigrationNameByTimestamp($timestamp);

        require_once "$dir/$fileName.php";

        return new $fileName();
    }

    /**
     * @param array<string> $migrationsUp
     * @param array<string> $migrationsDown
     * @param int $timestamp
     * @param string $comment
     * @param string $suffix
     *
     * @return string
     */
    public function getMigrationClassBody(array $migrationsUp, array $migrationsDown, int $timestamp, string $comment = '', string $suffix = ''): string
    {
        $connectionToVariableName = self::buildConnectionToVariableNameMap($migrationsUp, $migrationsDown);
        $templateFileName = $this->getTemplatePath(__DIR__) . 'migration_template.php';

        return PropelTemplate::renderFile($templateFileName, [
            'timestamp' => $timestamp,
            'commentString' => addcslashes($comment, ','),
            'suffix' => $suffix,
            'timeInWords' => date('Y-m-d H:i:s', $timestamp),
            'migrationAuthor' => ($author = $this->getUser()) ? 'by ' . $author : '',
            'migrationClassName' => $this->resolveMigrationNameByTimestamp($timestamp, $suffix),
            'migrationsUp' => $migrationsUp,
            'migrationsDown' => $migrationsDown,
            'connectionToVariableName' => $connectionToVariableName,
        ]);
    }

    /**
     *  * Builds an array mapping connection names to a string that can be used as a php variable name.
     *
     * @param array<string> $migrationsUp
     * @param array<string> $migrationsDown
     *
     * @return array<string, string>
     */
    protected static function buildConnectionToVariableNameMap(array $migrationsUp, array $migrationsDown): array
    {
        $connectionToVariableName = [];
        foreach ([$migrationsUp, $migrationsDown] as $migrations) {
            $connectionNames = array_keys($migrations);
            foreach ($connectionNames as $index => $connectionName) {
                if (array_key_exists($connectionName, $connectionToVariableName)) {
                    continue;
                }
                $alphNums = preg_replace('/\W/', '', (string)$connectionName);
                if (strlen($alphNums) === 0) {
                    $alphNums = $index;
                }
                $variableName = '$connection_' . $alphNums;
                while (in_array($variableName, $connectionToVariableName, true)) {
                    $variableName .= 'I';
                }
                $connectionToVariableName[$connectionName] = $variableName;
            }
        }

        return $connectionToVariableName;
    }

    /**
     * @param int $timestamp
     * @param string $suffix
     *
     * @return string
     */
    public function getMigrationFileName(int $timestamp, string $suffix = ''): string
    {
        return $this->resolveMigrationNameByTimestamp($timestamp, $suffix) . '.php';
    }

    /**
     * @return string
     */
    public static function getUser(): string
    {
        if (function_exists('posix_getuid')) {
            $currentUser = posix_getpwuid(posix_getuid());
            if (isset($currentUser['name'])) {
                return $currentUser['name'];
            }
        }

        return '';
    }

    /**
     * @return int|null
     */
    public function getOldestDatabaseVersion(): ?int
    {
        $versions = $this->loadExecutedMigrationTimestamps();

        return $versions ? array_pop($versions) : null;
    }

    /**
     * @param string $datasource
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    public function modifyMigrationTableIfOutdated(string $datasource): void
    {
        if ($this->columnExists($datasource, static::COL_EXECUTION_DATETIME)) {
            return;
        }

        $table = new Table($this->getMigrationTable());

        /** @phpstan-var \Propel\Generator\Platform\DefaultPlatform $platform */
        $platform = $this->getPlatform($datasource);
        $column = $this->createExecutionDatetimeColumn($platform);
        $column->setTable($table);

        $connection = $this->getAdapterConnection($datasource);
        $sql = $platform->getAddColumnDDL($column);
        $stmt = $connection->prepare($sql);

        if ($stmt === false) {
            throw new RuntimeException('PdoConnection::prepare() failed and did not return statement object for execution.');
        }

        $stmt->execute();
    }

    /**
     * @param int $version
     *
     * @return bool
     */
    public function isDatabaseVersionApplied(int $version): bool
    {
        return in_array($version, $this->getAlreadyExecutedMigrationTimestamps());
    }

    /**
     * @param string $connectionName
     *
     * @throws \RuntimeException
     *
     * @return array
     */
    protected function getMigrationData(string $connectionName): array
    {
        $connection = $this->getAdapterConnection($connectionName);
        $platform = $this->getGeneratorConfig()->getConfiguredPlatform($connection);
        if (!$platform->supportsMigrations()) {
            return [];
        }

        $this->modifyMigrationTableIfOutdated($connectionName);

        $migrationTable = $this->getMigrationTable();
        $versionColumn = $platform->doQuoting(static::COL_VERSION);
        $executionDateColumn = $platform->doQuoting(static::COL_EXECUTION_DATETIME);

        $sql = "SELECT $versionColumn, $executionDateColumn FROM $migrationTable";
        $stmt = $connection->prepare($sql);

        if ($stmt === false) {
            throw new RuntimeException('PdoConnection::prepare() failed and did not return statement object for execution.');
        }

        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * @param \Propel\Generator\Platform\PlatformInterface $platform
     *
     * @return \Propel\Generator\Model\Column
     */
    protected function createVersionColumn(PlatformInterface $platform): Column
    {
        $column = new Column(static::COL_VERSION);
        $column->getDomain()->copy($platform->getDomainForType('INTEGER'));
        $column->setDefaultValue('0');

        return $column;
    }

    /**
     * @param \Propel\Generator\Platform\PlatformInterface $platform
     *
     * @return \Propel\Generator\Model\Column
     */
    protected function createExecutionDatetimeColumn(PlatformInterface $platform): Column
    {
        $column = new Column(static::COL_EXECUTION_DATETIME);
        $column->getDomain()->copy($platform->getDomainForType('DATETIME'));

        return $column;
    }

    /**
     * @param string $datasource
     * @param string $columnName
     *
     * @return bool
     */
    protected function columnExists(string $datasource, string $columnName): bool
    {
        $migrationTable = $this->getMigrationTable();
        $sql = "SELECT $columnName FROM $migrationTable";

        return $this->statementSucceeds($datasource, $sql);
    }

    /**
     * @param string $datasource
     * @param string $sql
     *
     * @return bool
     */
    private function statementSucceeds(string $datasource, string $sql): bool
    {
        $connection = $this->getAdapterConnection($datasource);
        try {
            $connection->query($sql);

            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
}
