<?php

declare(strict_types = 1);

namespace Propel\Generator\Manager;

use Propel\Generator\Builder\Om\AbstractOMBuilder;
use Propel\Generator\Builder\Om\TableMapLoaderScriptBuilder;
use SplFileInfo;
use Symfony\Component\Filesystem\Path;
use function getcwd;
use const DIRECTORY_SEPARATOR;

/**
 * Build list of files that model:build would create
 */
class ModelDryRunManager extends ModelManager
{
    protected array $filePathCollector = [];

    /**
     * @return array<array{path: string, status: string}>
     */
    public function getCollectedFilePaths(): array
    {
        return $this->filePathCollector;
    }

    /**
     * @return void
     */
    #[\Override()]
    public function build(): void
    {
        parent::build();
    }

    /**
     * @param string $configProp
     *
     * @return bool
     */
    protected function isFromRelativeConfig(string $configProp): bool
    {
        return Path::isRelative($this->getGeneratorConfig()->getConfigPropertyString($configProp, true));
    }

    /**
     * @param \Propel\Generator\Builder\Om\AbstractOMBuilder $builder
     * @param bool $overwrite
     *
     * @return int
     */
    #[\Override]
    protected function doBuild(AbstractOMBuilder $builder, bool $overwrite = true): int
    {
        $fileName = $this->getWorkingDirectory() . DIRECTORY_SEPARATOR . $builder->getClassFilePath();
        $file = new SplFileInfo($fileName);
        if (!$file->isFile()) {
            $path = Path::makeAbsolute($file->getPathname(), getcwd() ?: '.');
            $this->filePathCollector[] = ['path' => $path, 'status' => '(new)'];
        } else {
            $overrideBehavior = $overwrite ? 'override if changed' : 'no override';
            $this->filePathCollector[] = ['path' => $file->getRealPath(), 'status' => "(exists, $overrideBehavior)"];
        }

        return 1;
    }

    /**
     * Create script to import all table map files into database map.
     *
     * @return int Number of changed files
     */
    #[\Override]
    protected function createTableMapLoaderScript(): int
    {
        $builder = new TableMapLoaderScriptBuilder($this->getGeneratorConfig());
        $file = $builder->getFile();
        $filePath = $file->getPathname();
        if ($this->isFromRelativeConfig('paths.phpConfDir')) {
            $filePath = Path::makeAbsolute($filePath, getcwd() ?: '.');
        }

        $status = $file->isFile() ? 'recreate' : 'new';
        $this->filePathCollector[] = ['path' => $filePath, 'status' => "($status)"];

        return 1;
    }
}
