<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Propel\Generator\Command;

use Propel\Generator\Behavior\AggregateMultipleColumns\AggregateMultipleColumnsBehavior;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function array_map;
use function chdir;
use function count;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function is_file;
use function realpath;
use function sprintf;
use function str_replace;
use function substr;
use function ucfirst;
use const DIRECTORY_SEPARATOR;

/**
 * @author William Durand <william.durand1@gmail.com>
 */
class TestPrepareCommand extends AbstractCommand
{
    /**
     * @var string
     */
    public const FIXTURES_DIR = 'tests/Fixtures';

    /**
     * @var string
     */
    public const DEFAULT_VENDOR = 'mysql';

    /**
     * @var string
     */
    public const DEFAULT_DSN = 'mysql:host=127.0.0.1;dbname=test';

    /**
     * @var string
     */
    public const DEFAULT_DB_USER = 'root';

    /**
     * @var string
     */
    public const DEFAULT_DB_PASSWD = '';

    /**
     * @var array
     */
    protected $fixtures = [
        //directory - array of connections
        'bookstore' => ['bookstore', 'bookstore-cms', 'bookstore-behavior'],
        'namespaced' => ['bookstore_namespaced'],
        'reverse/mysql' => ['reverse-bookstore'],
        'reverse/pgsql' => ['reverse-bookstore'],
        'schemas' => ['bookstore-schemas'],
        'migration' => ['migration'],
        'quoting' => ['quoting'],
    ];

    /**
     * @var string
     */
    protected $root;

