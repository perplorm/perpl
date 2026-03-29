<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Runtime\ActiveQuery;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Formatter\ArrayFormatter;
use Propel\Tests\TestCaseFixtures;

/**
 * @group database
 */
class CriteriaIsEmptyTest extends TestCaseFixtures
{
    public static function IsEmptyDataProvider(): array
    {
        static::checkInit();

        return [
            ['empty query', true, new Criteria()],
            ['select column', false, (new Criteria())->addSelectColumn('id')],
            ['AS column', false, (new Criteria())->addAsColumn('id', 'id')],
            ['select modifier', false, (new Criteria())->addSelectModifier('DISTINCT')],
            ['column filters', false, (new Criteria())->addFilter('id', 5)],
            ['having', false, (new Criteria())->addHaving('id')],
            ['join', false, (new Criteria())->addJoin('l.id', 'r.id')],
            ['update values', false, (new Criteria())->setUpdateValue('id', 3, \PDO::PARAM_STR)],
            
            ['select', false, (new ModelCriteria())->select('id')],
            ['formatter', false, (new ModelCriteria())->setFormatter(new ArrayFormatter())],
            ['modelAlias', false, new ModelCriteria(null, null, 'alias')],
           // ['with', false, (new ModelCriteria())->],

           ['cleared select column', true, (new Criteria())->addSelectColumn('id')->clearSelectColumns()],
           ['cleared AS column', true, (new Criteria())->addAsColumn('id', 'id')->clearSelectColumns()],
           ['cleared select modifier', true, (new Criteria())->addSelectModifier('DISTINCT')->removeSelectModifier('DISTINCT')],
        ];
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('IsEmptyDataProvider')]
    public function testIsEmpty(string $description, bool $expected, Criteria $query): void
    {
        $actual = $this->callMethod($query, 'isEmpty');
        $this->assertSame($expected, $actual, 'Criteria::isEmpty() failed with: ' . $description);
    }
    
}
