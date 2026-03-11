<?php

declare(strict_types = 1);

namespace Propel\Generator\Command;

use Propel\Generator\Config\GeneratorConfig;
use Propel\Generator\Exception\RuntimeException;
use Propel\Generator\Manager\MigrationManager;
use Symfony\Component\Console\Input\InputInterface;
use function array_merge;

abstract class AbstractMigrationCommand extends AbstractCommand
{
    private GeneratorConfig|null $generatorConfig = null;

    private MigrationManager|null $migrationManager = null;

    protected string $migrationDir;

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     *
     * @return void
     */
    protected function setUp(InputInterface $input): void
    {
        $this->generatorConfig = $this->setUpGeneratorConfig($input);
        $this->migrationManager = $this->setUpMigrationManager($this->generatorConfig);
        $this->migrationDir = $this->generatorConfig->getConfigPropertyString('paths.migrationDir', true);
        $this->createDirectory($this->migrationDir);
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     *
     * @return \Propel\Generator\Config\GeneratorConfig
     */
    protected function setUpGeneratorConfig(InputInterface $input): GeneratorConfig
    {
        $configOptions = [];

        if ($this->hasInputOption('connection', $input)) {
            foreach ($input->getOption('connection') as $conn) {
                $configOptions += $this->connectionToProperties($conn);
            }
        }

        if ($this->hasInputOption('migration-table', $input)) {
            $configOptions['propel']['migrations']['tableName'] = $input->getOption('migration-table');
        }

        if ($this->hasInputOption('output-dir', $input)) {
            $configOptions['propel']['paths']['migrationDir'] = $input->getOption('output-dir');
        }

        if ($this->hasInputOption('schema-dir', $input)) {
            $configOptions['propel']['paths']['schemaDir'] = $input->getOption('schema-dir');
        }

        return $this->buildGeneratorConfig($configOptions, $input);
    }

    /**
     * @param \Propel\Generator\Config\GeneratorConfig $generatorConfig
     *
     * @return \Propel\Generator\Manager\MigrationManager
     */
    protected function setUpMigrationManager(GeneratorConfig $generatorConfig): MigrationManager
    {
        $manager = new MigrationManager();
        $manager->setGeneratorConfig($generatorConfig);

        return $manager;
    }

    /**
     * @throws \Propel\Generator\Exception\RuntimeException
     *
     * @return \Propel\Generator\Config\GeneratorConfig
     */
    protected function getGeneratorConfig(): GeneratorConfig
    {
        if (!$this->generatorConfig) {
            throw new RuntimeException('GeneratorConfig not set up.');
        }

        return $this->generatorConfig;
    }

    /**
     * @throws \Propel\Generator\Exception\RuntimeException
     *
     * @return \Propel\Generator\Manager\MigrationManager
     */
    protected function getMigrationManager(): MigrationManager
    {
        if (!$this->migrationManager) {
            throw new RuntimeException('MigrationManager not set up.');
        }

        return $this->migrationManager;
    }

    /**
     * @return void
     */
    protected function registerMigrationManagerSchemas(): void
    {
        $schemas = $this->getSchemasFromConfig($this->generatorConfig);
        $this->getMigrationManager()->setSchemas($schemas);
    }

    /**
     * @param array<string> $customConnections
     *
     * @return void
     */
    protected function setUpMigrationManagerAccess(array $customConnections): void
    {
        $this->registerMigrationManagerConnections($customConnections);

        $migrationTableName = $this->getGeneratorConfig()->getConfigPropertyString('migrations.tableName', true);
        $this->getMigrationManager()->setMigrationTable($migrationTableName);

        $this->getMigrationManager()->setWorkingDirectory($this->migrationDir);
    }

    /**
     * @param array<string> $customConnections
     *
     * @return void
     */
    protected function registerMigrationManagerConnections(array $customConnections): void
    {
        $connections = [];
        if (!$customConnections) {
            $connections = $this->getGeneratorConfig()->getBuildConnections();
        } else {
            foreach ($customConnections as $connection) {
                [$name, $dsn, $infos] = $this->parseConnection($connection);
                $connections[$name] = array_merge(['dsn' => $dsn], $infos);
            }
        }

        $this->getMigrationManager()->setConnections($connections);
    }
}