    public function __construct()
    {
        parent::__construct();

        $this->root = (string)realpath(__DIR__ . '/../../../../');
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    protected function configure(): void
    {
        $this
            ->setDefinition([
                new InputOption('vendor', null, InputOption::VALUE_REQUIRED, 'The database vendor', self::DEFAULT_VENDOR),
                new InputOption('dsn', null, InputOption::VALUE_REQUIRED, 'The data source name', self::DEFAULT_DSN),
                new InputOption('user', 'u', InputOption::VALUE_REQUIRED, 'The database user', self::DEFAULT_DB_USER),
                new InputOption('password', 'p', InputOption::VALUE_OPTIONAL, 'The database password', self::DEFAULT_DB_PASSWD),
                new InputOption('exclude-database', null, InputOption::VALUE_NONE, 'Whether this should not touch database\'s schema'),
            ])
            ->setName('test:prepare')
            ->setDescription('Prepare the Propel test suite by building fixtures');
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $exitCode = static::CODE_SUCCESS;
        foreach ($this->fixtures as $fixturesDir => $connections) {
            $this->resetCounters();
            $buildDir = self::FIXTURES_DIR . DIRECTORY_SEPARATOR . $fixturesDir;
            $exitCode |= $this->buildFixtures($buildDir, $connections, $input, $output);
        }
        chdir($this->root);

        return $exitCode;
    }

    /**
     * @param string $fixturesDir
     * @param array<string> $connections
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int Exit code
     */
    protected function buildFixtures(string $fixturesDir, array $connections, InputInterface $input, OutputInterface $output): int
    {
        if (!file_exists($this->root . '/' . $fixturesDir)) {
            $output->writeln(sprintf('<error>Directory "%s" not found.</error>', $fixturesDir));

            return static::CODE_ERROR;
        }

        $vendor = $input->getOption('vendor');
        $dsn = $input->getOption('dsn');
        $user = $input->getOption('user');
        $password = $input->getOption('password');
        $verbose = $input->getOption('verbose');
        $excludeDataBase = (bool)$input->getOption('exclude-database');

        $output->writeln(sprintf('Building fixtures in <info>%-40s</info> %s', $fixturesDir, $excludeDataBase ? '(exclude-database)' : ''));

        chdir($this->root . '/' . $fixturesDir);

        if (!$this->updatePropelYamlDistFile($vendor, $dsn, $user, $password)) {
            $output->writeln('<comment>No "propel.yaml.dist" file found, skipped.</comment>');
        }

        $mainConnection = $connections[0];
        $this->runConfigConvertCommand($output, $mainConnection);

        $hasSchemasInCurrentDir = count($this->findSchemasInDirectory('.')) > 0;
        if (!$hasSchemasInCurrentDir) {
            return static::CODE_SUCCESS;
        }

        $this->runModelBuildCommand($output, $vendor, $verbose);

        if ($excludeDataBase) {
            return static::CODE_SUCCESS;
        }

        $this->runSqlBuildCommand($output, $vendor, $verbose);

        $connectionStrings = $this->buildConnectionString($connections, $dsn, $user, $password);
        $this->runSqlInsert($output, $connectionStrings, $verbose);

        return static::CODE_SUCCESS;
    }

    /**
     * Reset static members in builder classes. Necessary when test run commands repeatedly.
     *
     * @return void
     */
    protected function resetCounters(): void
    {
        AggregateMultipleColumnsBehavior::resetInsertedAggregationNames();
    }

    /**
     * Updates connection information in propel.yaml.dist in current working directory.
     *
     * @param string $vendor
     * @param string $dsn
     * @param string $user
     * @param string $password
     *
     * @return bool
     */
    protected function updatePropelYamlDistFile(string $vendor, string $dsn, string $user, string $password): bool
    {
        if (!is_file('propel.yaml.dist')) {
            return false;
        }

        $content = (string)file_get_contents('propel.yaml.dist');

        $content = str_replace('##DATABASE_VENDOR##', $vendor, $content);
        $content = str_replace('##DATABASE_URL##', $dsn, $content);
        $content = str_replace('##DATABASE_USER##', $user, $content);
        $content = str_replace('##DATABASE_PASSWORD##', $password, $content);

        file_put_contents('propel.yaml', $content);

        return true;
    }

    /**
     * Convert local propel.yaml file via config:convert command.
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param string $connection
     *
     * @return void
     */
    protected function runConfigConvertCommand(OutputInterface $output, string $connection): void
    {
        if (!is_file('propel.yaml')) {
            return;
        }
        $in = new ArrayInput([
            'command' => 'config:convert',
            '--output-dir' => './build/conf',
            '--output-file' => sprintf('%s-conf.php', $connection),
            '--loader-script-dir' => './build/conf',
        ]);

        $command = $this->getApplication()->find('config:convert');
        $command->run($in, $output);
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param string $vendor
     * @param string|bool $verbose
     *
     * @return void
     */
    protected function runModelBuildCommand(OutputInterface $output, string $vendor, string|bool $verbose): void
    {
        $in = new ArrayInput([
            'command' => 'model:build',
            '--schema-dir' => '.',
            '--output-dir' => 'build/classes/',
            '--loader-script-dir' => './build/conf',
            '--platform' => ucfirst($vendor) . 'Platform',
            '--verbose' => $verbose,
        ]);

        $command = $this->getApplication()->find('model:build');
        $command->run($in, $output);
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param string $vendor
     * @param string|bool $verbose
     *
     * @return void
     */
    protected function runSqlBuildCommand(OutputInterface $output, string $vendor, string|bool $verbose): void
    {
        $in = new ArrayInput([
            'command' => 'sql:build',
            '--schema-dir' => '.',
            '--output-dir' => 'build/sql/',
            '--platform' => ucfirst($vendor) . 'Platform',
            '--verbose' => $verbose,
        ]);

        $command = $this->getApplication()->find('sql:build');
        $command->run($in, $output);
    }

    /**
     * @param array<string> $connections
     * @param string $dsn
     * @param string $user
     * @param string $password
     *
     * @return array<string>
     */
    protected function buildConnectionString(array $connections, string $dsn, string|null $user, string|null $password): array
    {
        $isSqlite = substr($dsn, 0, 6) === 'sqlite';

        return array_map(fn ($con) => $isSqlite ? "$con=$dsn" : "$con=$dsn;user=$user;password=$password", $connections);
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param array<string> $connectionStrings
     * @param string|bool $verbose
     *
     * @return void
     */
    protected function runSqlInsert(OutputInterface $output, array $connectionStrings, string|bool $verbose): void
    {
        $in = new ArrayInput([
            'command' => 'sql:insert',
            '--sql-dir' => 'build/sql/',
            '--connection' => $connectionStrings,
            '--verbose' => $verbose,
        ]);

        $command = $this->getApplication()->find('sql:insert');
        $command->run($in, $output);
    }
}
