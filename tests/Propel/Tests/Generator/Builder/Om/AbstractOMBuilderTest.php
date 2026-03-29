<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Builder\Om;

use Propel\Generator\Builder\Om\AbstractOMBuilder;
use Propel\Generator\Config\QuickGeneratorConfig;
use Propel\Generator\Model\Table;
use Propel\Tests\Bookstore\Author;
use Propel\Tests\Bookstore\Book;
use Propel\Tests\Bookstore\Publisher;
use Propel\Tests\TestCase;

/**
 * Test class for OMBuilder.
 *
 * @author François Zaninotto
 */
class AbstractOMBuilderTest extends TestCase
{
    /**
     * @return void
     */
    public function testClear()
    {
        $b = new Book();
        $b->setNew(false);
        $b->clear();
        $this->assertTrue($b->isNew(), 'clear() sets the object to new');
        $b = new Book();
        $b->setDeleted(true);
        $b->clear();
        $this->assertFalse($b->isDeleted(), 'clear() sets the object to not deleted');
    }

    /**
     * @return void
     */
    public function testToStringUsesDefaultStringFormat()
    {
        $author = new Author();
        $author->setFirstName('John');
        $author->setLastName('Doe');
        $expected = <<<EOF
Id: null
FirstName: John
LastName: Doe
Email: null
Age: null

EOF;
        $this->assertEquals($expected, (string)$author, 'generated __toString() uses default string format and exportTo()');

        $publisher = new Publisher();
        $publisher->setId(345345);
        $publisher->setName('Peguinoo');
        $expected = <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<data>
  <Id>345345</Id>
  <Name><![CDATA[Peguinoo]]></Name>
</data>

EOF;
        $this->assertEquals($expected, (string)$publisher, 'generated __toString() uses default string format and exportTo()');
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('dataGetPackagePath')]
    public function testGetPackagePath($package, $expectedPath)
    {
        $builder = new OMBuilderMock();
        $builder->setPackage($package);

        $this->assertEquals($expectedPath, $builder->getPackagePath());
    }

    public static function dataGetPackagePath()
    {
        return [
            ['', ''],
            ['foo.bar', 'foo/bar'],
            ['foo/bar', 'foo/bar'],
            ['foo.bar.map', 'foo/bar/map'],
            ['foo.bar.om', 'foo/bar/om'],
            ['foo.bar.baz', 'foo/bar/baz'],
            ['foo.bar.baz.om', 'foo/bar/baz/om'],
            ['foo.bar.baz.map', 'foo/bar/baz/map'],
            ['foo/bar/baz', 'foo/bar/baz'],
            ['foo/bar/baz/map', 'foo/bar/baz/map'],
            ['foo/bar/baz/om', 'foo/bar/baz/om'],
            ['foo/bar.baz', 'foo/bar.baz'],
            ['foo/bar.baz.map', 'foo/bar.baz/map'],
            ['foo/bar.baz.om', 'foo/bar.baz/om'],
            ['foo.bar/baz', 'foo.bar/baz'],
            ['foo.bar/baz.om', 'foo.bar/baz/om'],
            ['foo.bar/baz.map', 'foo.bar/baz/map'],
        ];
    }

    /**
     * @return array<array{bool,string}>
     */
    public static function StrictTypesDataProvider(): array
    {
        return [
            [true, "<?php\n\ndeclare(strict_types = 1);\n\nnamespace"],
            [false, "<?php\n\nnamespace"],
        ];
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('StrictTypesDataProvider')]
    public function testDeclareStrictTypes(bool $declareStrictType, string $expectedStart)
    {
        $builder = new class(new Table('Foo')) extends AbstractOMBuilder{
            protected function addClassBody(string &$script): void {}
            protected function addClassClose(string &$script): void{}
            protected function addClassOpen(string &$script): void{}
            public function getUnprefixedClassName(): string { return 'Foo';}
            public function getNamespace(): string { return 'FooNamespace';}
        };
        $config = new QuickGeneratorConfig(['propel.generator.declareStrictTypesInBuilders' => $declareStrictType]);
        $builder->setGeneratorConfig($config);
        $content = $builder->build();

        $this->assertStringStartsWith($expectedStart, $content);
    }
}

class OMBuilderMock extends AbstractOMBuilder
{
    protected $pkg;

    public function __construct()
    {
    }

    /**
     * @return void
     */
    public function setPackage($pkg)
    {
        $this->pkg = $pkg;
    }

    public function getPackage(): string
    {
        return $this->pkg;
    }

    /**
     * @return void
     */
    public function getUnprefixedClassName(): string
    {
        return '';
    }

    /**
     * @return void
     */
    protected function addClassOpen(&$script): void
    {
    }

    /**
     * @return void
     */
    protected function addClassBody(&$script): void
    {
    }

    /**
     * @return void
     */
    protected function addClassClose(&$script): void
    {
    }
}
