<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Builder\Util;

use Propel\Generator\Builder\Om\ObjectBuilder;
use Propel\Generator\Builder\Util\ReferencedClasses;
use Propel\Tests\TestCase;

/**
 */
class ReferencedClassesTest extends TestCase
{
    public function typeHintDataProvider(): array
    {
        return [
            ['string', 'string', []],
            ['string|int', 'string|int', []],
            ['array<string|int>', 'array', []],
            ['Propel\Runtime\Propel', 'Propel', ['Propel\Runtime' => ['Propel' => 'Propel']]],
            ['DateTime', 'DateTimeInterface', ['' => ['DateTimeInterface' => 'DateTimeInterface']]],
            ['string|DateTimeImmutable', 'string|DateTimeInterface', ['' => ['DateTimeInterface' => 'DateTimeInterface']]],
        ];
    }

    /**
     * @dataProvider typeHintDataProvider
     * 
     * @param string $docType
     * @param string $expectedTypeHint
     * @param array<string, string> $expectedDeclarations
     *
     * @return void
     */
    public function testResolveTypeHintFromDocType(string $docType, string $expectedTypeHint, array $expectedDeclarations): void
    {
        $referencedClasses = new ReferencedClasses($this->createMock(ObjectBuilder::class));

        $actualTypeHint = $referencedClasses->resolveTypeDeclarationFromDocType($docType);
        $actualDeclarations = $referencedClasses->getDeclaredClasses();

        $this->assertSame($expectedTypeHint, $actualTypeHint, 'Type hint string should match');
        $this->assertSame($expectedDeclarations, $actualDeclarations, 'Type declaration should match');
    }
}
