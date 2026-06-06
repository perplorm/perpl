<?php

declare(strict_types = 1);

namespace Propel\Common\Config\Loader;

use Propel\Common\Config\Exception\InputOutputException;
use Propel\Common\Config\Exception\InvalidArgumentException;
use Propel\Common\Config\FileLocator;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Config\Loader\FileLoader as SymfonyFileLoader;
use function in_array;
use function is_array;
use function is_readable;
use function is_string;
use function pathinfo;
use function preg_match;
use const PATHINFO_EXTENSION;

/**
 * Abstract class used by all file-based loaders.
 *
 * The resolve method and correlatives, with parameters between placeholders %name%, are heavily inspired to
 * Symfony\Component\DependencyInjection\ParameterBag class.
 */
abstract class FileLoader extends SymfonyFileLoader
{
    /**
     * @param string $path
     *
     * @return array
     */
    abstract protected function loadFileContent(string $path): array;

    /**
     * @param \Symfony\Component\Config\FileLocatorInterface|null $locator A FileLocator instance
     */
    public function __construct(?FileLocatorInterface $locator = null)
    {
        parent::__construct($locator ?? new FileLocator());
    }

    /**
     * Loads a Json file.
     *
     * @param mixed $resource The resource
     * @param string|null $type The resource type
     *
     * @return array
     */
    #[\Override]
    final public function load($resource, $type = null): array
    {
        $path = $this->getPath($resource);
        $rawConfig = $this->loadFileContent($path);

        return ConfigValueResolver::resolve($rawConfig, $path); //Resolve parameter placeholders (%name%)
    }

    /**
     * Get the path of a given resource
     *
     * @param string $file The resource
     *
     * @throws \Propel\Common\Config\Exception\InputOutputException If the path is not readable
     *
     * @return string
     */
    protected function getPath(string $file): string
    {
        $path = $this->locator->locate($file);
        if (!is_readable($path)) {
            throw new InputOutputException("You don't have permissions to access configuration file `$path`.");
        }

        return $path;
    }

    /**
     * Check if a resource has a given extension
     *
     * @param array<string>|string $ext An extension or an array of extensions
     * @param mixed $resource A resource
     *
     * @throws \Propel\Common\Config\Exception\InvalidArgumentException
     *
     * @return bool
     */
    protected static function checkSupports($ext, $resource): bool
    {
        if (!is_string($resource)) {
            return false;
        }

        $pathParts = pathinfo($resource);
        $extension = $pathParts['extension'] ?? '';
        $filename = $pathParts['filename'];

        if ($extension === 'dist') {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
        }

        if (is_string($ext)) {
            return ($ext === $extension);
        }

        if (!is_array($ext)) {
            throw new InvalidArgumentException('$ext must be string or string[]');
        }

        return in_array($extension, $ext, true);
    }

    /**
     * @param string $fileName
     *
     * @return int|bool
     */
    public static function isDistFile(string $fileName): bool
    {
        return (bool)preg_match('/\.dist(\.\w{3,4})?$/', $fileName); // ends with ".dist.<extension>" or just ".dist"
    }
}
