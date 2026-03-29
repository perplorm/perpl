<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Runtime\ActiveQuery;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Tests\Bookstore\BookQuery;
use Propel\Tests\Bookstore\RecordLabelQuery;
use Propel\Tests\TestCaseFixtures;

/**
 * @group database
 */
class ModelCriteriaPkFilterDetectionTest extends TestCaseFixtures
{
    public static function UpdateAffectsSingleRowDataProvider(): array
    {
        static::checkInit();

        return [
            ['empty query', false, BookQuery::create()],
            ['pk not covered', false, BookQuery::create()->filterByTitle()],
            ['single pk covered by filterBy', true, BookQuery::create()->filterById()],
            ['single pk covered and other', false, BookQuery::create()->filterById()->filterByTitle()],
            ['single pk covered by where', true, BookQuery::create()->where('book.id = ?', 42)],
            ['single pk covered by condition', true, BookQuery::create('b')->addFilter('book.id', 42, '=')],
            ['single pk covered by where with alias', true, BookQuery::create('b')->where('b.id = ?', 42)],
            ['single pk covered by where with wrong alias', true, BookQuery::create('b')->where('book.id = ?', 42)],
            ['single pk covered by condition with alias', true, BookQuery::create('b')->addFilter('b.id', 42, '=')],

            // currently not implemented
            // ['single pk in IN', false, BookQuery::create('b')->useInBookSummaryQuery()->endUse()],
            // ['single pk less equal', false, BookQuery::create()->filterById(42, '<=')],
            // ['single pk with OR', false, BookQuery::create()->filterById(42)->_or()->filterById(43)],

            ['complex pk partly covered 1', false, RecordLabelQuery::create()->filterById()],
            ['complex pk partly covered 2', false, RecordLabelQuery::create()->filterByAbbr()],
            ['complex pk partly covered and other', false, RecordLabelQuery::create()->filterById()->filterByName()],
            ['complex pk fully covered', true, RecordLabelQuery::create()->filterById()->filterByAbbr()],
            ['complex pk in WHERE', true, RecordLabelQuery::create('r')->where('r.id = ?', 42)->where('r.abbr = ?', '')],

            // currently not implemented
            // ['complex pk in complex WHERE', true, RecordLabelQuery::create('r')->where('r.id = ? AND r.abbr = ?', [42, ''])],
        ];
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('UpdateAffectsSingleRowDataProvider')]
    public function testUpdateAffectsSingleRow(string $description, bool $expected, ModelCriteria $query): void
    {
        $actual = $this->callMethod($query, 'updateAffectsSingleRow');
        $this->assertSame($expected, $actual, $description);
    }
    
}
