<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Propel\Generator\Manager;

use RuntimeException;
use Symfony\Component\Filesystem\Path;
use function array_key_exists;
use function array_key_first;
use function array_pop;
use function count;
use function current;
use function explode;
use function getcwd;
use function is_array;
use function is_object;
use function is_string;
use function ksort;
use function sort;
use function var_export;
use const DIRECTORY_SEPARATOR;

/**
 * Build list of files that model:build would create
 */
class PrintPropelDirectoriesManager extends AbstractManager
{
    /**
     * @var string
     */
    private const VERTICAL_WITH_INDENT = '│    ';

    /**
     * @var string
     */
    private const VERTICAL = '│';

    /**
     * @var string
     */
    private const VERTICAL_RIGHT = '├';

    /**
     * @var string
     */
    private const HORIZONTAL = '─';

    /**
     * @var string
     */
    private const UP_RIGHT = '└';

    /**
     * @var string
     */
    private const INDENT_UNIT = '     ';

    /**
     * @var string
     */
    private const BULLET = '╼';

    /**
     * @var array
     */
    protected array $directoryStructure = [];

    /**
     * @return void
     */
    public function build(): void
    {
        $this->loadConfigDirectoryPaths();
        $this->loadGeneratedFilePaths();
        $this->loadSchemaFiles();

        $locations = $this->mergeNonBranchingPaths($this->directoryStructure);
        $directoryStructure = $this->printDirectoryStructure($locations, '');

        $description = 'Directory structure and files according to current config (directories marked as <error>relative</error> change when perpl is called from a different path):';
        $this->log("$description\n\n$directoryStructure");
    }

    /**
     * @param array<array|string|object{type: string, param: string, description: string, isRelative: bool}> $locations
     * @param string $prefix
     *
     * @throws \RuntimeException
     *
     * @return string
     */
    protected function printDirectoryStructure(array $locations, string $prefix): string
    {
        $structure = '';
        $countToLastItem = count($locations);
        foreach ($locations as $path => $value) {
            $countToLastItem--;
            $branch = ($countToLastItem === 0 ? self::UP_RIGHT : self::VERTICAL_RIGHT) . self::HORIZONTAL;
            if (is_array($value)) {
                $connector = self::HORIZONTAL;
                $indent = $prefix . ($countToLastItem > 0 ? self::VERTICAL_WITH_INDENT : self::INDENT_UNIT);
                $content = $path . DIRECTORY_SEPARATOR . "\n" . $this->printDirectoryStructure($value, $indent); // . $prefix . static::VERTICAL . "\n"
            } elseif (is_object($value) && $value->type === 'config-dir') {
                $branch = $countToLastItem > 0 ? self::VERTICAL : ' '; // FIXME: prints vertical if multiple config entries
                $connector = '';
                $content = $this->printConfigDirData($value) . "\n";
            } elseif (is_string($value)) {
                $connector = self::BULLET;
                $content = "{$path} $value\n";
            } else {
                throw new RuntimeException('Cannot print leave node: ' . var_export($value, true));
            }

            $structure .= "{$prefix}{$branch}{$connector} {$content}";
        }

        return $structure;
    }

    /**
     * @param object{type: string, param: string, description: string, isRelative: bool} $data
     *
     * @return string
     */
    protected function printConfigDirData(object $data): string
    {
        $relative = $data->isRelative ? ' <error>!from relative path!</error>' : '';

        return " <info>{$data->param}</info> <comment>{$data->description}</comment>$relative";
    }

    /**
     * @param string $key
     * @param mixed $value
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    public function insertDirectory(string $key, $value): void
    {
        if (!$key) {
            throw new RuntimeException('Cannot insert empty key into configuration');
        }

        $sections = explode(DIRECTORY_SEPARATOR, $key);
        $lastKey = array_pop($sections);
        $location = &$this->directoryStructure;
        foreach ($sections as $section) {
            if (!array_key_exists($section, $location)) {
                $location[$section] = [];
            }
            if (!is_array($location[$section])) {
                throw new RuntimeException("While inserting key `$key` into config: Cannot create section `$section`, property exists and is not empty ");
            }
            $location = &$location[$section];
        }
        $location[$lastKey] = $value;
    }

    /**
     * @return void
     */
    protected function loadConfigDirectoryPaths(): void
    {
        $config = $this->getGeneratorConfig();

        $pathDescription = [
            'paths.migrationDir' => 'Migration files (target for migration:create, migration:migrate, etc)',
            'paths.schemaDir' => 'Schema XML files (input for migration:diff, database:reverse, etc)',
            'paths.sqlDir' => 'SQL database initialization files for sql:insert (user-generated and generated from schema.xml by sql:build)',
            'paths.phpConfDir' => 'Perpl configurations files (from config:convert and model:build).',
            'paths.phpDir' => 'Base target directory for model:build',
        ];

        foreach ($pathDescription as $configKey => $description) {
            $directory = $config->getConfigPropertyString($configKey, true);
            $directory = Path::canonicalize($directory);
            $isRelative = Path::isRelative($directory);
            if ($isRelative) {
                $directory = Path::makeAbsolute($directory, getcwd() ?: '.');
            }

            $this->insertDirectory($directory, [
                (object)['type' => 'config-dir', 'param' => $configKey, 'description' => $description, 'isRelative' => $isRelative],
            ]);
        }
    }

    /**
     * @return void
     */
    protected function loadSchemaFiles(): void
    {
        foreach ($this->getSchemas() as $schemaFileInfo) {
            $this->insertDirectory($schemaFileInfo->getRealPath(), '');
        }
    }

    /**
     * @return void
     */
    protected function loadGeneratedFilePaths(): void
    {
        if (!$this->getSchemas()) {
            $this->log("<error>No schema.xml file provided, skipping model class output (check --schema-dir argument or paths.schemaDir in config).</error>\n\n");

            return;
        }
        $filePaths = $this->getFilePathsFromManager();
        foreach ($filePaths as $filePath) {
            $this->insertDirectory($filePath['path'], $filePath['status']);
        }
    }

    /**
     * @return array<array{path: string, status: string}>
     */
    protected function getFilePathsFromManager(): array
    {
        $manager = new ModelDryRunManager();
        $manager->setGeneratorConfig($this->getGeneratorConfig());
        $manager->setSchemas($this->getSchemas());
        $configDir = $this->getGeneratorConfig()->getConfigPropertyString('paths.phpDir', true);
        $manager->setWorkingDirectory($configDir);
        $manager->build();

        return $manager->getCollectedFilePaths();
    }

    /**
     * @param array $paths
     *
     * @return array
     */
    protected function mergeNonBranchingPaths(array $paths): array
    {
        if (count($paths) === 1) {
            $key = array_key_first($paths);
            $value = current($paths);
            while (is_array($value) && count($value) === 1 && !is_object(current($value))) {
                $key .= DIRECTORY_SEPARATOR . array_key_first($value);
                $value = current($value);
            }
            $value = is_array($value) ? $this->mergeNonBranchingPaths($value) : $value;

            return [$key => $value];
        }

        $descriptions = [];
        $leaves = [];
        $nodes = [];
        foreach ($paths as $key => $value) {
            if (is_object($value)) {
                $descriptions[] = $value;

                continue;
            }
            if (is_string($value)) {
                $leaves[$key] = $value;

                continue;
            }
            $merged = $this->mergeNonBranchingPaths([$key => $value]);
            $value = current($merged);
            $key = array_key_first($merged);
            $nodes[$key] = $value;
        }
        ksort($nodes);
        ksort($leaves);
        sort($descriptions);

        return [...$descriptions, ...$nodes, ...$leaves];
    }
}
