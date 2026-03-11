<?php

declare(strict_types = 1);

namespace Propel\Generator\Command;

use Propel\Generator\Manager\GraphvizManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GraphvizGenerateCommand extends AbstractCommand
{
    /**
     * @var string
     */
    public const DEFAULT_OUTPUT_DIRECTORY = 'generated-graphviz';

    /**
     * @inheritDoc
     */
    #[\Override]
    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption('output-dir', null, InputOption::VALUE_REQUIRED, 'The output directory', self::DEFAULT_OUTPUT_DIRECTORY)
            ->addOption('schema-dir', null, InputOption::VALUE_REQUIRED, 'The directory where the schema files are placed')
            ->setName('graphviz:generate')
            ->setAliases(['graphviz'])
            ->setDescription('Generate Graphviz files (.dot)');
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $generatorConfig = $this->buildGeneratorConfig([], $input, [
            'schema-dir' => 'paths.schemaDir',
        ]);

        $this->createDirectory($input->getOption('output-dir'));

        $manager = new GraphvizManager();
        $manager->setGeneratorConfig($generatorConfig);
        $manager->setSchemas($this->getSchemasFromConfig($generatorConfig));
        $manager->setLoggerClosure(function ($message) use ($input, $output): void {
            if ($input->getOption('verbose')) {
                $output->writeln($message);
            }
        });
        $manager->setWorkingDirectory($input->getOption('output-dir'));

        $manager->build();

        return static::CODE_SUCCESS;
    }
}
