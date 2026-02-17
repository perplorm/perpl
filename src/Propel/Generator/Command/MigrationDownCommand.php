<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Propel\Generator\Command;

use Propel\Generator\Command\Executor\RollbackExecutor;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function array_pop;
use function count;
use function sprintf;

class MigrationDownCommand extends AbstractMigrationCommand
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
            ->addOption('fake', null, InputOption::VALUE_NONE, 'Does not touch the actual schema, but marks previous migration as executed.')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Continues with the migration even when errors occur.')
            ->setName('migration:down')
            ->setAliases(['down'])
            ->setDescription('Execute migrations down');
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

        $alreadyExecutedMigrations = $manager->getAlreadyExecutedMigrationTimestamps();
        if ($alreadyExecutedMigrations === []) {
            $output->writeln('No migrations were ever executed on this database - nothing to reverse.');

            return static::CODE_ERROR;
        }

        $rollbackExecutor = new RollbackExecutor($input, $output, $manager);

        $currentMigrationVersion = array_pop($alreadyExecutedMigrations);

        $leftMigrationsCount = count($alreadyExecutedMigrations);
        $previousMigrationVersion = array_pop($alreadyExecutedMigrations);

        if (!$rollbackExecutor->executeRollbackToPreviousVersion($currentMigrationVersion, $previousMigrationVersion)) {
            return static::CODE_ERROR;
        }

        if ($leftMigrationsCount) {
            $output->writeln(sprintf('Reverse migration complete. %d more migrations available for reverse.', $leftMigrationsCount));
        } else {
            $output->writeln('Reverse migration complete. No more migration available for reverse');
        }

        return static::CODE_SUCCESS;
    }
}
