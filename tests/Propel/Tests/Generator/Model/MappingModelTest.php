<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Model;

use Propel\Generator\Model\MappingModel;
use Propel\Tests\TestCase;

/**
 * @author William Durand <william.durand1@gmail.com>
 */
class MappingModelTest extends TestCase
{
    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForGetDefaultValueForArray')]
    public function testGetDefaultValueForArray(string $value, $expected)
    {
        $this->assertEquals($expected, MappingModel::buildDefaultValueExpressionForArray($value));
    }

    public static function providerForGetDefaultValueForArray()
    {
        return [
            ['', null],
            ['FOO', '||FOO||'],
            ['FOO, BAR', '||FOO | BAR||'],
            ['FOO , BAR', '||FOO | BAR||'],
            ['FOO,BAR', '||FOO | BAR||'],
            [' ', null],
            [', ', null],
        ];
    }
}

class TestableMappingModel extends MappingModel
{
    /**
     * @return void
     */
    public function appendXml(DOMNode $node)
    {
    }

    /**
     * @return void
     */
    protected function setupObject(): void
    {
    }
}
