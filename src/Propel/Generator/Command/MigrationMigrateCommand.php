<?php

declare(strict_types = 1);

namespace Propel\Generator\Command;

use Exception;
use Propel\Generator\Command\Executor\RollbackExecutor;
use Propel\Generator\Manager\MigrationManager;
use Propel\Generator\Util\SqlParser;
use Propel\Runtime\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function array_key_last;
use function array_pop;
use function count;
use function property_exists;

class MigrationMigrateCommand extends AbstractMigrationCommand
{
    /**
     * @var string
     */
    protected const COMMAND_OPTION_MIGRATE_TO_VERSION = 'migrate-to-version';

    /**
     * @var string
     */
    protected const COMMAND_OPTION_MIGRATE_TO_VERSION_DESCRIPTION = 'Defines the version to migrate database to.';

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
            ->addOption('fake', null, InputOption::VALUE_NONE, 'Does not touch the actual schema, but marks all migration as executed.')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Continues with the migration even when errors occur.')
            ->addOption(static::COMMAND_OPTION_MIGRATE_TO_VERSION, null, InputOption::VALUE_REQUIRED, static::COMMAND_OPTION_MIGRATE_TO_VERSION_DESCRIPTION)
            ->setName('migration:migrate')
            ->setAliases(['migrate'])
            ->setDescription('Execute all pending migrations');
    }

    /**
     * @inheritDoc
     *
     * @throws \Propel\Runtime\Exception\RuntimeException
     */
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->setUp($input);
        $manager = $this->getMigrationManager();

        $customConnectionData = $input->getOption('connection');
        $this->setUpMigrationManagerAccess($customConnectionData);

        $version = $input->getOption(static::COMMAND_OPTION_MIGRATE_TO_VERSION);
        if ($version && $manager->isDatabaseVersionApplied($version)) {
            return $this->executeRollbackToVersion($input, $output, $manager, $version);
        }

        if (!$manager->getFirstUpMigrationTimestamp()) {
            $output->writeln('All migrations were already executed - nothing to migrate.');

            return static::CODE_SUCCESS;
        }

        $timestamps = $manager->getNonExecutedMigrationTimestampsByVersion($version);
        $numberOfMigrations = count($timestamps);
        if ($numberOfMigrations > 1) {
            $output->writeln("$numberOfMigrations migrations to execute");
        }

        $isDryRun = $input->getOption('fake');
        $isForce = $input->getOption('force');
        $isVerbose = $input->getOption('verbose');

        foreach ($timestamps as $timestamp) {
            $nextMigrationFileName = $manager->resolveMigrationNameByTimestamp($timestamp);
            $action = $isDryRun ? 'Faking' : 'Executing';
            $output->writeln("$action migration $nextMigrationFileName up");

            if (!$isDryRun) {
                $migration = $manager->instantiateMigration($timestamp);
                if (property_exists($migration, 'comment') && $migration->comment) {
                    $output->writeln("<info>{$migration->comment}</info>");
                }

                if ($migration->preUp($manager) === false) {
                    if ($isForce) {
                        $output->writeln('<error>preUp() returned false. Continue migration.</error>');
                    } else {
                        $output->writeln('<error>preUp() returned false. Aborting migration.</error>');

                        return static::CODE_ERROR;
                    }
                }

                foreach ($migration->getUpSQL() as $datasource => $sql) {
                    $connection = $manager->getConnection($datasource);
                    if ($isVerbose) {
                        $output->writeln("Connecting to database `$datasource` using DSN `{$connection['dsn']}`");
                    }

                    $conn = $manager->getAdapterConnection($datasource);
                    $executedStatementsCount = 0;
                    $statements = SqlParser::parseString($sql);

                    foreach ($statements as $statement) {
                        try {
                            if ($isVerbose) {
                                $output->writeln("Executing statement `$statement`");
                            }
                            $conn->exec($statement);
                            $executedStatementsCount++;
                        } catch (Exception $e) {
                            if ($isForce) {
                                //print error and continue
                                $output->writeln("'<error>Failed to execute SQL `$statement`. Continue migration.</error>");
                            } else {
                                throw new RuntimeException("<error>Failed to execute SQL `$statement`. Aborting migration.</error>", 0, $e);
                            }
                        }
                    }
                    $totalStatements = count($statements);
                    $output->writeln("$executedStatementsCount of $totalStatements SQL statements executed successfully on datasource `$datasource`");
                }
            }

            foreach ($manager->getConnections() as $datasource => $connection) {
                $manager->updateLatestMigrationTimestamp($datasource, $timestamp);
                if ($isVerbose) {
                    $output->writeln("Updated latest migration date to {$timestamp} for datasource `$datasource`");
                }
            }

            if (!$isDryRun) {
                $migration->postUp($manager);
            }
        }

        $output->writeln('Migration complete. No further migration to execute.');

        return static::CODE_SUCCESS;
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param \Propel\Generator\Manager\MigrationManager $migrationManager
     * @param int $version
     *
     * @return int
     */
    protected function executeRollbackToVersion(
        InputInterface $input,
        OutputInterface $output,
        MigrationManager $migrationManager,
        int $version
    ): int {
        $alreadyExecutedMigrations = $migrationManager->getAlreadyExecutedMigrationTimestampsByVersion($version);
        if ($alreadyExecutedMigrations === []) {
            $output->writeln("Already at version {$version}.");

            return static::CODE_SUCCESS;
        }

        $rollbackExecutor = new RollbackExecutor($input, $output, $migrationManager);

        while ($alreadyExecutedMigrations !== []) {
            $currentVersion = array_pop($alreadyExecutedMigrations);
            $previousVersion = count($alreadyExecutedMigrations) ? $alreadyExecutedMigrations[array_key_last($alreadyExecutedMigrations)] : null;

            if (!$rollbackExecutor->executeRollbackToPreviousVersion($currentVersion, $previousVersion)) {
                return static::CODE_ERROR;
            }
        }

        $output->writeln("Successfully rollback to migration version {$version}.");

        return static::CODE_SUCCESS;
    }
}
