<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Behavior\Sortable;

use InvalidArgumentException;
use Propel\Tests\Bookstore\Behavior\Map\SortableTable11TableMap;
use Propel\Tests\Bookstore\Behavior\Map\SortableTable12TableMap;

/**
 * Tests for SortableBehavior class
 *
 * @author Massimiliano Arione
 * @author William Durand <william.durand1@gmail.com>
 *
 * @group database
 */
class SortableBehaviorTest extends TestCase
{
    /**
     * @return void
     */
    public function testParameters()
    {
        $table11 = SortableTable11TableMap::getTableMap();
        $this->assertEquals(count($table11->getColumns()), 3, 'Sortable adds one columns by default');
        $this->assertTrue(method_exists('Propel\Tests\Bookstore\Behavior\SortableTable11', 'getRank'), 'Sortable adds a rank column by default');

        $table12 = SortableTable12TableMap::getTableMap();
        $this->assertEquals(count($table12->getColumns()), 4, 'Sortable does not add a column when it already exists');
        $this->assertTrue(method_exists('Propel\Tests\Bookstore\Behavior\SortableTable12', 'getPosition'), 'Sortable allows customization of rank_column name');
    }

    public function testNoScopeColumnError(): void
    {
        $schema = <<<XML
    <database>
        <table name="table">
            <column name="id" required="true" primaryKey="true" autoIncrement="true" type="INTEGER"/>

            <behavior name="sortable">
                <parameter name="use_scope" value="true"/>
            </behavior>
        </table>
    </database>
XML;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("The sortable behavior in `table` needs a `scope_column` parameter.");
        $this->buildDatabaseFromSchema($schema);
    }
}
