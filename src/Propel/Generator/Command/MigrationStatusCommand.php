<?php

declare(strict_types = 1);

namespace Propel\Generator\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function count;
use function date;
use function in_array;

class MigrationStatusCommand extends AbstractMigrationCommand
{
    /**
     * @var string
     */
    protected const COMMAND_OPTION_LAST_VERSION = 'last-version';

    /**
     * @var string
     */
    protected const COMMAND_OPTION_LAST_VERSION_DESCRIPTION = 'Use this option to receive the version of the last executed migration.';

    /**
     * @inheritDoc
     */
    #[\Override]
    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption('output-dir', null, InputOption::VALUE_REQUIRED, 'The output directory')
            ->addOption('migration-table', null, InputOption::VALUE_REQUIRED, 'Migration table name')
            ->addOption('connection', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Connection to use', [])
            ->addOption(static::COMMAND_OPTION_LAST_VERSION, null, InputOption::VALUE_NONE, static::COMMAND_OPTION_LAST_VERSION_DESCRIPTION)
            ->setName('migration:status')
            ->setAliases(['status'])
            ->setDescription('Get migration status');
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->setUp($input);
        $manager = $this->getMigrationManager();

        $customConnectionData = $input->getOption('connection');
        $this->setUpMigrationManagerAccess($customConnectionData);

        $lastMigrationTimestamp = $manager->getOldestDatabaseVersion();
        if ($input->getOption(static::COMMAND_OPTION_LAST_VERSION)) {
            $output->writeln((string)$lastMigrationTimestamp);

            return static::CODE_SUCCESS;
        }

        $isVerbose = $input->getOption('verbose');

        $output->writeln('Checking Database Versions...');
        foreach ($manager->getConnections() as $datasource => $params) {
            if ($isVerbose) {
                $output->writeln("Connecting to database `$datasource` using DSN `{$params['dsn']}`");
            }

            if (!$manager->migrationTableExists($datasource)) {
                if ($isVerbose) {
                    $output->writeln("Migration table does not exist in datasource `$datasource`; creating it.");
                }
                $manager->createMigrationTable($datasource);
            } else {
                $manager->modifyMigrationTableIfOutdated($datasource);
            }
        }

        if ($isVerbose) {
            if (!$lastMigrationTimestamp) {
                $output->writeln('No migration was ever executed on these connection settings.');
            } else {
                $lastMigrationDate = date('Y-m-d H:i:s', $lastMigrationTimestamp);
                $output->writeln("Latest migration was executed on $lastMigrationDate (timestamp $lastMigrationTimestamp)");
            }
        }

        $migrationFileTimestamps = $manager->readMigrationFileTimestamps();
        $nbExistingMigrations = count($migrationFileTimestamps);

        if (!$migrationFileTimestamps) {
            $output->writeln("No migration file found in '{$this->migrationDir}'");

            return static::CODE_ERROR;
        }

        $output->writeln("$nbExistingMigrations valid migration classes found in migrations directory `$this->migrationDir`");

        $openMigrationTimestamps = $manager->findUncommittedMigrationFileTimestamps();
        $nbNotYetExecutedMigrations = count($openMigrationTimestamps);
        if ($nbNotYetExecutedMigrations) {
            $msg = $nbNotYetExecutedMigrations === 1
                ? '1 migration needs to be executed:'
                : "$nbNotYetExecutedMigrations migrations need to be executed:";

            $output->writeln($msg);
        }

        foreach ($migrationFileTimestamps as $timestamp) {
            if ($timestamp <= $lastMigrationTimestamp && !$isVerbose) {
                continue;
            }

            $oldestMigrationMarker = $timestamp === $lastMigrationTimestamp ? '>' : ' ';
            $migrationFileName = $manager->resolveMigrationNameByTimestamp($timestamp);
            $executedLabel = in_array($timestamp, $openMigrationTimestamps) ? '' : ' (executed)';
            $output->writeln(" $oldestMigrationMarker {$migrationFileName}{$executedLabel}");
        }

        if (!$nbNotYetExecutedMigrations) {
            $output->writeln('All migration files were already executed - Nothing to migrate.');

            return static::CODE_ERROR;
        }

        $pronoun = $nbNotYetExecutedMigrations == 1 ? 'it' : 'them';
        $output->writeln("Call the `migrate` task to execute $pronoun");

        return static::CODE_SUCCESS;
    }
}
