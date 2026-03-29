<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Runtime\ActiveQuery;

use Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\LocalColumnExpression;
use Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\RemoteTypedColumnExpression;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Tests\Bookstore\AuthorQuery;
use Propel\Tests\Bookstore\BookQuery;
use Propel\Tests\TestCase;

/**
 * Tests name replacement in conditions. The tests were scattered across several classes and are somewhat redundant.
 */
class CriteriaReplaceNameTest extends TestCase
{
    private const PROJECT_ROOT = __DIR__ . '/../../../../..';

    /**
     * Provides test data
     *
     * @return string[][]|null[][]
     */
    public static function NamespacedBookReplaceNamesDataProvider()
    {
        return [
            ['Foo\\Bar\\NamespacedBook.Title = ?', 'Title', 'namespaced_book.title = ?'], // basic case
            ['Foo\\Bar\\NamespacedBook.Title=?', 'Title', 'namespaced_book.title=?'], // without spaces
            ['Foo\\Bar\\NamespacedBook.Id<= ?', 'Id', 'namespaced_book.id<= ?'], // with non-equal comparator
            ['Foo\\Bar\\NamespacedBook.AuthorId LIKE ?', 'AuthorId', 'namespaced_book.author_id LIKE ?'], // with SQL keyword separator
            ['(Foo\\Bar\\NamespacedBook.AuthorId) LIKE ?', 'AuthorId', '(namespaced_book.author_id) LIKE ?'], // with parenthesis
            ['(Foo\\Bar\\NamespacedBook.Id*1.5)=1', 'Id', '(namespaced_book.id*1.5)=1'], // ignore numbers
            // dealing with quotes
            ["Foo\\Bar\\NamespacedBook.Id + ' ' + Foo\\Bar\\NamespacedBook.AuthorId", null, "namespaced_book.id + ' ' + namespaced_book.author_id"],
            ["'Foo\\Bar\\NamespacedBook.Id' + Foo\\Bar\\NamespacedBook.AuthorId", null, "'Foo\\Bar\\NamespacedBook.Id' + namespaced_book.author_id"],
            ["Foo\\Bar\\NamespacedBook.Id + 'Foo\\Bar\\NamespacedBook.AuthorId'", null, "namespaced_book.id + 'Foo\\Bar\\NamespacedBook.AuthorId'"],
        ];
    }

    /**
     * Provides test data
     *
     * @return string[][]|null[][]
     */
    public static function BookReplaceNamesDataProvider()
    {
        return [
            ['Propel\Tests\Bookstore\Book.Title = ?', 'Title', 'book.title = ?'], // basic case
            ['Propel\Tests\Bookstore\Book.Title=?', 'Title', 'book.title=?'], // without spaces
            ['Propel\Tests\Bookstore\Book.Id<= ?', 'Id', 'book.id<= ?'], // with non-equal comparator
            ['Propel\Tests\Bookstore\Book.AuthorId LIKE ?', 'AuthorId', 'book.author_id LIKE ?'], // with SQL keyword separator
            ['(Propel\Tests\Bookstore\Book.AuthorId) LIKE ?', 'AuthorId', '(book.author_id) LIKE ?'], // with parenthesis
            ['(Propel\Tests\Bookstore\Book.Id*1.5)=1', 'Id', '(book.id*1.5)=1'], // ignore numbers
            // dealing with quotes
            ['Book.Title = ?', 'Title', 'book.title = ?'], // basic case
            ['Book.Title=?', 'Title', 'book.title=?'], // without spaces
            ['Book.Id<= ?', 'Id', 'book.id<= ?'], // with non-equal comparator
            ['Book.AuthorId LIKE ?', 'AuthorId', 'book.author_id LIKE ?'], // with SQL keyword separator
            ['(Book.AuthorId) LIKE ?', 'AuthorId', '(book.author_id) LIKE ?'], // with parenthesis
            ['(Book.Id*1.5)=1', 'Id', '(book.id*1.5)=1'], // ignore numbers
            // dealing with quotes
            ["Book.Id + ' ' + Book.AuthorId", null, "book.id + ' ' + book.author_id"],
            ["'Book.Id' + Book.AuthorId", null, "'Book.Id' + book.author_id"],
            ["Book.Id + 'Book.AuthorId'", null, "book.id + 'Book.AuthorId'"],

            ['1=1', null, '1=1'], // with no name
            ['', null, ''], // with empty string
        ];
    }

