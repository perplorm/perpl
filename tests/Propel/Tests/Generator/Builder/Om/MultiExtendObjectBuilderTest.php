<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Builder\Om;

use Propel\Generator\Util\QuickBuilder;
use Propel\Tests\Helpers\Bookstore\BookstoreTestBase;

/**
 */
class MultiExtendObjectBuilderTest extends BookstoreTestBase
{
    public function testNonNullClassKey(): void
    {
        $schemaXml = <<<XML
    <database name="bookstore" namespace="\LeNonNullClassKey">
        <table name="main">
            <column name="id" type="INTEGER" primaryKey="true" autoIncrement="true"/>
            <column name="type" type="INTEGER" required="true" inheritance="single">
                <inheritance key="44" class="Main"/>
                <inheritance key="23" class="Child1" extends="Main"/>
            </column>
        </table>
    </database>
XML;
        QuickBuilder::buildClasses($schemaXml);

        $m = new \LeNonNullClassKey\Main();
        $typeId = $m->getType();

        $this->assertSame(44, $typeId);
    }
}
