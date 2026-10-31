<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Runtime\ActiveQuery;

use Propel\Runtime\ActiveQuery\RelationPopulator;
use Propel\Tests\Bookstore\AuthorQuery;
use Propel\Tests\Bookstore\BookQuery;
use Propel\Tests\Bookstore\BookReaderQuery;
use Propel\Tests\Bookstore\BookstoreEmployeeQuery;
use Propel\Tests\Bookstore\BookSummaryQuery;
use Propel\Tests\Bookstore\ReviewQuery;
use Propel\Tests\TestCaseFixtures;

/**
 */
class RelationPopulatorTest extends TestCaseFixtures
{
    /**
     * @return void
     */
    public function testModelNameManyToOne()
    {
        $q = BookQuery::create()
            ->joinAuthor();
        $joins = $q->getJoins();
        $join = $joins['Author'];
        $with = new RelationPopulator($join);
        $this->assertEquals('Propel\Tests\Bookstore\Author', $with->getModelName(), 'A ModelWith computes the model name from the join');
    }

    /**
     * @return void
     */
    public function testModelNameOneToMany()
    {
        $q = AuthorQuery::create()
            ->joinBook();
        $joins = $q->getJoins();
        $join = $joins['Book'];
        $with = new RelationPopulator($join);
        $this->assertEquals('Propel\Tests\Bookstore\Book', $with->getModelName(), 'A ModelWith computes the model name from the join');
    }

    /**
     * @return void
     */
    public function testModelNameAlias()
    {
        $q = BookQuery::create()
            ->joinAuthor('a');
        $joins = $q->getJoins();
        $join = $joins['a'];
        $with = new RelationPopulator($join);
        $this->assertEquals('Propel\Tests\Bookstore\Author', $with->getModelName(), 'A ModelWith computes the model name from the join');
    }

    /**
     * @return void
     */
    public function testRelationManyToOne()
    {
        $q = BookQuery::create()
            ->joinAuthor();
        $joins = $q->getJoins();
        $join = $joins['Author'];
        $with = new RelationPopulator($join);
        $this->assertEquals($this->getObjectPropertyValue($with, 'populateRelationMethod'), 'setAuthor', 'A ModelWith computes the relation method from the join');
        $this->assertEquals($with->getRelationName(), 'Author', 'A ModelWith computes the relation name from the join');
        $this->assertFalse($with->populatesListOnTarget(), 'A ModelWith computes the relation cardinality from the join');
    }

    /**
     * @return void
     */
    public function testRelationOneToMany()
    {
        $q = AuthorQuery::create()
            ->joinBook();
        $joins = $q->getJoins();
        $join = $joins['Book'];
        $with = new RelationPopulator($join);
        $this->assertEquals($this->getObjectPropertyValue($with, 'populateRelationMethod'), 'addBook', 'A ModelWith computes the relation method from the join');
        $this->assertEquals($with->getRelationName(), 'Books', 'A ModelWith computes the relation name from the join');
        $this->assertTrue($with->populatesListOnTarget(), 'A ModelWith computes the relation cardinality from the join');
    }

    /**
     * @return void
     */
    public function testRelationOneToOne()
    {
        $q = BookstoreEmployeeQuery::create()
            ->joinBookstoreEmployeeAccount();
        $joins = $q->getJoins();
        $join = $joins['BookstoreEmployeeAccount'];
        $with = new RelationPopulator($join);
        $this->assertEquals($this->getObjectPropertyValue($with, 'populateRelationMethod'), 'setBookstoreEmployeeAccount', 'A ModelWith computes the relation method from the join');
        $this->assertEquals($with->getRelationName(), 'BookstoreEmployeeAccount', 'A ModelWith computes the relation name from the join');
        $this->assertFalse($with->populatesListOnTarget(), 'A ModelWith computes the relation cardinality from the join');
    }

