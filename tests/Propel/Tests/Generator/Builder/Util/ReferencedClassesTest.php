<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Builder\Util;

use PHPUnit\Framework\Attributes\DataProvider;
use Propel\Generator\Builder\Om\ObjectBuilder;
use Propel\Generator\Builder\Util\ReferencedClasses;
use Propel\Tests\TestCase;

/**
 */
class ReferencedClassesTest extends TestCase
{
    public static function typeHintDataProvider(): array
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
     *
     * @param string $docType
     * @param string $expectedTypeHint
     * @param array<string, string> $expectedDeclarations
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('typeHintDataProvider')]
    public function testResolveTypeHintFromDocType(string $docType, string $expectedTypeHint, array $expectedDeclarations): void
    {
        $referencedClasses = new ReferencedClasses($this->createMock(ObjectBuilder::class));

        $actualTypeHint = $referencedClasses->resolveTypeDeclarationFromDocType($docType);
        $actualDeclarations = $referencedClasses->getDeclaredClasses();

        $this->assertSame($expectedTypeHint, $actualTypeHint, 'Type hint string should match');
        $this->assertSame($expectedDeclarations, $actualDeclarations, 'Type declaration should match');
    }

    public function testBuildUseStatements(): void
    {
        $referencedClasses = new ReferencedClasses($this->createMock(ObjectBuilder::class));
        $referencedClasses->registerConstant('CONST_3', 'CONST_1');
        $referencedClasses->registerConstant('CONST_2');
        $referencedClasses->registerFunction('function_c', 'function_a');
        $referencedClasses->registerFunction('function_b');
        $referencedClasses->registerClassByFullyQualifiedName('\Your\Namespace\ClassEol');
        $referencedClasses->registerClassByFullyQualifiedName('\My\Namespace\ClassFoo');
        $referencedClasses->registerClassByFullyQualifiedName('\My\Namespace\ClassBar');
        $referencedClasses->registerClassByFullyQualifiedName('\My\Namespace\ClassEol', 'Eol');

        $useStatements = $referencedClasses->buildUseStatements('', '');
        $expected = "use My\Namespace\ClassBar;
use My\Namespace\ClassEol as EolClassEol;
use My\Namespace\ClassFoo;
use Your\Namespace\ClassEol;
use function function_a;
use function function_b;
use function function_c;
use const CONST_1;
use const CONST_2;
use const CONST_3;
";

        $this->assertEquals($expected, $useStatements);
    }

    public static function ClassNameDataProvider(): array
    {
        return [
            ['LeNamespace\LeSubNamespace\LeFullName'],
            ['LeRootClassName'],
        ];
    }

    #[DataProvider('ClassNameDataProvider')]
    public function testResolveQualifiedNameFromClassAlias(string $classNameFq)
    {
        $referencedClasses = new ReferencedClasses($this->createMock(ObjectBuilder::class));
        $classAlias = $referencedClasses->registerClassByFullyQualifiedName($classNameFq, 'LeWhatever');
        $resolvedFcqn = $referencedClasses->getQualifiedNameForClassAlias($classAlias);

        $this->assertSame($classNameFq, $resolvedFcqn);

        $resolvedAlias = $referencedClasses->getLocalNameForClass($classNameFq);
        $this->assertSame($classAlias, $resolvedAlias);

        $resolvedAlias = $referencedClasses->getLocalNameForClass("\\$classNameFq");
        $this->assertSame($classAlias, $resolvedAlias);
    }
}
