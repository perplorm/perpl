<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Propel\Generator\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function escapeshellarg;
use function file_put_contents;
use function shell_exec;
use function sprintf;
use function time;
use const DIRECTORY_SEPARATOR;

/**
 * @author William Durand <william.durand1@gmail.com>
 * @author Fredrik Wollsén <fredrik@neam.se>
 */
class MigrationCreateCommand extends AbstractMigrationCommand
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
            ->addOption('output-dir', null, InputOption::VALUE_REQUIRED, 'The output directory where the migration files are located')
            ->addOption('connection', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Connection to use. Example: \'bookstore=mysql:host=127.0.0.1;dbname=test;user=root;password=foobar\' where "bookstore" is your propel database name (used in your schema.xml)', [])
            ->addOption('editor', null, InputOption::VALUE_OPTIONAL, 'The text editor to use to open diff files', null)
            ->addOption('comment', 'm', InputOption::VALUE_OPTIONAL, 'A comment for the migration', '')
            ->addOption('suffix', null, InputOption::VALUE_OPTIONAL, 'A suffix for the migration class', '')
            ->setName('migration:create')
            ->setDescription('Create an empty migration class');
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->setUp($input);
        $this->registerMigrationManagerSchemas();

        $suffix = $input->getOption('suffix');
        $comment = $input->getOption('comment');
        $filePath = $this->createMigrationFile($suffix, $comment);

        $output->writeln(sprintf('"%s" file successfully created.', $filePath));

        $editorCmd = $input->getOption('editor');
        if ($editorCmd !== null) {
            $output->writeln(sprintf('Using "%s" as text editor', $editorCmd));
            shell_exec($editorCmd . ' ' . escapeshellarg($filePath));
        } else {
            $output->writeln('Now add SQL statements and data migration code as necessary.');
            $output->writeln('Once the migration class is valid, call the "migrate" task to execute it.');
        }

        return static::CODE_SUCCESS;
    }

    /**
     * @param string $suffix
     * @param string $comment
     *
     * @return string
     */
    protected function createMigrationFile(string $suffix, string $comment): string
    {
        $migrationManager = $this->getMigrationManager();
        $migrationsUp = [];
        $migrationsDown = [];
        foreach ($migrationManager->getDatabases() as $appDatabase) {
            $name = $appDatabase->getName();
            $migrationsUp[$name] = '';
            $migrationsDown[$name] = '';
        }

        $timestamp = time();
        $migrationFileName = $migrationManager->getMigrationFileName($timestamp, $suffix);
        $migrationClassBody = $migrationManager->getMigrationClassBody($migrationsUp, $migrationsDown, $timestamp, $comment, $suffix);

        $filePath = $this->migrationDir . DIRECTORY_SEPARATOR . $migrationFileName;
        file_put_contents($filePath, $migrationClassBody);

        return $filePath;
    }
}
