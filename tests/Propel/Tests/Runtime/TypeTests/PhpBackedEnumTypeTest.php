<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Runtime\TypeTests;

use Propel\Tests\Bookstore\Base\Book2Query;
use Propel\Tests\Bookstore\Book2;
use Propel\Tests\Bookstore\Map\Book2TableMap;
use Propel\Tests\Helpers\Bookstore\BookstoreTestBase;
use Propel\Tests\Helpers\ColorsBackedEnum;

/**
 * @group database
 */
class PhpBackedEnumTypeTest extends BookstoreTestBase
{
    protected function createBook(ColorsBackedEnum $color): Book2
    {
        $book = (new Book2())->setColor($color);
        $book->save();

        return $book;
    }

    public function testHydrate(): void
    {
        $book = $this->createBook(ColorsBackedEnum::Blue);
        Book2TableMap::clearInstancePool();

        $reloadedBook = Book2Query::create()->findOneById($book->getId());

        $this->assertNotNull($reloadedBook);
        $this->assertInstanceOf(ColorsBackedEnum::class, $reloadedBook->getColor());
    }

    public function testFilterByEnumCase(): void
    {
        $this->createBook(ColorsBackedEnum::Blue);
        $reloadedBook = Book2Query::create()->filterByColor(ColorsBackedEnum::Blue)->findOne();

        $this->assertNotNull($reloadedBook);
        $this->assertSame(ColorsBackedEnum::Blue, $reloadedBook->getColor());
    }

    public function testFilterByValue(): void
    {
        $this->createBook(ColorsBackedEnum::Blue);
        $reloadedBook = Book2Query::create()->filterByColor(ColorsBackedEnum::Blue->value)->findOne();

        $this->assertNotNull($reloadedBook);
        $this->assertSame(ColorsBackedEnum::Blue, $reloadedBook->getColor());
    }

    public function testFilterByArray(): void
    {
        $blueBook = $this->createBook(ColorsBackedEnum::Blue);
        $redBook = $this->createBook(ColorsBackedEnum::Red);

        $reloadedBooksArray = Book2Query::create()
            ->filterByColor([ColorsBackedEnum::Blue->value, ColorsBackedEnum::Red]) // mixing value and case
            ->find()->getArrayCopy();

        $this->assertContains($blueBook, $reloadedBooksArray);
        $this->assertContains($redBook, $reloadedBooksArray);
    }

    public function testCreateFromFilter(): void
    {
        $bookFromFilter = Book2Query::create()->filterById(-1)->filterByColor(ColorsBackedEnum::Yellow->value)->findOneOrCreate();

        $this->assertSame(ColorsBackedEnum::Yellow, $bookFromFilter->getColor());
    }
}
