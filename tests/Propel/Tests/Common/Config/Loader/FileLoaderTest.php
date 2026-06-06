<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Common\Config\Loader;

use PHPUnit\Framework\Attributes\DataProvider;
use Propel\Common\Config\Loader\FileLoader as BaseFileLoader;
use Propel\Tests\TestCase;

class FileLoaderTest extends TestCase
{
    /** @var TestableFileLoader */
    private $loader;

    /**
     * @return void
     */
    public function setUp(): void
    {
        $this->loader = new TestableFileLoader();
    }

    /**
     * @return void
     */
    public function testResourceNameIsNotStringReturnsFalse()
    {
        $this->assertFalse(TestableFileLoader::checkSupports('ini', null));
        $this->assertFalse(TestableFileLoader::checkSupports('yaml', false));
    }

    /**
     * @return void
     */
    public function testExtensionIsNotStringOrArrayReturnsFalse()
    {
        $this->assertFalse(TestableFileLoader::checkSupports('', '/tmp/propel.yaml'));
        $this->assertFalse(TestableFileLoader::checkSupports('12', '/tmp/propel.yaml'));
    }
    public static function DistFileNameDataProvider(): array
    {
        return [
            ['perpl.dist.yml', true],
            ['perpl.yml.dist', true],
            ['perpl.dist.php', true],
            ['perpl.php.dist', true],
            ['perpl.disto.yml', false],
            ['perpl.php.disto', false],
        ];
    }

    #[DataProvider('DistFileNameDataProvider')]
    public function testIsDistFile(string $fileName, bool $expected): void
    {
        $isDist = BaseFileLoader::isDistFile($fileName);
        $maybeNot = $expected ? '' : 'not ';
        $this->assertTrue($isDist === $expected, "$fileName is {$maybeNot}dist file");
    }
}

class TestableFileLoader extends BaseFileLoader
{
    protected function loadFileContent(string $path): array
    {
        return [];
    }

    public function supports($resource, $type = null): bool
    {
        return false;
    }

    /**
     * @param string|string[] $ext
     * @param mixed $resource
     *
     * @return bool
     */
    public static function checkSupports($ext, $resource): bool
    {
        return parent::checkSupports($ext, $resource);
    }
}
