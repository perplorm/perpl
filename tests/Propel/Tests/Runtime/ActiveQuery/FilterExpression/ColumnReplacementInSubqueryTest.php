<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Runtime\ActiveQuery\FilterExpression;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Exception\PropelException;
use Propel\Tests\Bookstore\AuthorQuery;
use Propel\Tests\Bookstore\BookQuery;
use Propel\Tests\Bookstore\ReviewQuery;
use Propel\Tests\TestCaseFixtures;

/**
 */
class ColumnReplacementInSubqueryTest extends TestCaseFixtures
{
    public function ReplaceInSubqueryDataProvider(): array
    {
        return [
            [
                'description' => 'Should resolve columns in PHP form from subquery',
                'inputClause' => 'saut.FirstName = ?',
                'value'  => 'asdf',
                'expectedClause' => 'saut.first_name = :p1',
                'expectedParam' => [['table' => 'author', 'column' => 'first_name', 'value' => 'asdf']],
            ],[
                'description' => 'Should find column type from subquery',
                'inputClause' => 'saut.Age = ?',
                'value'  => 'asdf',
                'expectedClause' => 'saut.age = :p1',
                'expectedParam' => [['table' => 'author', 'column' => 'age', 'value' => 'asdf']],
            ],[
                'description' => 'Should resolve AS columns from subquery',
                'inputClause' => 'saut.MyAsColumn = ?',
                'value'  => 'asdf',
                'expectedClause' => 'saut.MyAsColumn = :p1',
                'expectedParam' => [['table' => 'saut', 'column' => 'MyAsColumn', 'value' => 'asdf']],
            ]
        ];
    }
    /**
     * @dataProvider ReplaceInSubqueryDataProvider
     *
     * @return void
     */
    public function testReplaceInSubquery(string $description, string $clause, $value, string $expectedClause, array $expectedParam)
    {
        $subquery = AuthorQuery::create('aut')
            ->setModelAlias('aut', true)
            ->joinEssayRelatedByFirstAuthorId('ess')
            ->addAsColumn('MyAsColumn', 'author.id') // should be aut.id?
        ;
        $query = BookQuery::create('bok')
            ->select('id')
            ->addSubquery($subquery, 'saut')
            ->where('saut.Id = bok.AuthorId')
            ->where($clause, $value)
        ;

        $expectedSql =  'SELECT book.id AS "id" '.
                        'FROM book, ' .
                        '(' .
                            'SELECT aut.id AS MyAsColumn ' . 
                            'FROM author aut ' .
                            'LEFT JOIN essay ess ON (aut.id=ess.first_author_id)'.
                        ') AS saut '.
                        'WHERE saut.id = book.author_id AND ' . $expectedClause;

        $this->assertCriteriaTranslation($query, $expectedSql, $expectedParam, $description);
    }


    /**
     * @return void
     */
    public function testReplaceInSubqueryWithJoin()
    {
        $this->markTestSkipped('Propel cannot resolve columns in a subquery join at the moment');
        $subquery = AuthorQuery::create('aut')->joinWithEssayRelatedByFirstAuthorId();
        try {
            $c = BookQuery::create('bok')
                ->addSubquery($subquery, 'saut')
                ->where('saut.second_author_id = ?', 'asdf')
            ;
        } catch (PropelException $e) {
            $this->fail('Column in WHERE was not resolved: ' . $e->getTraceAsString());
        }

        $expectedSQL =  'SELECT  '.
                        'FROM book ' .
                        'LEFT JOIN (' .
                            'SELECT  ' .
                            'FROM author aut ' .
                            'LEFT JOIN essay ess ON (aut.id=ess.first_author_id)'.
                        ') saut'.
                        'WHERE saut.second_author_id = :p1';

        $this->assertCriteriaTranslation($c, $expectedSQL);
    }

    /**
     * @param \Propel\Runtime\ActiveQuery\ModelCriteria $criteria
     * @param string $expectedSql
     * @param array $expectedParams
     * @param string $message
     *
     * @return void
     */
    protected function assertCriteriaTranslation(ModelCriteria $criteria, string $expectedSql, array $expectedParams = [], string $message = '')
    {
        $params = [];
        $result = $criteria->createSelectSql($params);

        $this->assertEquals($this->getSql($expectedSql), $result, $message);
        $this->assertEqualsCanonicalizing($expectedParams, $params);
    }

    /**
     * @return void
     */
    public function testReplaceMainQueryColumnsInFilterSubquery()
    {
        $subquery = AuthorQuery::create('a')->where('b.AuthorId = a.FirstName');
        $c = BookQuery::create('b')->add(null, $subquery, '<');

        $expectedSql = "SELECT  FROM book WHERE < (SELECT  FROM author WHERE book.author_id = author.first_name)";

        $params = [];
        $this->assertCriteriaTranslation($c, $expectedSql, $params);
    }

    /**
     * @return void
     */
    public function testReplaceJoinQueryColumnsInFilterSubquery()
    {
        $subquery = AuthorQuery::create('a')->where('r.ReviewedBy = a.FirstName');
        $c = BookQuery::create('b')
            ->joinReview('r')
            ->add(null, $subquery, '>');

        $expectedSql = "SELECT  FROM book LEFT JOIN review r ON (book.id=r.book_id) WHERE > (SELECT  FROM author WHERE r.reviewed_by = author.first_name)";

        $params = [];
        $this->assertCriteriaTranslation($c, $expectedSql, $params, '');
    }

    /**
     * @return void
     */
    public function testReplaceUseQueryColumnsAreInFilterSubquery()
    {
        // access a column in subquery that was added through a join in a subcriteria (useQuery)
        // book <-> author <-> essay
        //  ^-> review
        $subquery = ReviewQuery::create('rev')->where('rev.ReviewedBy = ess.SecondAuthorId');
        $c = BookQuery::create('bok')
            ->useAuthorQuery('aut')
                ->joinEssayRelatedByFirstAuthorId('ess')
            ->endUse()
            ->add(null, $subquery, '<');

        $expectedSql =  'SELECT  FROM book ' .
                        'LEFT JOIN author aut ON (book.author_id=aut.id) ' .
                        'LEFT JOIN essay ess ON (aut.id=ess.first_author_id) '.
                        'WHERE < (' .
                            'SELECT  ' . 
                            'FROM review '.
                            'WHERE review.reviewed_by = ess.second_author_id'.
                        ')';

        $params = [];
        $this->assertCriteriaTranslation($c, $expectedSql, $params, '');
    }



    /**
     * @return void
     */
    public function testUseColumnNotInSubquerySelectGivesException()
    {
        $this->markTestIncomplete('Propel cannot check against output columns at the moment.');

        $subquery = BookQuery::create('bok')
            ->select(['title']);

        $joinCondition = 'Author.Id = bok.AuthorId'; // AuthorId is not in select
        
        $authorQuery = AuthorQuery::create()
        ->addSubquery($subquery, 'sub', false)
        ->where($joinCondition);

        $this->expectException(\Exception::class);
        $params = [];
        $authorQuery->configureSelectColumns();
        $query = $authorQuery->createSelectSql($params);
    }

}
