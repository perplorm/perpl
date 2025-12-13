<?php

declare(strict_types = 1);

namespace Propel\Generator\Command;

use Exception;
use Propel\Generator\Util\SqlParser;
use Propel\Runtime\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function count;

class MigrationUpCommand extends AbstractMigrationCommand
{
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
            ->addOption('fake', null, InputOption::VALUE_NONE, 'Does not touch the actual schema, but marks next migration as executed.')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Continues with the migration even when errors occur.')
            ->setName('migration:up')
            ->setAliases(['up'])
            ->setDescription('Execute migrations up');
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

        $nextMigrationTimestamp = $manager->getFirstUpMigrationTimestamp();
        if (!$nextMigrationTimestamp) {
            $output->writeln('All migrations were already executed - nothing to migrate.');

            return static::CODE_ERROR;
        }

        $isDryRun = $input->getOption('fake');
        $isForce = $input->getOption('force');
        $isVerbose = $input->getOption('verbose');

        $nextMigrationFileName = $manager->resolveMigrationNameByTimestamp($nextMigrationTimestamp);
        $action = $isDryRun ? 'Faking' : 'Executing';
        $output->writeln("$action migration $nextMigrationFileName up");

        $migration = $manager->instantiateMigration($nextMigrationTimestamp);

        if (!$isDryRun && $migration->preUp($manager) === false) {
            if ($isForce) {
                $output->writeln('<error>preUp() returned false. Continue migration.</error>');
            } else {
                $output->writeln('<error>preUp() returned false. Aborting migration.</error>');

                return static::CODE_ERROR;
            }
        }

        foreach ($migration->getUpSQL() as $dataSource => $sql) {
            $connection = $manager->getConnection($dataSource);

            if ($isVerbose) {
                $output->writeln("Connecting to database `$dataSource` using DSN `{$connection['dsn']}`");
            }

            $conn = $manager->getAdapterConnection($dataSource);
            $executedStatementsCount = 0;
            $statements = SqlParser::parseString($sql);

            if (!$isDryRun) {
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

                $numberOfStatements = count($statements);
                $output->writeln("$executedStatementsCount of $numberOfStatements SQL statements executed successfully on datasource `$dataSource`");
            }

            $manager->updateLatestMigrationTimestamp($dataSource, $nextMigrationTimestamp);

            if ($isVerbose) {
                $output->writeln("Updated latest migration date to $nextMigrationTimestamp for datasource `$dataSource`");
            }
        }

        if (!$isDryRun) {
            $migration->postUp($manager);
        }

        $leftMigrationsCount = count($manager->findUncommittedMigrationFileTimestamps());
        $status = $leftMigrationsCount === 0
            ? 'No further migration to execute.'
            : "$leftMigrationsCount migrations left to execute.";
        $output->writeln("Migration complete. $status");

        return static::CODE_SUCCESS;
    }
}