    /**
     * @return void
     */
    public function testIsPrimary()
    {
        $q = AuthorQuery::create()
            ->joinBook();
        $joins = $q->getJoins();
        $join = $joins['Book'];
        $with = new RelationPopulator($join);
        $this->assertTrue($with->joinsToMainModel(), 'A ModelWith initialized from a primary join is primary');

        $q = BookQuery::create()
            ->joinAuthor()
            ->joinReview();
        $joins = $q->getJoins();
        $join = $joins['Review'];
        $with = new RelationPopulator($join);
        $this->assertTrue($with->joinsToMainModel(), 'A ModelWith initialized from a primary join is primary');

        $q = AuthorQuery::create()
            ->join('Propel\Tests\Bookstore\Author.Book')
            ->join('Book.Publisher');
        $joins = $q->getJoins();
        $join = $joins['Publisher'];
        $with = new RelationPopulator($join);
        $this->assertFalse($with->joinsToMainModel(), 'A ModelWith initialized from a non-primary join is not primary');
    }

    /**
     * @return void
     */
    public function testGetParentJoinIdentifier()
    {
        $q = AuthorQuery::create()
            ->joinBook();
        $joins = $q->getJoins();
        $join = $joins['Book'];
        $with = new RelationPopulator($join);
        $this->assertNull($with->getParentJoinIdentifier(), 'A ModelWith initialized from a primary join has a null left phpName');

        $q = AuthorQuery::create('a')
            ->joinBook();
        $joins = $q->getJoins();
        $join = $joins['Book'];
        $with = new RelationPopulator($join);
        $this->assertNull($with->getParentJoinIdentifier(), 'A ModelWith initialized from a primary join with alias has a null left phpName');

        $q = AuthorQuery::create()
            ->joinBook('b');
        $joins = $q->getJoins();
        $join = $joins['b'];
        $with = new RelationPopulator($join);
        $this->assertNull($with->getParentJoinIdentifier(), 'A ModelWith initialized from a primary join with alias has a null left phpName');

        $q = AuthorQuery::create()
            ->join('Propel\Tests\Bookstore\Author.Book')
            ->join('Book.Publisher');
        $joins = $q->getJoins();
        $join = $joins['Publisher'];
        $with = new RelationPopulator($join);
        $this->assertEquals('book', $with->getParentJoinIdentifier(), 'A ModelWith uses the previous join relation name as left phpName');

        $q = ReviewQuery::create()
            ->join('Propel\Tests\Bookstore\Review.Book')
            ->join('Book.Author')
            ->join('Book.Publisher');
        $joins = $q->getJoins();
        $join = $joins['Publisher'];
        $with = new RelationPopulator($join);
        $this->assertEquals('book', $with->getParentJoinIdentifier(), 'A ModelWith uses the previous join relation name as left phpName');

        $q = ReviewQuery::create()
            ->join('Propel\Tests\Bookstore\Review.Book')
            ->join('Book.BookOpinion')
            ->join('BookOpinion.BookReader');
        $joins = $q->getJoins();
        $join = $joins['BookOpinion'];
        $with = new RelationPopulator($join);
        $this->assertEquals('book', $with->getParentJoinIdentifier(), 'A ModelWith uses the previous join relation name as left phpName');
        $join = $joins['BookReader'];
        $with = new RelationPopulator($join);
        $this->assertEquals('book_opinion', $with->getParentJoinIdentifier(), 'A ModelWith uses the previous join relation name as left phpName');

        $q = BookReaderQuery::create()
            ->join('Propel\Tests\Bookstore\BookReader.BookOpinion')
            ->join('BookOpinion.Book')
            ->join('Book.Author');
        $joins = $q->getJoins();
        $join = $joins['Book'];
        $with = new RelationPopulator($join);
        $this->assertEquals('book_opinion', $with->getParentJoinIdentifier(), 'A ModelWith uses the previous join relation name as related class');
        $join = $joins['Author'];
        $with = new RelationPopulator($join);
        $this->assertEquals('book', $with->getParentJoinIdentifier(), 'A ModelWith uses the previous join relation name as left phpName');

        $q = BookSummaryQuery::create()
            ->join('Propel\Tests\Bookstore\BookSummary.SummarizedBook')
            ->join('SummarizedBook.Author');
        $joins = $q->getJoins();
        $join = $joins['Author'];
        $with = new RelationPopulator($join);
        $this->assertEquals('book', $with->getParentJoinIdentifier(), 'A ModelWith uses the previous join relation name as left phpName');
    }

