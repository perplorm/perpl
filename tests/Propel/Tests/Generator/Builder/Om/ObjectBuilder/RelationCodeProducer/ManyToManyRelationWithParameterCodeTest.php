<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Builder\Om\RelationCodeProducer;

use Propel\Generator\Builder\Om\ObjectBuilder\RelationCodeProducer\TernaryRelationCodeProducer;

/**
 */
class ManyToManyRelationWithParameterCodeTest extends AbstractManyToManyCodeTest
{
    /**
     * @return void
     */
    public function testType(): void
    {
        $this->assertInstanceOf(TernaryRelationCodeProducer::class, $this->getCodeProducer());
    }

    /**
     * @return void
     */
    public function testAttributes()
    {
        $expected = '
    /**
     * Objects associated via TeamDayType relation (n:m).
     */
    protected ObjectCombinationCollection|null $combinationTeamDayTypes = null;

    /**
     * If $combinationTeamDayTypes contains all objects in TeamDayType relation.
     */
    protected bool $combinationTeamDayTypesIsPartial = false;
';
        $this->assertProducedCodeMatches('addAttributes', $expected);
    }

    /**
     * @return void
     */
    public function testDeletionAttributes()
    {
        $expected = '
    /**
     * Items of TeamDayType relation marked for deletion.
     */
    protected ObjectCombinationCollection|null $teamDayTypesScheduledForDeletion = null;
';
        $this->assertProducedCodeMatches('addScheduledForDeletionAttribute', $expected);
    }


    /**
     * @return void
     */
    public function testInit()
    {
        $expected = '
    /**
     * Initializes the combinationTeamDayTypes crossRef collection.
     *
     * By default this just sets the combinationTeamDayTypes collection to an empty collection (like clearTeamDayTypes());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @return void
     */
    public function initTeamDayTypes(): void
    {
        $this->combinationTeamDayTypes = new ObjectCombinationCollection();
        $this->combinationTeamDayTypesIsPartial = true;
    }
';
        $this->assertProducedCodeMatches('addInit', $expected);
    }


    /**
     * @return void
     */
    public function testIsLoaded()
    {
        $expected = '
    /**
     * Checks if the combinationTeamDayTypes collection is loaded.
     *
     * @return bool
     */
    public function isTeamDayTypesLoaded(): bool
    {
        return $this->combinationTeamDayTypes !== null;
    }
';
        $this->assertProducedCodeMatches('addisLoaded', $expected);
    }


    /**
     * @return void
     */
    public function testClear()
    {
        $expected = '
    /**
     * Clears out the combinationTeamDayTypes collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     */
    public function clearTeamDayTypes(): void
    {
        $this->combinationTeamDayTypes = null; // important to set this to NULL since that means it is uninitialized
    }
';
        $this->assertProducedCodeMatches('addClear', $expected);
    }

    /**
     * @return void
     */
    public function testOnReloadCode()
    {
        $expected = '
            $this->combinationTeamDayTypes = null;';

        $this->assertProducedCodeMatches('addOnReloadCode', $expected);
    }

    /**
     * @return void
     */
    public function testDeleteScheduledItemsCode()
    {
        $expected = '
        if ($this->teamDayTypesScheduledForDeletion !== null && !$this->teamDayTypesScheduledForDeletion->isEmpty()) {
            $pks = [];
            foreach ($this->teamDayTypesScheduledForDeletion as $combination) {
                $entryPk = [];

                $entryPk[2] = $this->getId();
                $entryPk[3] = $combination[0]->getId();
                $entryPk[0] = $combination[1];
                $entryPk[1] = $combination[2];

                $pks[] = $entryPk;
            }

            ChildTeamUserQuery::create()
                ->filterByPrimaryKeys($pks)
                ->delete($con);

            $this->teamDayTypesScheduledForDeletion = null;
        }

        if ($this->combinationTeamDayTypes !== null) {
            foreach ($this->combinationTeamDayTypes as $combination) {
                $model = $combination[0];
                if (!$model->isDeleted() && ($model->isNew() || $model->isModified())) {
                    $model->save($con);
                }

            }
        }
';
        $this->assertProducedCodeMatches('addDeleteScheduledItemsCode', $expected);
    }

