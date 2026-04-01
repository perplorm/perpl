<?php

declare(strict_types = 1);

namespace Propel\Tests\Suites\InstancePooling;

use Propel\Tests\Helpers\Bookstore\BookstoreTestBase;
use Propel\Tests\InstancePoolTest\Base\CompositePkTableQuery;
use Propel\Tests\InstancePoolTest\CompositePkTable;
use Propel\Tests\InstancePoolTest\DatePkTable;
use Propel\Tests\InstancePoolTest\DatePkTableQuery;
use Propel\Tests\InstancePoolTest\DatetimeFractionalPkTable;
use Propel\Tests\InstancePoolTest\DatetimeFractionalPkTableQuery;
use Propel\Tests\InstancePoolTest\DatetimePkTable;
use Propel\Tests\InstancePoolTest\DatetimePkTableQuery;
use Propel\Tests\InstancePoolTest\DecimalPkTable;
use Propel\Tests\InstancePoolTest\DecimalPkTableQuery;
use Propel\Tests\InstancePoolTest\EnumBinaryPkTable;
use Propel\Tests\InstancePoolTest\EnumBinaryPkTableQuery;
use Propel\Tests\InstancePoolTest\Base\FloatPkTableQuery;
use Propel\Tests\InstancePoolTest\FloatPkTable;
use Propel\Tests\InstancePoolTest\IntegerPkTable;
use Propel\Tests\InstancePoolTest\IntegerPkTableQuery;
use Propel\Tests\InstancePoolTest\Map\CompositePkTableTableMap;
use Propel\Tests\InstancePoolTest\Map\DatePkTableTableMap;
use Propel\Tests\InstancePoolTest\Map\DatetimeFractionalPkTableTableMap;
use Propel\Tests\InstancePoolTest\Map\DatetimePkTableTableMap;
use Propel\Tests\InstancePoolTest\Map\DecimalPkTableTableMap;
use Propel\Tests\InstancePoolTest\Map\EnumBinaryPkTableTableMap;
use Propel\Tests\InstancePoolTest\Map\FloatPkTableTableMap;
use Propel\Tests\InstancePoolTest\Map\IntegerPkTableTableMap;
use Propel\Tests\InstancePoolTest\Map\SetBinaryPkTableTableMap;
use Propel\Tests\InstancePoolTest\Map\UuidBinaryPkTableTableMap;
use Propel\Tests\InstancePoolTest\Map\VarcharPkTableTableMap;
use Propel\Tests\InstancePoolTest\SetBinaryPkTable;
use Propel\Tests\InstancePoolTest\SetBinaryPkTableQuery;
use Propel\Tests\InstancePoolTest\UuidBinaryPkTable;
use Propel\Tests\InstancePoolTest\UuidBinaryPkTableQuery;
use Propel\Tests\InstancePoolTest\VarcharPkTable;
use Propel\Tests\InstancePoolTest\VarcharPkTableQuery;

/**
 * @group database
 */
class InstancePoolTest extends BookstoreTestBase
{
    public static function ItemDataProvider(): array
    {
        return [
            [IntegerPkTable::class, IntegerPkTableQuery::class, IntegerPkTableTableMap::class, 42],
            [VarcharPkTable::class, VarcharPkTableQuery::class, VarcharPkTableTableMap::class, 'LePk'],
            [UuidBinaryPkTable::class, UuidBinaryPkTableQuery::class, UuidBinaryPkTableTableMap::class, 'b41a29db-cf78-4d43-83a9-4cd3e1e1b41a'],
            [DatePkTable::class, DatePkTableQuery::class, DatePkTableTableMap::class, '2026-03-27'],
            [DatetimePkTable::class, DatetimePkTableQuery::class, DatetimePkTableTableMap::class, '2026-03-27 16:01:12'], // NOTE: regular Datetime does not support milliseconds
            [DatetimeFractionalPkTable::class, DatetimeFractionalPkTableQuery::class, DatetimeFractionalPkTableTableMap::class, '2026-03-27 10:01:12.345678'], // NOTE: returned value will include milliseconds (no trailing zeros on Postgres)
            [FloatPkTable::class, FloatPkTableQuery::class, FloatPkTableTableMap::class, 1.5], // NOTE: Depends on rounding
            [DecimalPkTable::class, DecimalPkTableQuery::class, DecimalPkTableTableMap::class, '1.30000'],
            [EnumBinaryPkTable::class, EnumBinaryPkTableQuery::class, EnumBinaryPkTableTableMap::class, 'bar'],
            [SetBinaryPkTable::class, SetBinaryPkTableQuery::class, SetBinaryPkTableTableMap::class, ['bar','baz']],
            [CompositePkTable::class, CompositePkTableQuery::class, CompositePkTableTableMap::class, ['b41a29db-cf78-4d43-83a9-4cd3e1e1b41a', true, 'lePk']],
        ];
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('ItemDataProvider')]
    public function testItemInPoolAfterSave(string $modelClass, string $queryClass, string $tablemapClass, $setPkArgs): void
    {
        if ($modelClass === DecimalPkTable::class) {
            $this->markTestSkipped('Decimals are not working atm');
        }
        $queryClass::create()->deleteAll();
        $query = $queryClass::create()->keepQuery()->filterByPrimaryKey($setPkArgs);
        $initialPayload = 'First Payload';
        $updatedPayload = 'Second Payload';

        // save initial item

        $initialItem = new $modelClass();
        $initialItem->setPrimaryKey($setPkArgs);
        $initialItem->setPayload($initialPayload)->save();

        // check saved object is returned from cache, keeping unsaved changes

        $initialItem->setPayload($updatedPayload);

        $savedItemFromCache = $query->findOne();
        $this->assertSame($initialItem, $savedItemFromCache, 'Should retrieve item from pool.');
        $this->assertSame($updatedPayload, $savedItemFromCache->getPayload(), 'Should keep unsaved changes.');

        // check clearing cache creates new object

        $tablemapClass::clearInstancePool();
        $loadedItem = $query->findOne();

        $this->assertNotSame($initialItem, $loadedItem, 'Should be new item after clearing cache.');
        $this->assertSame($initialPayload, $loadedItem->getPayload(), 'Should reset unsaved changes.');

        // check queried object was added to cache, keeping unsaved changes 

        $loadedItem->setPayload($updatedPayload);
        $reloadedItem = $query->findOne();

        $this->assertSame($loadedItem, $reloadedItem, 'Should retrieve item from pool.');
        $this->assertSame($updatedPayload, $reloadedItem->getPayload(), 'Should keep unsaved changes.');

    }
}
