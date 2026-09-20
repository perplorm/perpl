<?php

declare(strict_types = 1);

namespace Propel\Generator\Command;

use Propel\Common\Config\ConfigurationManager;
use Propel\Generator\Exception\RuntimeException;
use Propel\Generator\Model\Database;
use Propel\Generator\Model\Diff\DatabaseComparator;
use Propel\Generator\Model\IdMethod;
use Propel\Generator\Model\Schema;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function array_keys;
use function array_map;
use function array_merge;
use function count;
use function escapeshellarg;
use function file_put_contents;
use function implode;
use function reset;
use function shell_exec;
use function time;
use const DIRECTORY_SEPARATOR;

class MigrationDiffCommand extends AbstractMigrationCommand
{
    /**
     * @inheritDoc
     */
    #[\Override]
    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption('schema-dir', null, InputOption::VALUE_REQUIRED, 'The directory where the schema files are placed')
            ->addOption('output-dir', null, InputOption::VALUE_REQUIRED, 'The output directory where the migration files are located')
            ->addOption('print', 'p', InputOption::VALUE_OPTIONAL, 'Output current migration code without creating file.')
            ->addOption('migration-table', null, InputOption::VALUE_REQUIRED, 'Migration table name', null)
            ->addOption('connection', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Connection to use. Example: \'bookstore=mysql:host=127.0.0.1;dbname=test;user=root;password=foobar\' where "bookstore" is your propel database name (used in your schema.xml)', [])
            ->addOption('table-renaming', null, InputOption::VALUE_NONE, 'Detect table renaming', null)
            ->addOption('editor', null, InputOption::VALUE_OPTIONAL, 'The text editor to use to open diff files', null)
            ->addOption('skip-removed-table', null, InputOption::VALUE_NONE, 'Option to skip removed table from the migration')
            ->addOption('skip-tables', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, 'List of excluded tables', [])
            ->addOption('disable-identifier-quoting', null, InputOption::VALUE_NONE, 'Disable identifier quoting in SQL queries for reversed database tables.')
            ->addOption('comment', 'm', InputOption::VALUE_OPTIONAL, 'A comment for the migration', '')
            ->addOption('suffix', null, InputOption::VALUE_OPTIONAL, 'A suffix for the migration class', '')
            ->addOption('override', 'o', InputOption::VALUE_OPTIONAL, 'Replace content of single pending migration file if available', false)