    /**
     * @return void
     */
    public function testCreateQuery()
    {
        $expected = '
    /**
     * Returns a new query object pre configured with filters from current object and given arguments to query the database.
     *
     * @param string|null $day
     * @param int|null $type
     * @param \Propel\Runtime\ActiveQuery\Criteria|null $criteria
     *
     * @return ChildTeamQuery
     */
    public function createTeamsQuery(?string $day = null, ?int $type = null, ?Criteria $criteria = null): ChildTeamQuery
    {
        $query = ChildTeamQuery::create($criteria)
            ->filterByUser($this);

        $teamUserQuery = $query->useTeamUserQuery();

        if ($day !== null) {
            $teamUserQuery->filterByDay($day);
        }

        if ($type !== null) {
            $teamUserQuery->filterByType($type);
        }

        $teamUserQuery->endUse();

        return $query;
    }
';
        $this->assertProducedCodeMatches('addCreateQuery', $expected);
    }

    /**
     * @return void
     */
    public function testReserveNamesForGetters()
    {
        $reservedNames = $this->getCodeProducer()->reserveNamesForGetters();
        $expected = ['TeamDayType', 'Team'];
        $this->assertEqualsCanonicalizing($expected, $reservedNames);
    }

    /**
     * @return void
     */
    public function testGet()
    {
        $expected = '
    /**
     * Gets a combined collection of array{\Base\Team, string, int} objects related by a many-to-many relationship
     * to the current object by way of the team_user cross-reference table.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this User is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param \Propel\Runtime\ActiveQuery\Criteria|null $criteria Optional query object to filter the query
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con Optional connection object
     *
     * @return \Propel\Runtime\Collection\ObjectCombinationCollection<array{\Base\Team, string, int}>
     */
    public function getTeamDayTypes(?Criteria $criteria = null, ?ConnectionInterface $con = null): ObjectCombinationCollection
    {
        $partial = $this->combinationTeamDayTypesIsPartial && !$this->isNew();
        if ($this->combinationTeamDayTypes !== null && !$partial && !$criteria) {
            return $this->combinationTeamDayTypes;
        }

        if ($this->isNew()) {
            // return empty collection
            if ($this->combinationTeamDayTypes === null) {
                $this->initTeamDayTypes();
            }

            return $this->combinationTeamDayTypes;
        }

        $query = ChildTeamUserQuery::create(null, $criteria)
            ->filterByUser($this)
            ->joinTeam()
            ;

        $items = $query->find($con);
        $combinationTeamDayTypes = new ObjectCombinationCollection();
        foreach ($items as $item) {
            $combination = [];

            $combination[] = $item->getTeam();
            $combination[] = $item->getDay();
            $combination[] = $item->getType();

            $combinationTeamDayTypes[] = $combination;
        }

        if ($criteria) {
            return $combinationTeamDayTypes;
        }

        if ($partial && $this->combinationTeamDayTypes) {
            //make sure that already added objects gets added to the list of the database.
            foreach ($this->combinationTeamDayTypes as $obj) {
                if (!$combinationTeamDayTypes->contains($obj)) {
                    $combinationTeamDayTypes[] = $obj;
                }
            }
        }

        $this->combinationTeamDayTypes = $combinationTeamDayTypes;
        $this->combinationTeamDayTypesIsPartial = false;

        return $this->combinationTeamDayTypes;
    }

    /**
     * Returns a not cached ObjectCollection of Team objects. This will hit always the databases.
     * If you have attached new Team object to this object you need to call `save` first to get
     * the correct return value. Use getTeamDayTypes() to get the current internal state.
     *
     * @param string|null $day
     * @param int|null $type
     * @param \Propel\Runtime\ActiveQuery\Criteria|null $criteria
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con
     *
     * @return \Base\Collection\TeamCollection
     */
    public function getTeams(?string $day = null, ?int $type = null, ?Criteria $criteria = null, ?ConnectionInterface $con = null)
    {
        return $this->createTeamsQuery($day, $type, $criteria)->find($con);
    }
';
        $this->assertProducedCodeMatches('addGetters', $expected);
    }