    /**
     * Provides test data
     *
     * @return string[][]
     */
    public static function BookstoreContestReplaceNamesDataProvider()
    {
        return [
            ['BookstoreContest.PrizeBookId = ?', 'PrizeBookId', 'contest.bookstore_contest.prize_book_id = ?'], // basic case
            ['BookstoreContest.PrizeBookId=?', 'PrizeBookId', 'contest.bookstore_contest.prize_book_id=?'], // without spaces
            ['BookstoreContest.Id<= ?', 'Id', 'contest.bookstore_contest.id<= ?'], // with non-equal comparator
            ['BookstoreContest.BookstoreId LIKE ?', 'BookstoreId', 'contest.bookstore_contest.bookstore_id LIKE ?'], // with SQL keyword separator
            ['(BookstoreContest.BookstoreId) LIKE ?', 'BookstoreId', '(contest.bookstore_contest.bookstore_id) LIKE ?'], // with parenthesis
            ['(BookstoreContest.Id*1.5)=1', 'Id', '(contest.bookstore_contest.id*1.5)=1'], // ignore numbers
        ];
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('NamespacedBookReplaceNamesDataProvider')]
    public function testReplaceNameFromNamespacedBook(string $origClause, ?string $columnPhpName, string $modifiedClause)
    {
        include self::PROJECT_ROOT . '/tests/Fixtures/namespaced/build/conf/bookstore_namespaced-conf.php';
        $c = new ModelCriteria('bookstore_namespaced', 'Foo\\Bar\\NamespacedBook');
        $this->runTestReplaceName($c, $origClause, $columnPhpName, $modifiedClause);
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('BookReplaceNamesDataProvider')]
    public function testReplaceNameFromBook(string $origClause, ?string $columnPhpName, string $modifiedClause)
    {
        include self::PROJECT_ROOT . '/tests/Fixtures/bookstore/build/conf/bookstore-conf.php';
        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Book');
        $this->runTestReplaceName($c, $origClause, $columnPhpName, $modifiedClause);
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('BookstoreContestReplaceNamesDataProvider')]
    public function testReplaceNameFromBookstoreContest(string $origClause, ?string $columnPhpName, string $modifiedClause)
    {
        include self::PROJECT_ROOT . '/tests/Fixtures/bookstore/build/conf/bookstore-conf.php';
        $c = new ModelCriteria('bookstore-schemas', '\Propel\Tests\BookstoreSchemas\BookstoreContest');
        $this->runTestReplaceName($c, $origClause, $columnPhpName, $modifiedClause);
    }

    /**
     * @return void
     */
    protected function runTestReplaceName(ModelCriteria $c, string $origClause, ?string $columnPhpName, string $modifiedClause)
    {
        $replacedColumns = $this->replaceColumns($c, $origClause);

        if ($columnPhpName) {
            $this->assertCount(1, $replacedColumns);
            $columnMap = $c->getTableMap()->getColumnByPhpName($columnPhpName);
            $this->assertEquals($columnMap, $replacedColumns[0]->getColumnMap());
        }
        $this->assertEquals($modifiedClause, $origClause);
    }

    /**
     * Provides test data
     *
     * @return string[][]|string[][][]
     */
    public static function ReplaceMultipleNamesDataProvider()
    {
        return [
            ['(Propel\Tests\Bookstore\Book.Id+Book.Id)=1', ['Id', 'Id'], '(book.id+book.id)=1'], // match multiple names
            ['CONCAT(Propel\Tests\Bookstore\Book.Title,"Book.Id")= ?', ['Title'], 'CONCAT(book.title,"Book.Id")= ?'], // ignore names in strings
            ['CONCAT(Propel\Tests\Bookstore\Book.Title," Book.Id ")= ?', ['Title'], 'CONCAT(book.title," Book.Id ")= ?'], // ignore names in strings
            ['MATCH (Propel\Tests\Bookstore\Book.Title,Book.isbn) AGAINST (?)', ['Title', 'ISBN'], 'MATCH (book.title,book.isbn) AGAINST (?)'],
        ];
    }

    /**
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('ReplaceMultipleNamesDataProvider')]
    public function testReplaceMultipleNames($origClause, $expectedColumns, $modifiedClause)
    {
        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Book');
        $replacedColumns = $this->replaceColumns($c, $origClause);

        $this->assertCount(count($expectedColumns), $replacedColumns);
        foreach ($replacedColumns as $index => $replacedColumn) {
            $expectedColumnName = $expectedColumns[$index];
            $expectedColumnMap = $c->getTableMap()->getColumnByPhpName($expectedColumnName);

            $this->assertEquals($expectedColumnMap, $replacedColumn->getColumnMap());
        }
        $this->assertEquals($modifiedClause, $origClause);
    }

    /**
     * @return void
     */
    public function testReplaceNamesFromSubquery()
    {
        $numberOfBooksQuery = BookQuery::create()
        ->addAsColumn('NumberOfBooks', 'COUNT(Book.Id)')
        ->select(['NumberOfBooks', 'AuthorId'])
        ->groupBy('Book.AuthorId');

        $joinCondition = 'Author.Id = numberOfBooks.AuthorId';
        
        $authorQuery = AuthorQuery::create()
        ->addSubquery($numberOfBooksQuery, 'numberOfBooks', false)
        ->where($joinCondition)
        ->withColumn('numberOfBooks.NumberOfBooks', 'NumberOfBooks');

        $replacedColumns = $this->replaceColumns($authorQuery, $joinCondition);        
        $this->assertEquals('author.id = numberOfBooks.author_id', $joinCondition, 'Aliases from subquery should not be replaced');

        $localAuthorIdMap = $authorQuery->getTableMap()->getColumn('id');
        $localAuthorIdColumnExpression = new LocalColumnExpression($authorQuery, 'author', $localAuthorIdMap);
        $remoteAuthorIdMap = $numberOfBooksQuery->getTableMap()->getColumn('author_id');
        $subqueryAuthorIdColumnExpression = new RemoteTypedColumnExpression($authorQuery, 'numberOfBooks', 'author_id', \PDO::PARAM_INT, $remoteAuthorIdMap );
        $this->assertEquals([$localAuthorIdColumnExpression, $subqueryAuthorIdColumnExpression], $replacedColumns);
    }


    /**
     * 
     * @param \Propel\Runtime\ActiveQuery\ModelCriteria $c
     * @param mixed $sql
     *
     * @return \Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression[]
     */
    protected function replaceColumns(ModelCriteria $c, &$sql): array
    {

        $expr = $c->normalizeFilterExpression($sql);
        $sql = $expr->getNormalizedFilterExpression();

        return $replacedColumns = $expr->getReplacedColumns();
    }
}
