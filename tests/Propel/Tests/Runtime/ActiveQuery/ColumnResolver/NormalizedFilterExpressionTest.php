<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Runtime\ActiveQuery\ColumnResolver;

use Propel\Runtime\ActiveQuery\ColumnResolver\NormalizedFilterExpression;
use Propel\Tests\TestCase;

/**
 */
class NormalizedFilterExpressionTest extends TestCase
{
    public static function ColumnLiteralDataProvider(): array
    {
        $column = ['column', 'table.column', 'schema.table.column', 'class\path\table.column', 'sqliteschema§table.column'];
        $noColumn = ['table.column = ?', '"table.column" = ?', 'table.column table.column', '" table.column"'];

        return array_merge(
            array_map(fn($s) => [$s, true], $column),
            [['   table.column   ', true]],
            array_map(fn($s) => ["'$s'", true], $column),
            array_map(fn($s) => ["\"$s\"", true], $column),
            array_map(fn($s) => [$s, false], $noColumn),
        );
    }

    /**
     *
     * @param string $columnOrClause
     * @param bool $expected
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('ColumnLiteralDataProvider')]
    public function testIsColumnLiteral(string $columnOrClause, bool $expected): void
    {
        $this->assertEquals($expected, NormalizedFilterExpression::isColumnLiteral($columnOrClause));
    }
}