    /**
     * @return void
     */
    public function testSet()
    {
        $expected = '
    /**
     * Sets a collection of TeamDayType objects related by a many-to-many relationship
     * to the current object by way of the team_user cross-reference table.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param \Propel\Runtime\Collection\Collection<array{\Base\Team, string, int}> $teamDayTypes A Propel collection.
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con Optional connection object
     *
     * @return static
     */
    public function setTeamDayTypes(Collection $teamDayTypes, ?ConnectionInterface $con = null): static
    {
        $this->clearTeamDayTypes();
        $currentTeamDayTypes = $this->getTeamDayTypes();

        $teamDayTypesScheduledForDeletion = $currentTeamDayTypes->diff($teamDayTypes);

        foreach ($teamDayTypesScheduledForDeletion as $toDelete) {
            $this->removeTeamDayType(...$toDelete);
        }

        foreach ($teamDayTypes as $teamDayType) {
            if (!$currentTeamDayTypes->contains($teamDayType)) {
                $this->doAddTeamDayType(...$teamDayType);
            }
        }

        $this->combinationTeamDayTypesIsPartial = false;
        $this->combinationTeamDayTypes = $teamDayTypes instanceof ObjectCombinationCollection
            ? $teamDayTypes : new ObjectCombinationCollection($teamDayTypes->getData());

        return $this;
    }
';
        $this->assertProducedCodeMatches('addSetters', $expected);
    }

    /**
     * @return void
     */
    public function testCount()
    {
        $expected = '
    /**
     * Gets the number of TeamDayType objects related by a many-to-many relationship
     * to the current object by way of the team_user cross-reference table.
     *
     * @param \Propel\Runtime\ActiveQuery\Criteria|null $criteria Optional query object to filter the query
     * @param bool $distinct Set to true to force count distinct
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con Optional connection object
     *
     * @return int The number of related TeamDayType objects
     */
    public function countTeamDayTypes(?Criteria $criteria = null, bool $distinct = false, ?ConnectionInterface $con = null): int
    {
        $partial = $this->combinationTeamDayTypesIsPartial && !$this->isNew();
        if ($this->combinationTeamDayTypes && !$criteria && !$partial) {
            return count($this->combinationTeamDayTypes);
        }

        if ($this->isNew() && $this->combinationTeamDayTypes === null) {
            return 0;
        }

        if ($partial && !$criteria) {
            return count($this->getTeamDayTypes());
        }

        $query = ChildTeamQuery::create(null, $criteria);
        if ($distinct) {
            $query->distinct();
        }

        return $query
            ->filterByUser($this)
            ->count($con);
    }

    /**
     * Returns the not cached count of Team objects. This will hit always the databases.
     * If you have attached new Team object to this object you need to call `save` first to get
     * the correct return value. Use getTeamDayTypes() to get the current internal state.
     *
     * @param string|null $day
     * @param int|null $type
     * @param \Propel\Runtime\ActiveQuery\Criteria|null $criteria
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con
     *
     * @return int
     */
    public function countTeams(?string $day = null, ?int $type = null, ?Criteria $criteria = null, ?ConnectionInterface $con = null): int
    {
        return $this->createTeamsQuery($day, $type, $criteria)->count($con);
    }
';
        $this->assertProducedCodeMatches('addCount', $expected);
    }

    /**
     * @return void
     */
    public function testAdd()
    {
        $expected = '
    /**
     * Associate a Team with this object through the team_user cross reference table.
     *
     * @param \Base\Team $team
     * @param string $day
     * @param int $type
     *
     * @return static
     */
    public function addTeam(Team $team, string $day, int $type): static
    {
        if ($this->combinationTeamDayTypes === null) {
            $this->initTeamDayTypes();
        }

        if (!$this->getTeamDayTypes()->contains([$team, $day, $type])) {
            // only add it if the **same** object is not already associated
            $this->combinationTeamDayTypes->push([$team, $day, $type]);
            $this->doAddTeamDayType($team, $day, $type);
        }

        return $this;
    }
';
        $this->assertProducedCodeMatches('addAdders', $expected);
    }

