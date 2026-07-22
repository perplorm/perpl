<?php

declare(strict_types = 1);

namespace Propel\Tests\Runtime\ActiveQuery;

use PHPUnit\Framework\Attributes\DataProvider;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Exception\UnknownRelationException;
use Propel\Runtime\Map\TableMap;
use Propel\Tests\Bookstore\AuthorQuery;
use Propel\Tests\Bookstore\BookQuery;
use Propel\Tests\Bookstore\Map\AuthorTableMap;
use Propel\Tests\Bookstore\Map\BookTableMap;
use Propel\Tests\Bookstore\Map\PublisherTableMap;
use Propel\Tests\Bookstore\Map\ReviewTableMap;
use Propel\Tests\Bookstore\ReviewQuery;
use Propel\Tests\Helpers\Bookstore\BookstoreDataPopulator;
use Propel\Tests\Helpers\Bookstore\BookstoreTestBase;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;

/**
 */
class ModelCriteriaPopulateRelationTest extends BookstoreTestBase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        BookstoreDataPopulator::depopulate();
        BookstoreDataPopulator::populate();
    }
    /**
     * @return void
     */
    public function testWith()
    {
        $c = BookQuery::create();
        $c->join('Propel\Tests\Bookstore\Book.Author');
        $c->populateJoinedRelation('Author');
        $withs = $c->getRelatedModelsToPopulate();
        $this->assertTrue(array_key_exists('Author', $withs), 'with() adds an entry to the internal list of Withs');
        $this->assertInstanceOf('Propel\Runtime\ActiveQuery\ModelWith', $withs['Author'], 'with() references the ModelWith object');
    }

    /**
     * @return void
     */
    public function testWithThrowsExceptionWhenJoinLacks()
    {
        $this->expectException(UnknownRelationException::class);

        $c = BookQuery::create();
        $c->populateJoinedRelation('Propel\Tests\Bookstore\Author');
    }

    /**
     * @return void
     */
    public function testWithAlias()
    {
        $c = BookQuery::create();
        $c->join('Propel\Tests\Bookstore\Book.Author a');
        $c->populateJoinedRelation('a');
        $withs = $c->getRelatedModelsToPopulate();
        $this->assertTrue(array_key_exists('a', $withs), 'with() uses the alias for the index of the internal list of Withs');
    }

    /**
     * @return void
     */
    public function testWithThrowsExceptionWhenNotUsingAlias()
    {
        $this->expectException(UnknownRelationException::class);

        $c = BookQuery::create();
        $c->join('Propel\Tests\Bookstore\Book.Author a');
        $c->populateJoinedRelation('Propel\Tests\Bookstore\Author');
    }

    /**
     * @return void
     */
    public function testWithAddsSelectColumns()
    {
        $c = BookQuery::create();
        $c->join('Propel\Tests\Bookstore\Book.Author');
        $c->populateJoinedRelation('Author');
        $expectedColumns = $this->buildFieldNamesFromTableMap(BookTableMap::class, AuthorTableMap::class);

        $this->assertEquals($expectedColumns, $c->getSelectColumns(), 'with() adds the columns of the related table');
    }

    /**
     * @return void
     */
    public function testWithAliasAddsSelectColumns()
    {
        $c = BookQuery::create();
        $c->join('Propel\Tests\Bookstore\Book.Author a');
        $c->populateJoinedRelation('a');
        $expectedColumns = [
            ...$this->buildFieldNamesFromTableMap(BookTableMap::class),
            'a.id',
            'a.first_name',
            'a.last_name',
            'a.email',
            'a.age',
        ];
        $this->assertEquals($expectedColumns, $c->getSelectColumns(), 'with() adds the columns of the related table');
    }

    /**
     * @return void
     */
    public function testPopulateRelationAlias()
    {
        $c = BookQuery::create();
        $c->populateRelation('Propel\Tests\Bookstore\Book.Author a');
        $expectedColumns = [
            ...$this->buildFieldNamesFromTableMap(BookTableMap::class),
            'a.id',
            'a.first_name',
            'a.last_name',
            'a.email',
            'a.age',
        ];
        $this->assertEquals($expectedColumns, $c->getSelectColumns(), 'populateRelation() adds the join with the alias');
    }

    /**
     * @return void
     */
    public function testWithAliasAddsSelectColumnsOfMainTable()
    {
        $c = BookQuery::create();
        $c->setModelAlias('b', true);
        $c->join('b.Author a');
        $c->populateJoinedRelation('a');
        $expectedColumns = [
            'b.id',
            'b.title',
            'b.isbn',
            'b.price',
            'b.publisher_id',
            'b.author_id',
            'a.id',
            'a.first_name',
            'a.last_name',
            'a.email',
            'a.age',
        ];
        $this->assertEquals($expectedColumns, $c->getSelectColumns(), 'with() adds the columns of the main table with an alias if required');
    }

    /**
     * @return void
     */
    public function testWithOneToManyAddsSelectColumns()
    {
        $c = AuthorQuery::create();
        $c->leftJoin('Propel\Tests\Bookstore\Author.Book');
        $c->populateJoinedRelation('Book');
        $expectedColumns = $this->buildFieldNamesFromTableMap(AuthorTableMap::class, BookTableMap::class);

        $this->assertEquals($expectedColumns, $c->getSelectColumns(), 'with() adds the columns of the related table even in a one-to-many relationship');
    }

    public static function PopulateDataProvider(): array
    {
        return [
            ['joinWith', Criteria::INNER_JOIN],
            ['populateRelation', Criteria::LEFT_JOIN],
        ];
    }

    /**
     * @return void
     */
    #[DataProvider('PopulateDataProvider')]
    public function testPopulateRelation(string $populateFunctionName, string $expectedJoinType)
    {
        $c = BookQuery::create();
        $c->$populateFunctionName('Propel\Tests\Bookstore\Book.Author');
        $expectedColumns = $this->buildFieldNamesFromTableMap(BookTableMap::class, AuthorTableMap::class);

        $this->assertEquals($expectedColumns, $c->getSelectColumns(), "$populateFunctionName() adds the join");
        $joins = $c->getJoins();
        $join = $joins['Author'];
        $this->assertEquals($expectedJoinType, $join->getJoinType(), "$populateFunctionName() adds an $expectedJoinType by default");
    }

    /**
     * @return void
     */
    public function testPopulateRelationType()
    {
        $c = BookQuery::create();
        $c->populateRelation('Propel\Tests\Bookstore\Book.Author', Criteria::LEFT_JOIN);
        $joins = $c->getJoins();
        $join = $joins['Author'];
        $this->assertEquals(Criteria::LEFT_JOIN, $join->getJoinType(), 'populateRelation() accepts a join type as second parameter');
    }

    /**
     * @return void
     */
    public function testPopulateRelationSeveral()
    {
        $c = ReviewQuery::create();
        $c->populateRelation('Review.Book');
        $c->populateRelation('Propel\Tests\Bookstore\Book.Author');
        $c->populateRelation('Book.Publisher');
        $expectedColumns = $this->buildFieldNamesFromTableMap(ReviewTableMap::class, BookTableMap::class, AuthorTableMap::class, PublisherTableMap::class);
        
        $this->assertEquals($expectedColumns, $c->getSelectColumns(), 'populateRelation() adds the with');
        $joins = $c->getJoins();
        $expectedJoinKeys = ['Book', 'Author', 'Publisher'];
        $this->assertEquals($expectedJoinKeys, array_keys($joins), 'populateRelation() adds the join');
    }

    /**
     * @return void
     */
    public function testPopulateRelationTwice()
    {
        $c = BookQuery::create();
        $c->join('Propel\Tests\Bookstore\Book.Review');
        $c->populateRelation('Propel\Tests\Bookstore\Book.Author');
        $c->populateRelation('Propel\Tests\Bookstore\Book.Review');
        $expectedColumns = $this->buildFieldNamesFromTableMap(BookTableMap::class, AuthorTableMap::class, ReviewTableMap::class);

        $this->assertEquals($expectedColumns, $c->getSelectColumns(), 'populateRelation() adds the with');
        $joins = $c->getJoins();
        $expectedJoinKeys = ['Review', 'Author'];
        $this->assertEquals($expectedJoinKeys, array_keys($joins), 'populateRelation() adds the join');
    }

    protected function buildFieldNamesFromTableMap(string ...$tableMaps)
    {
        return array_merge(...array_map(fn (string $t) => array_map(fn ($name) => $t::TABLE_NAME. ".$name", $t::getFieldNames(TableMap::TYPE_FIELDNAME)), $tableMaps));        
    }

    /**
     * @group database
     * 
     * @return void
     */
    public function testPopulateWithVirtualColumns()
    {
        AuthorTableMap::clearInstancePool();

        $author = AuthorQuery::create()
            ->populateBook()
            ->addAsColumn('leAuthorId', 'author.id')
            ->filterByLastName('Rowling')
            ->find()->get(0);

        $this->assertSame(['leAuthorId' => $author->getId()], $author->getVirtualColumns());

        $books = $this->getObjectPropertyValue($author, 'collBooks');
        $this->assertCount(1, $books);
    }
}