    /**
     * @return void
     */
    public function testGetJoinIdentifier()
    {
        $join = AuthorQuery::create()
            ->joinBook()
            ->getJoin('Book');
        $populator = new RelationPopulator($join);
        $this->assertEquals('book', $populator->getJoinIdentifier(), 'A ModelWith initialized from a primary join has a right phpName');

        $join = AuthorQuery::create('a')
            ->joinBook()
            ->getJoin('Book');
        $populator = new RelationPopulator($join);
        $this->assertEquals('book', $populator->getJoinIdentifier(), 'A ModelWith initialized from a primary join with alias has a right phpName');

        $join = AuthorQuery::create()
            ->joinBook('b')
            ->getJoin('b');
        $populator = new RelationPopulator($join);
        $this->assertEquals('b', $populator->getJoinIdentifier(), 'A ModelWith initialized from a primary join with alias uses the alias as right phpName');

        $join = AuthorQuery::create()
            ->join('Propel\Tests\Bookstore\Author.Book')
            ->join('Book.Publisher')
            ->getJoin('Publisher');
        $populator = new RelationPopulator($join);
        $this->assertEquals('publisher', $populator->getJoinIdentifier(), 'A ModelWith has a right phpName even when there are previous joins');

        $join = BookSummaryQuery::create()
            ->join('Propel\Tests\Bookstore\BookSummary.SummarizedBook')
            ->getJoin('SummarizedBook');
        $populator = new RelationPopulator($join);
        $this->assertEquals('book', $populator->getJoinIdentifier(), 'A ModelWith uses the relation name rather than the class phpName when it exists');

        $join = BookSummaryQuery::create()
            ->join('Propel\Tests\Bookstore\BookSummary.SummarizedBook')
            ->join('SummarizedBook.Author')
            ->getJoin('Author');
        $populator = new RelationPopulator($join);
        $this->assertEquals('author', $populator->getJoinIdentifier(), 'A ModelWith has a right phpName even when there are previous joins with custom relation names');
    }

    /**
     * @return void
     */
    public function testGetNameInQuery()
    {
        $join = AuthorQuery::create()
            ->joinBook()
            ->getJoin('Book');
        $populator = new RelationPopulator($join);
        $this->assertEquals('book', $populator->getNameInQuery(), 'should use table name');

        $join = AuthorQuery::create()
            ->joinBook('b')
            ->getJoin('b');
        $populator = new RelationPopulator($join);
        $this->assertEquals('b', $populator->getNameInQuery(), 'should use alias');

        $join = AuthorQuery::create()
            ->join('Propel\Tests\Bookstore\Author.Book')
            ->join('Book.Publisher')
            ->getJoin('Publisher');
        $populator = new RelationPopulator($join);
        $this->assertEquals('publisher', $populator->getNameInQuery(), 'should resolve table name across relations');

        $join = BookSummaryQuery::create()
            ->join('Propel\Tests\Bookstore\BookSummary.SummarizedBook')
            ->getJoin('SummarizedBook');
        $populator = new RelationPopulator($join);
        $this->assertEquals('book', $populator->getNameInQuery(), 'should use table name over relation name');

        $join = BookSummaryQuery::create()
            ->join('Propel\Tests\Bookstore\BookSummary.SummarizedBook s')
            ->getJoin('s');
        $populator = new RelationPopulator($join);
        $this->assertEquals('s', $populator->getNameInQuery(), 'should use alias from relation specifier');
    }

    public function testJoinIdentifiersMatch()
    {
        $joins = AuthorQuery::create()
            ->join('Propel\Tests\Bookstore\Author.Book')
            ->join('Book.Publisher')
            ->getJoins();

        $parentPopulator = new RelationPopulator($joins['Book']);
        $childPopulator = new RelationPopulator($joins['Publisher']);

        $this->assertSame($parentPopulator->getJoinIdentifier(), $childPopulator->getParentJoinIdentifier());

    }
}
