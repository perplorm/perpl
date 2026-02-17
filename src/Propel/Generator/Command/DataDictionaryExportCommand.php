<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Propel\Generator\Command;

use Propel\Generator\Manager\DataDictionaryExportManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function realpath;
use function sprintf;

class DataDictionaryExportCommand extends AbstractCommand
{
    /**
     * @var string
     */
    public const DEFAULT_OUTPUT_DIRECTORY = 'generated-datadictionary';

    /**
     * @var string
     */
    protected const OPTION_OUTPUT_DIR = 'output-dir';

    /**
     * @var string
     */
    protected const OPTION_SCHEMA_DIR = 'schema-dir';

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    #[\Override]
    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption(static::OPTION_OUTPUT_DIR, null, InputOption::VALUE_REQUIRED, 'The output directory', static::DEFAULT_OUTPUT_DIRECTORY)
            ->addOption(static::OPTION_SCHEMA_DIR, null, InputOption::VALUE_REQUIRED, 'The directory where the schema files are placed')
            ->setName('datadictionary:export')
            ->setAliases(['datadictionary', 'md'])
            ->setDescription('Generate Data Dictionary files (.md)');
    }

    /**
     * {@inheritDoc}
     *
     * @see \Symfony\Component\Console\Command\Command::execute()
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int
     */
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $generatorConfig = $this->buildGeneratorConfig([], $input, [
            static::OPTION_SCHEMA_DIR => 'paths.schemaDir',
        ]);

        $manager = new DataDictionaryExportManager();
        $manager->setGeneratorConfig($generatorConfig);

        $schemas = $this->getSchemasFromConfig($generatorConfig);
        $manager->setSchemas($schemas);
        $manager->setLoggerClosure(function ($message) use ($input, $output): void {
            if ($input->getOption('verbose')) {
                $output->writeln($message);
            }
        });

        $outputDir = $input->getOption(static::OPTION_OUTPUT_DIR);
        $this->createDirectory($outputDir);
        $manager->setWorkingDirectory($outputDir);

        $manager->build();

        $output->writeln(sprintf('<info>Generated data dictionary file at %s.</info>', realpath($outputDir)));

        return static::CODE_SUCCESS;
    }
}
