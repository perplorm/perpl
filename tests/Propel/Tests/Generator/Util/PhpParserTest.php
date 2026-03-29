<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Util;

use Propel\Generator\Util\PhpParser;
use Propel\Tests\TestCase;

class PhpParserTest extends TestCase
{
    public static function basicClassCodeProvider()
    {
        $code = <<<EOF
<?php
class Foo
{
    public function bar1()
    {
        // this is bar1
    }

    protected \$bar2;

    public function bar2()
    {
        // this is bar2
    }

    /**
     * This is the bar3 method
     */
    public function bar3()
    {
        // this is bar3
    }

    public function bar4()
    {
        // this is bar4 with a curly brace }
        echo '}';
    }
}
EOF;

        return [[$code]];
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('basicClassCodeProvider')]
    public function testFindMethodNotExistsReturnsFalse($code)
    {
        $parser = new PhpParser($code);
        $this->assertFalse($parser->findMethod('foo'));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('basicClassCodeProvider')]
    public function testFindMethodNReturnsMethod($code)
    {
        $parser = new PhpParser($code);
        $expected = <<<EOF

    public function bar1()
    {
        // this is bar1
    }
EOF;
        $this->assertEquals($expected, $parser->findMethod('bar1'));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('basicClassCodeProvider')]
    public function testFindMethodPrecededByAttribute($code)
    {
        $parser = new PhpParser($code);
        $expected = <<<EOF


    public function bar2()
    {
        // this is bar2
    }
EOF;
        $this->assertEquals($expected, $parser->findMethod('bar2'));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('basicClassCodeProvider')]
    public function testFindMethodPrecededByComment($code)
    {
        $parser = new PhpParser($code);
        $expected = <<<EOF


    /**
     * This is the bar3 method
     */
    public function bar3()
    {
        // this is bar3
    }
EOF;
        $this->assertEquals($expected, $parser->findMethod('bar3'));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('basicClassCodeProvider')]
    public function testFindMethodWithWrongCurlyBraces($code)
    {
        $parser = new PhpParser($code);
        $expected = <<<EOF


    public function bar4()
    {
        // this is bar4 with a curly brace }
        echo '}';
    }
EOF;
        $this->assertEquals($expected, $parser->findMethod('bar4'));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('basicClassCodeProvider')]
    public function testRemoveMethodNotExistsReturnsFalse($code)
    {
        $parser = new PhpParser($code);
        $this->assertFalse($parser->removeMethod('foo'));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('basicClassCodeProvider')]
    public function testRemoveMethodReturnsMethod($code)
    {
        $parser = new PhpParser($code);
        $expected = <<<EOF

    public function bar1()
    {
        // this is bar1
    }
EOF;
        $this->assertEquals($expected, $parser->removeMethod('bar1'));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('basicClassCodeProvider')]
    public function testRemoveMethodRemovesMethod($code)
    {
        $parser = new PhpParser($code);
        $parser->removeMethod('bar1');
        $expected = <<<EOF
<?php
class Foo
{

    protected \$bar2;

    public function bar2()
    {
        // this is bar2
    }

    /**
     * This is the bar3 method
     */
    public function bar3()
    {
        // this is bar3
    }

    public function bar4()
    {
        // this is bar4 with a curly brace }
        echo '}';
    }
}
EOF;
        $this->assertEquals($expected, $parser->getCode());
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('basicClassCodeProvider')]
    public function testReplaceMethodNotExistsReturnsFalse($code)
    {
        $parser = new PhpParser($code);
        $this->assertFalse($parser->replaceMethod('foo', '// foo'));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('basicClassCodeProvider')]
    public function testReplaceMethodReturnsMethod($code)
    {
        $parser = new PhpParser($code);
        $expected = <<<EOF

    public function bar1()
    {
        // this is bar1
    }
EOF;
        $this->assertEquals($expected, $parser->replaceMethod('bar1', '// foo'));
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('basicClassCodeProvider')]
    public function testReplaceMethodReplacesMethod($code)
    {
        $parser = new PhpParser($code);
        $newCode = <<<EOF

    public function bar1prime()
    {
        // yep, I've been replaced
        echo 'bar';
    }
EOF;
        $parser->replaceMethod('bar1', $newCode);
        $expected = <<<EOF
<?php
class Foo
{
    public function bar1prime()
    {
        // yep, I've been replaced
        echo 'bar';
    }

    protected \$bar2;

    public function bar2()
    {
        // this is bar2
    }

    /**
     * This is the bar3 method
     */
    public function bar3()
    {
        // this is bar3
    }

    public function bar4()
    {
        // this is bar4 with a curly brace }
        echo '}';
    }
}
EOF;
        $this->assertEquals($expected, $parser->getCode());
    }
}
