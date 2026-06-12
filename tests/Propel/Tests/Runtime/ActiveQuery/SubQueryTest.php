<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Runtime\ActiveQuery;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\RuntimeException;
use Propel\Runtime\Propel;
use Propel\Tests\Bookstore\BookQuery;
use Propel\Tests\Bookstore\Map\BookTableMap;
use Propel\Tests\Helpers\Bookstore\BookstoreTestBase;

/**
 * Test class for SubQueryTest.
 *
 * @author Francois Zaninotto
 *
 * @group database
 */
class SubQueryTest extends BookstoreTestBase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @param \Propel\Runtime\ActiveQuery\Criteria $criteria
     * @param mixed $expectedSql
     * @param mixed $expectedParams
     * @param string $message
     *
     * @return void
     */
    protected function assertCriteriaTranslation($criteria, $expectedSql, $expectedParams, $message = '')
    {
        $params = [];
        $result = $criteria->createSelectSql($params);

        $this->assertEquals($expectedSql, $result, $message);
        $this->assertEquals($expectedParams, $params, $message);
    }

    /**
     * @return void
     */
    public function testSubQueryExplicit()
    {
        $subCriteria = new BookQuery();
        BookTableMap::addSelectColumns($subCriteria);
        $subCriteria->orderByTitle(Criteria::ASC);

        $c = new BookQuery();
        $c->setAutoAddTable(false);
        BookTableMap::addSelectColumns($c, 'subCriteriaAlias');
        $c->addSubquery($subCriteria, 'subCriteriaAlias', false);
        $c->groupBy('subCriteriaAlias.AuthorId');

        if ($this->isDb('pgsql')) {
            $sql = $this->getSql('SELECT subCriteriaAlias.id, subCriteriaAlias.title, subCriteriaAlias.isbn, subCriteriaAlias.price, subCriteriaAlias.publisher_id, subCriteriaAlias.author_id FROM (SELECT book.id, book.title, book.isbn, book.price, book.publisher_id, book.author_id FROM book ORDER BY book.title ASC) AS subCriteriaAlias GROUP BY subCriteriaAlias.author_id,subCriteriaAlias.id,subCriteriaAlias.title,subCriteriaAlias.isbn,subCriteriaAlias.price,subCriteriaAlias.publisher_id');
        } else {
            $sql = $this->getSql('SELECT subCriteriaAlias.id, subCriteriaAlias.title, subCriteriaAlias.isbn, subCriteriaAlias.price, subCriteriaAlias.publisher_id, subCriteriaAlias.author_id FROM (SELECT book.id, book.title, book.isbn, book.price, book.publisher_id, book.author_id FROM book ORDER BY book.title ASC) AS subCriteriaAlias GROUP BY subCriteriaAlias.author_id');
        }

        $params = [];
        $this->assertCriteriaTranslation($c, $sql, $params, 'addSubQueryCriteriaInFrom() combines two queries successfully');
    }

    /**
     * @return void
     */
    public function testSubQueryWithoutSelect()
    {
        $subCriteria = new BookQuery();
        // no addSelectColumns()

        $c = new BookQuery();
        $c->addSubquery($subCriteria, 'subCriteriaAlias');
        $c->filterByPrice(20, Criteria::LESS_THAN);

        $sql = $this->getSql('SELECT subCriteriaAlias.id, subCriteriaAlias.title, subCriteriaAlias.isbn, subCriteriaAlias.price, subCriteriaAlias.publisher_id, subCriteriaAlias.author_id FROM (SELECT book.id, book.title, book.isbn, book.price, book.publisher_id, book.author_id FROM book) AS subCriteriaAlias WHERE subCriteriaAlias.price<:p1');

        $params = [
            ['table' => 'book', 'column' => 'price', 'value' => 20],
        ];
        $this->assertCriteriaTranslation($c, $sql, $params, 'addSubquery() adds select columns if none given');
    }

    /**
     * @return void
     */
    public function testSubQueryWithoutAlias()
    {
        $subCriteria = new BookQuery();
        $subCriteria->addSelfSelectColumns();

        $c = new BookQuery();
        $c->addSubquery($subCriteria); // no alias
        $c->filterByPrice(20, Criteria::LESS_THAN);

        $sql = $this->getSql('SELECT subquery_1.id, subquery_1.title, subquery_1.isbn, subquery_1.price, subquery_1.publisher_id, subquery_1.author_id FROM (SELECT book.id, book.title, book.isbn, book.price, book.publisher_id, book.author_id FROM book) AS subquery_1 WHERE subquery_1.price<:p1');

        $params = [
            ['table' => 'book', 'column' => 'price', 'value' => 20],
        ];
        $this->assertCriteriaTranslation($c, $sql, $params, 'addSubquery() forges a unique alias if none is given');
    }

    /**
     * @return void
     */
    public function testSubQueryWithoutAliasAndSelect()
    {
        $subCriteria = new BookQuery();
        // no select

        $c = new BookQuery();
        $c->addSubquery($subCriteria); // no alias
        $c->filterByPrice(20, Criteria::LESS_THAN);

        $sql = $this->getSql('SELECT subquery_1.id, subquery_1.title, subquery_1.isbn, subquery_1.price, subquery_1.publisher_id, subquery_1.author_id FROM (SELECT book.id, book.title, book.isbn, book.price, book.publisher_id, book.author_id FROM book) AS subquery_1 WHERE subquery_1.price<:p1');

        $params = [
            ['table' => 'book', 'column' => 'price', 'value' => 20],
        ];
        $this->assertCriteriaTranslation($c, $sql, $params, 'addSubquery() forges a unique alias and adds select columns by default');
    }

    /**
     * @return void
     */
    public function testSubQueryWithoutAliasSeveral()
    {
        $c1 = new BookQuery();
        $c1->filterByPrice(10, Criteria::GREATER_THAN);

        $c2 = new BookQuery();
        $c2->filterByPrice(20, Criteria::LESS_THAN);

        $c3 = new BookQuery();
        $c3->addSubquery($c1); // no alias
        $c3->addSubquery($c2); // no alias
        $c3->filterByTitle('War%', Criteria::LIKE);

        $sql = $this->getSql('SELECT subquery_1.id, subquery_1.title, subquery_1.isbn, subquery_1.price, subquery_1.publisher_id, subquery_1.author_id, subquery_2.id, subquery_2.title, subquery_2.isbn, subquery_2.price, subquery_2.publisher_id, subquery_2.author_id FROM (SELECT book.id, book.title, book.isbn, book.price, book.publisher_id, book.author_id FROM book WHERE book.price>:p2) AS subquery_1, (SELECT book.id, book.title, book.isbn, book.price, book.publisher_id, book.author_id FROM book WHERE book.price<:p3) AS subquery_2 WHERE subquery_2.title LIKE :p1');

        $params = [
            ['table' => 'book', 'column' => 'title', 'value' => 'War%'],
            ['table' => 'book', 'column' => 'price', 'value' => 10],
            ['table' => 'book', 'column' => 'price', 'value' => 20],
        ];

        $this->assertCriteriaTranslation($c3, $sql, $params, 'addSelectQuery() forges a unique alias if none is given');
    }

    /**
     * @return void
     */
    public function testSubQueryWithoutAliasRecursive()
    {
        $c1 = new BookQuery();

        $c2 = new BookQuery();
        $c2->addSubquery($c1); // no alias
        $c2->filterByPrice(20, Criteria::LESS_THAN);

        $c3 = new BookQuery();
        $c3->addSubquery($c2); // no alias
        $c3->filterByTitle('War%', Criteria::LIKE);

        $sql = $this->getSql('SELECT subquery_2.id, subquery_2.title, subquery_2.isbn, subquery_2.price, subquery_2.publisher_id, subquery_2.author_id FROM (SELECT subquery_1.id, subquery_1.title, subquery_1.isbn, subquery_1.price, subquery_1.publisher_id, subquery_1.author_id FROM (SELECT book.id, book.title, book.isbn, book.price, book.publisher_id, book.author_id FROM book) AS subquery_1 WHERE subquery_1.price<:p2) AS subquery_2 WHERE subquery_2.title LIKE :p1');

        $params = [
            ['table' => 'book', 'column' => 'title', 'value' => 'War%'],
            ['table' => 'book', 'column' => 'price', 'value' => 20],
        ];
        $this->assertCriteriaTranslation($c3, $sql, $params, 'addSubquery() forges a unique alias if none is given');
    }

    public function testErrorOnDuplicatedAlias(): void
    {
        $q = BookQuery::create()->addSubquery(BookQuery::create(), 'foo');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Subquery alias `foo` already exists.');

        $q->addSubquery(BookQuery::create(), 'foo');
    }

    public function testGeneratedAliasResolvesDuplication(): void
    {
        $q = BookQuery::create()
            ->addSubquery(BookQuery::create(), 'subquery_2')
            ->addSubquery(BookQuery::create()) // no alias
        ;

        $subqueries = $q->getSubqueries();
        $this->assertCount(2, $subqueries);
        $this->assertArrayHasKey('subquery_2', $subqueries);
        $this->assertArrayHasKey('subquery_2i', $subqueries);
    }

    /**
     * @return void
     */
    public function testSubQueryWithJoin()
    {
        $c1 = BookQuery::create()
            ->useAuthorQuery()
                ->filterByLastName('Rowling')
            ->endUse();

        $c2 = new BookQuery();
        $c2->addSubquery($c1, 'subQuery');
        $c2->filterByPrice(20, Criteria::LESS_THAN);

        $sql = $this->getSql('SELECT subQuery.id, subQuery.title, subQuery.isbn, subQuery.price, subQuery.publisher_id, subQuery.author_id FROM (SELECT book.id, book.title, book.isbn, book.price, book.publisher_id, book.author_id FROM book LEFT JOIN author ON (book.author_id=author.id) WHERE author.last_name=:p2) AS subQuery WHERE subQuery.price<:p1');

        $params = [
            ['table' => 'book', 'column' => 'price', 'value' => 20],
            ['table' => 'author', 'column' => 'last_name', 'value' => 'Rowling'],
        ];
        $this->assertCriteriaTranslation($c2, $sql, $params, 'addSubquery() can add a select query with a join');
    }

    /**
     * @return void
     */
    public function testSubQueryParameters()
    {
        $subCriteria = new BookQuery();
        $subCriteria->filterByAuthorId(123);

        $c = new BookQuery();
        $c->addSubquery($subCriteria, 'subCriteriaAlias');
        // and use filterByPrice method!
        $c->filterByPrice(20, Criteria::LESS_THAN);

        $sql = $this->getSql('SELECT subCriteriaAlias.id, subCriteriaAlias.title, subCriteriaAlias.isbn, subCriteriaAlias.price, subCriteriaAlias.publisher_id, subCriteriaAlias.author_id FROM (SELECT book.id, book.title, book.isbn, book.price, book.publisher_id, book.author_id FROM book WHERE book.author_id=:p2) AS subCriteriaAlias WHERE subCriteriaAlias.price<:p1');

        $params = [
            ['table' => 'book', 'column' => 'price', 'value' => 20],
            ['table' => 'book', 'column' => 'author_id', 'value' => 123],
        ];
        $this->assertCriteriaTranslation($c, $sql, $params, 'addSubQueryCriteriaInFrom() combines two queries successfully');
    }

    /**
     * @return void
     */
    public function testSubQueryRecursive()
    {
        // sort the books (on date, if equal continue with id), filtered by a publisher
        $sortedBookQuery = new BookQuery();
        $sortedBookQuery->addSelfSelectColumns();
        $sortedBookQuery->filterByPublisherId(123);
        $sortedBookQuery->orderByTitle(Criteria::DESC);
        $sortedBookQuery->orderById(Criteria::DESC);

        // group by author, after sorting!
        $latestBookQuery = new BookQuery();
        $latestBookQuery->addSubquery($sortedBookQuery, 'sortedBookQuery');
        $latestBookQuery->groupBy('sortedBookQuery.AuthorId');

        // filter from these latest books, find the ones cheaper than 12 euro
        $c = new BookQuery();
        $c->addSubquery($latestBookQuery, 'latestBookQuery');
        $c->filterByPrice(12, Criteria::LESS_THAN);

        if ($this->isDb('pgsql')) {
            $sql = $this->getSql('SELECT latestBookQuery.id, latestBookQuery.title, latestBookQuery.isbn, latestBookQuery.price, latestBookQuery.publisher_id, latestBookQuery.author_id ' .
            'FROM (SELECT sortedBookQuery.id, sortedBookQuery.title, sortedBookQuery.isbn, sortedBookQuery.price, sortedBookQuery.publisher_id, sortedBookQuery.author_id FROM ' .
            '(SELECT book.id, book.title, book.isbn, book.price, book.publisher_id, book.author_id FROM book WHERE book.publisher_id=:p2 ORDER BY book.title DESC,book.id DESC) AS sortedBookQuery ' .
            'GROUP BY sortedBookQuery.author_id,sortedBookQuery.id,sortedBookQuery.title,sortedBookQuery.isbn,sortedBookQuery.price,sortedBookQuery.publisher_id) AS latestBookQuery WHERE latestBookQuery.price<:p1');
        } else {
            $sql = $this->getSql('SELECT latestBookQuery.id, latestBookQuery.title, latestBookQuery.isbn, latestBookQuery.price, latestBookQuery.publisher_id, latestBookQuery.author_id ' .
            'FROM (SELECT sortedBookQuery.id, sortedBookQuery.title, sortedBookQuery.isbn, sortedBookQuery.price, sortedBookQuery.publisher_id, sortedBookQuery.author_id ' .
            'FROM (SELECT book.id, book.title, book.isbn, book.price, book.publisher_id, book.author_id ' .
            'FROM book ' .
            'WHERE book.publisher_id=:p2 ' .
            'ORDER BY book.title DESC,book.id DESC) AS sortedBookQuery ' .
            'GROUP BY sortedBookQuery.author_id) AS latestBookQuery ' .
            'WHERE latestBookQuery.price<:p1');
        }

        $params = [
            ['table' => 'book', 'column' => 'price', 'value' => 12],
            ['table' => 'book', 'column' => 'publisher_id', 'value' => 123],
        ];
        $this->assertCriteriaTranslation($c, $sql, $params, 'addSubQueryCriteriaInFrom() combines two queries successfully');
    }

    /**
     * @return void
     */
    public function testSubQueryWithSelectColumns()
    {
        $subCriteria = new BookQuery();

        $c = new BookQuery();
        $c->addSubquery($subCriteria, 'alias1', false);
        $c->select(['alias1.Id']);
        $c->setAutoAddTable(false);
        $c->configureSelectColumns();

        $sql = $this->getSql('SELECT alias1.id AS "alias1.Id" FROM (SELECT book.id, book.title, book.isbn, book.price, book.publisher_id, book.author_id FROM book) AS alias1');

        $params = [];
        $this->assertCriteriaTranslation($c, $sql, $params, 'addSubquery() forges a unique alias and adds select columns by default');
    }

    /**
     * @return void
     */
    public function testSubQueryCount()
    {
        $subCriteria = new BookQuery();

        $c = new BookQuery();
        $c->addSubquery($subCriteria, 'subCriteriaAlias');
        $c->filterByPrice(20, Criteria::LESS_THAN);
        $nbBooks = $c->count();

        $query = Propel::getConnection()->getLastExecutedQuery();

        $sql = $this->getSql('SELECT COUNT(*) FROM (SELECT subCriteriaAlias.id, subCriteriaAlias.title, subCriteriaAlias.isbn, subCriteriaAlias.price, subCriteriaAlias.publisher_id, subCriteriaAlias.author_id FROM (SELECT book.id, book.title, book.isbn, book.price, book.publisher_id, book.author_id FROM book) AS subCriteriaAlias WHERE subCriteriaAlias.price<20) propelmatch4cnt');

        $this->assertEquals($sql, $query, 'addSubquery() doCount is defined as complexQuery');
    }

    /**
     * @return void
     */
    public function testPropelDoesNotAddTableFromSubqueryInSelect()
    {
        $colDef = <<< EOF
CASE WHEN EXISTS(
    SElECT 1
    FRoM author r
    WHERE r.id = book.author_id
)
THEN 1 ELSE 0 END
EOF;
        $p = [];
        $actual = BookQuery::create()->select('id')->addAsColumn('hasAuthor', $colDef)->createSelectSql($p);
        $expected = "SELECT $colDef AS hasAuthor, book.id AS \"id\" FROM book";
        $this->assertEquals($this->getSql($expected), $actual);
    }

    /**
     * @return void
     */
    public function testPropelDoesNotAddTableFromSubqueryInWhere()
    {
        $where = <<< EOF
EXISTS(
    SElECT 1
    FRoM author r
    WHERE r.id = book.author_id
)
THEN 1 ELSE 0 END
EOF;
        $p = [];
        $actual = BookQuery::create()->where($where)->createSelectSql($p);
        $expected = "SELECT  FROM book WHERE $where";
        $this->assertEquals($this->getSql($expected), $actual);
    }
}