            ->setName('migration:diff')
            ->setAliases(['diff'])
            ->setDescription('Generate diff classes');
    }

    /**
     * @inheritDoc
     *
     * @throws \Propel\Generator\Exception\RuntimeException
     */
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->setUp($input);
        $this->registerMigrationManagerSchemas();
        $manager = $this->getMigrationManager();

        $customConnectionData = $input->getOption('connection');
        $this->setUpMigrationManagerAccess($customConnectionData);

        $isPrint = $input->getOption('print');
        $isVerbose = $input->getOption('verbose');
        $isOverride = $input->getOption('override');

        $openMigrationTimestamps = $manager->findUncommittedMigrationFileTimestamps();
        $reuseTimestamp = null;
        if ($openMigrationTimestamps && !$isPrint) {
            if (!$isOverride) {
                $timestampsList = implode("\n", $openMigrationTimestamps);
                $msg = "Found pending migrations - execute or delete them, or run with the `--override` flag to replace existing migration file code.\n$timestampsList";

                throw new RuntimeException($msg);
            }
            if (count($openMigrationTimestamps) > 1) {
                throw new RuntimeException('Override aborted: More than one pending migration.');
            }
            $reuseTimestamp = reset($openMigrationTimestamps);
            $output->writeln("Overriding migration file with timestamp $reuseTimestamp");
        }

        $currentSchema = $this->loadCurrentSchema($input, $output, $isVerbose);

        $output->writeln('Comparing models...');

        [$migrationsUp, $migrationsDown] = $this->generateMigrationCode($input, $output, $currentSchema, $isVerbose);

        if (!$migrationsUp) {
            $output->writeln('Same XML and database structures for all datasource - no diff to generate');

            return static::CODE_SUCCESS;
        }

        if ($isPrint) {
            $forceMultipleDbOutput = count($currentSchema->getDatabases()) > 1;
            $output->writeln("\nSQL to migrate DB to schema.xml:" . $this->migrationsToString($migrationsUp, $forceMultipleDbOutput));

            return static::CODE_SUCCESS;
        }

        $fileName = $this->writeFile($input, $migrationsUp, $migrationsDown, $reuseTimestamp);
        $output->writeln("Created migration file `$fileName`");

        $editorCmd = $input->getOption('editor');
        if ($editorCmd) {
            $output->writeln("Using ¸$editorCmd` as text editor");
            shell_exec($editorCmd . ' ' . escapeshellarg($fileName));
        } else {
            $output->writeln('Please review the generated SQL statements, and add data migration code if necessary.');
            $output->writeln('Once the migration class is valid, call the "migrate" task to execute it.');
        }

        return static::CODE_SUCCESS;
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param array $migrationsUp
     * @param array $migrationsDown
     * @param int|null $timestamp
     *
     * @return string
     */
    protected function writeFile(InputInterface $input, array $migrationsUp, array $migrationsDown, int|null $timestamp): string
    {
        $manager = $this->getMigrationManager();
        $suffix = $input->getOption('suffix');
        $comment = $input->getOption('comment');
        $timestamp ??= time();
        $migrationFileName = $manager->getMigrationFileName($timestamp, $suffix);
        $migrationClassBody = $manager->getMigrationClassBody($migrationsUp, $migrationsDown, $timestamp, $comment, $suffix);

        $fileName = $this->migrationDir . DIRECTORY_SEPARATOR . $migrationFileName;
        file_put_contents($fileName, $migrationClassBody);

        return $fileName;
    }

    /**
     * @param array<string, string> $migrations
     * @param bool $forceMultipleDbOutput
     *
     * @return string
     */
    protected function migrationsToString(array $migrations, bool $forceMultipleDbOutput): string
    {
        return count($migrations) === 1 && !$forceMultipleDbOutput
            ? reset($migrations)
            : implode("\n\n", array_map(fn ($dbName, $code) => " - Database $dbName:\n$code", array_keys($migrations), $migrations));
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param bool $isVerbose
     *
     * @return \Propel\Generator\Model\Schema
     */
    protected function loadCurrentSchema(InputInterface $input, OutputInterface $output, bool $isVerbose): Schema
    {
        $manager = $this->getMigrationManager();
        $generatorConfig = $this->getGeneratorConfig();

        $totalNbTables = 0;
        $reversedSchema = new Schema();

        $connections = $manager->getConnections();
        foreach ($manager->getDatabases() as $appDatabase) {
            $name = $appDatabase->getName();
            $params = $connections[$name] ?? [];
            if (!$params) {
                $output->writeln("<info>No connection configured for database `$name`</info>");
            }

            if ($isVerbose) {
                $output->writeln("Connecting to database `$name` using DSN `{$params['dsn']}`");
            }

            $conn = $manager->getAdapterConnection($name);
            $platform = $generatorConfig->getConfiguredPlatform($conn, $name);

            $appDatabase->setPlatform($platform);

            if ($platform && !$platform->supportsMigrations()) {
                $vendor = $platform->getDatabaseType();
                $output->writeln("Skipping database `$name` since vendor `$vendor` does not support migrations");

                continue;
            }

            $additionalTables = [];
            foreach ($appDatabase->getTables() as $table) {
                if ($table->getSchema() && $table->getSchema() != $appDatabase->getSchema()) {
                    $additionalTables[] = $table;
                }
            }

            if ($input->getOption('disable-identifier-quoting')) {
                $platform->setIdentifierQuoting(false);
            }

            $database = new Database($name);
            $database->setPlatform($platform);
            $database->setSchema($appDatabase->getSchema());
            $database->setDefaultIdMethod(IdMethod::NATIVE);

            $parser = $generatorConfig->getConfiguredSchemaParser($conn, $name);
            $nbTables = $parser->parse($database, $additionalTables);

            $reversedSchema->addDatabase($database);
            $totalNbTables += $nbTables;

            if ($isVerbose) {
                $output->writeln("$nbTables tables found in database `$name`", OutputInterface::VERBOSITY_VERBOSE);
            }
        }

        if ($totalNbTables) {
            $output->writeln("$totalNbTables tables found in all databases.");
        } else {
            $output->writeln('No table found in all databases');
        }

        return $reversedSchema;
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param \Propel\Generator\Model\Schema $currentSchema
     * @param bool $isVerbose
     *
     * @return array{array<string>, array<string>}
     */
    protected function generateMigrationCode(InputInterface $input, OutputInterface $output, Schema $currentSchema, bool $isVerbose): array
    {
        $manager = $this->getMigrationManager();
        $generatorConfig = $this->getGeneratorConfig();
        $tableRenaming = $input->getOption('table-renaming');

        $migrationsUp = [];
        $migrationsDown = [];
        $removeTable = !$input->getOption('skip-removed-table');
        $excludedTables = $input->getOption('skip-tables');
        $configManager = new ConfigurationManager($input->getOption('config-dir'));
        $excludedTables = array_merge((array)$excludedTables, (array)$configManager->getConfigProperty('exclude_tables'));

        foreach ($currentSchema->getDatabases() as $currentDatabase) {
            $name = $currentDatabase->getName();

            if ($isVerbose) {
                $output->writeln("Comparing database \"$name\"");
            }

            $schemaDatabase = $manager->getDatabase($name);
            if (!$schemaDatabase) {
                $output->writeln("<error>Database \"$name\" does not exist in schema.xml. Skipped.</error>");

                continue;
            }

            $databaseDiff = DatabaseComparator::computeDiff($currentDatabase, $schemaDatabase, false, $tableRenaming, $removeTable, $excludedTables);

            if (!$databaseDiff) {
                if ($isVerbose) {
                    $output->writeln("Same XML and database structures for datasource \"$name\" - no diff to generate");
                }

                continue;
            }

            $output->writeln("Structure of database was modified in datasource \"$name\": " . $databaseDiff->getDescription());

            foreach ($databaseDiff->getPossibleRenamedTables() as $fromTableName => $toTableName) {
                $output->writeln("<info>Possible table renaming detected: \"$fromTableName\" to \"$toTableName\". It will be deleted and recreated. Use --table-renaming to only rename it.</info>");
            }

            $conn = $manager->getAdapterConnection($name);
            /** @var \Propel\Generator\Platform\DefaultPlatform $platform */
            $platform = $generatorConfig->getConfiguredPlatform($conn, $name);
            if ($input->getOption('disable-identifier-quoting')) {
                $platform->setIdentifierQuoting(false);
            }
            $migrationsUp[$name] = $platform->buildModifyDatabaseDdl($databaseDiff);
            $migrationsDown[$name] = $platform->buildModifyDatabaseDdl($databaseDiff->getReverseDiff());
        }

        return [$migrationsUp, $migrationsDown];
    }
}