    /**
     * @return void
     */
    public function testDoAdd()
    {
        $expected = '
    /**
     * @param \Base\Team $team
     * @param string $day
     * @param int $type
     *
     * return void
     */
    protected function doAddTeamDayType(Team $team, string $day, int $type): void
    {
        $teamUser = new ChildTeamUser();
        $teamUser->setTeam($team);
        $teamUser->setDay($day);
        $teamUser->setType($type);
        $teamUser->setUser($this);

        $this->addTeamUser($teamUser);

        // set the back reference to this object directly as using provided method either results
        // in endless loop or in multiple relations
        $userDayTypesEntry = [$this, $day, $type];
        if ($team->isUserDayTypesLoaded()) {
            $team->getUserDayTypes()->push($userDayTypesEntry);
        } elseif (!$team->getUserDayTypes()->contains($userDayTypesEntry)) {
            $team->initUserDayTypes();
            $team->getUserDayTypes()->push($userDayTypesEntry);
        }
    }
';
        $this->assertProducedCodeMatches('buildDoAdd', $expected);
    }

    /**
     * @return void
     */
    public function testRemove()
    {
        $expected = '
    /**
     * Remove team, day, type of this object through the team_user cross reference table.
     *
     * @param \Base\Team $team
     * @param string $day
     * @param int $type
     *
     * @return static
     */
    public function removeTeamDayType(Team $team, string $day, int $type): static
    {
        if (!$this->getTeamDayTypes()->contains([$team, $day, $type])) {
            return $this;
        }

        $teamUser = new ChildTeamUser();
        $teamUser->setTeam($team);
        if ($team->isUserDayTypesLoaded()) {
            //remove the back reference if available
            $team->getUserDayTypes()->removeObject([$this, $day, $type]);
        }

        $teamUser->setDay($day);
        $teamUser->setType($type);
        $teamUser->setUser($this);
        $this->removeTeamUser(clone $teamUser);
        $teamUser->clear();

        $this->combinationTeamDayTypes->remove($this->combinationTeamDayTypes->search([$team, $day, $type]));

        if ($this->teamDayTypesScheduledForDeletion === null) {
            $this->teamDayTypesScheduledForDeletion = clone $this->combinationTeamDayTypes;
            $this->teamDayTypesScheduledForDeletion->clear();
        }

        $this->teamDayTypesScheduledForDeletion->push([$team, $day, $type]);

        return $this;
    }
';
        $this->assertProducedCodeMatches('addRemove', $expected);
    }

    /**
     * @return void
     */
    public function testClearReferencesCode()
    {
        $expected = '
            if ($this->combinationTeamDayTypes) {
                foreach ($this->combinationTeamDayTypes as $o) {
                    $o[0]->clearAllReferences($deep);
                }
            }';

        $this->assertProducedCodeMatches('addClearReferencesCode', $expected);
    }

    /**
     * @return string
     */
    protected function getSchema(): string
    {  
        return <<<EOF
<database>

    <table name="user">
        <column name="id" type="INTEGER" primaryKey="true" autoIncrement="true"/>
    </table>

    <table name="team_user" isCrossRef="true">

        <!-- additional PKs not used in FK makes this multiple -->
        <column name="day" type="DATETIME" primaryKey="true" required="true" />
        <column name="type" type="INTEGER" primaryKey="true" required="true" />

        <column name="user_id" type="INTEGER" primaryKey="true" required="true" />
        <column name="team_id" type="INTEGER" primaryKey="true" required="true" />

        <foreign-key foreignTable="user">
            <reference local="user_id" foreign="id" />
        </foreign-key>

        <foreign-key foreignTable="team">
            <reference local="team_id" foreign="id" />
        </foreign-key>
    </table>

    <table name="team">
        <column name="id" type="INTEGER" autoIncrement="true" primaryKey="true" required="true"/>
    </table>
</database>
EOF;
    }

}
