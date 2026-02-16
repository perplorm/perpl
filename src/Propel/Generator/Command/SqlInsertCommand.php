<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Propel\Generator\Command;

use Propel\Generator\Manager\SqlManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author William Durand <william.durand1@gmail.com>
 */
class SqlInsertCommand extends AbstractCommand
{
    /**
     * @inheritDoc
     */
    #[\Override]
    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption('sql-dir', null, InputOption::VALUE_REQUIRED, 'The SQL files directory')
            ->addOption('connection', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Connection to use. Example: \'bookstore=mysql:host=127.0.0.1;dbname=test;user=root;password=foobar\' where "bookstore" is your propel database name (used in your schema.xml)')
            ->setName('sql:insert')
            ->setAliases(['insert-sql'])
            ->setDescription('Run SQL scripts in directory --sql-dir (or paths.sqlDir in config), typically used to (re-)initialize database by running SQL scripts from sql:build.');
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $generatorConfig = $this->buildGeneratorConfig([], $input, [
            'sql-dir' => 'paths.sqlDir',
        ]);

        $connections = $this->buildConnectionFromInput($input) ?? $generatorConfig->getBuildConnections();

        $manager = new SqlManager();
        $manager->setConnections($connections);
        $manager->setLoggerClosure(function ($message) use ($input, $output): void {
            if ($input->getOption('verbose')) {
                $output->writeln($message);
            }
        });
        $sqlDir = $generatorConfig->getConfigPropertyString('paths.sqlDir', true);
        $manager->setWorkingDirectory($sqlDir);

        $manager->insertSql();

        return static::CODE_SUCCESS;
    }
}
