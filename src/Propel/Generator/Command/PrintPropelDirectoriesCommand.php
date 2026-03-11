<?php

declare(strict_types = 1);

namespace Propel\Generator\Command;

use Propel\Generator\Manager\PrintPropelDirectoriesManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function class_exists;

class PrintPropelDirectoriesCommand extends AbstractCommand
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
            ->addOption('connection', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Connection to use. Example: \'bookstore=mysql:host=127.0.0.1;dbname=test;user=root;password=foobar\' where "bookstore" is your propel database name (used in your schema.xml)', [])
            ->setName('config:preview')
            ->setDescription('Output directory structure of files and directories known to Perpl with current configuration, including auto-generated files.');
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!class_exists('\Symfony\Component\Filesystem\Path')) {
            $output->writeln('<error>Requires Symfony 5.4 or above (see https://symfony.com/blog/new-in-symfony-5-4-filesystem-path-class)</error>');

            return static::CODE_ERROR;
        }

        $config = $this->buildGeneratorConfig([], $input, [
            'schema-dir' => 'paths.schemaDir',
        ]);

        $manager = new PrintPropelDirectoriesManager();
        $manager->setGeneratorConfig($config);
        $schemas = $this->getSchemasFromConfig($config, false);
        $manager->setSchemas($schemas);
        $manager->setLoggerClosure(fn ($data) => $output->write($data));
        $manager->build();

        return static::CODE_SUCCESS;
    }
}
