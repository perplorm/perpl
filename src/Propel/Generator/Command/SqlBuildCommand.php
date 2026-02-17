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
use function array_merge;

/**
 * @author William Durand <william.durand1@gmail.com>
 */
class SqlBuildCommand extends AbstractCommand
{
    /**
     * @inheritDoc
     */
    #[\Override]
    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption('mysql-engine', null, InputOption::VALUE_REQUIRED, 'MySQL engine (MyISAM, InnoDB, ...)')
            ->addOption('schema-dir', null, InputOption::VALUE_REQUIRED, 'The directory where the schema files are placed')
            ->addOption('output-dir', null, InputOption::VALUE_REQUIRED, 'The output directory')
            ->addOption('validate', null, InputOption::VALUE_NONE, '')
            ->addOption('overwrite', null, InputOption::VALUE_NONE, '')
            ->addOption('connection', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Connection to use', [])
            ->addOption('schema-name', null, InputOption::VALUE_REQUIRED, 'The schema name for RDBMS supporting them', '')
            //->addOption('encoding',     null, InputOption::VALUE_REQUIRED,  'The encoding to use for the database')
            ->addOption('table-prefix', null, InputOption::VALUE_REQUIRED, 'Add a prefix to all the table names in the database')
            ->addOption('composer-dir', null, InputOption::VALUE_REQUIRED, 'Directory in which your composer.json resides', null)
            ->setName('sql:build')
            ->setAliases(['build-sql'])
            ->setDescription('Create SQL script with DDL statements from schema.xml in --output-dir (or paths.schemaDir in config)');
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $generatorConfig = $this->buildGeneratorConfig([], $input, [
            'schema-dir' => 'paths.schemaDir',
            'output-dir' => 'paths.sqlDir',
            'schema-name' => 'generator.schema.basename',
            'table-prefix' => 'generator.tablePrefix',
            'mysql-engine' => 'database.adapters.mysql.tableType',
            'composer-dir' => 'paths.composerDir',
        ]);

        $sqlDir = $generatorConfig->getConfigPropertyString('paths.sqlDir', true);
        $this->createDirectory($sqlDir);

        $manager = new SqlManager();

        $connections = [];
        $optionConnections = $input->getOption('connection');
        if (!$optionConnections) {
            $connections = $generatorConfig->getBuildConnections();
        } else {
            foreach ($optionConnections as $connection) {
                [$name, $dsn, $infos] = $this->parseConnection($connection);
                $connections[$name] = array_merge(['dsn' => $dsn], $infos);
            }
        }
        $manager->setOverwriteSqlMap($input->getOption('overwrite'));
        $manager->setConnections($connections);

        $manager->setValidate($input->getOption('validate'));
        $manager->setGeneratorConfig($generatorConfig);
        $manager->setSchemas($this->getSchemasFromConfig($generatorConfig));
        $manager->setLoggerClosure(function ($message) use ($input, $output): void {
            if ($input->getOption('verbose')) {
                $output->writeln($message);
            }
        });
        $manager->setWorkingDirectory($sqlDir);

        if (!$manager->isOverwriteSqlMap() && $manager->existSqlMap()) {
            $output->writeln("<info>sqldb.map won't be saved because it already exists. Remove it to generate a new map. Use --overwrite to force a overwrite.</info>");
        }

        $manager->buildSql();

        return static::CODE_SUCCESS;
    }
}
