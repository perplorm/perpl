<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Builder\Om\RelationCodeProducer;

use Propel\Generator\Builder\Om\ObjectBuilder\RelationCodeProducer\ManyToManyRelationCodeProducer;

/**
 */
class ManyToManyRelationCodeTest extends AbstractManyToManyCodeTest
{
    /**
     * @return void
     */
    public function testType(): void
    {
        $this->assertInstanceOf(ManyToManyRelationCodeProducer::class, $this->getCodeProducer());
    }

    /**
     * @return void
     */
    public function testAttributes()
    {
        $expected = '
    /**
     * Objects associated via Team relation (n:m).
     */
    protected TeamCollection|null $collTeams = null;

    /**
     * If $collTeams contains all objects in Team relation.
     */
    protected bool $collTeamsIsPartial = false;
';
        $this->assertProducedCodeMatches('addAttributes', $expected);
    }

    public function testDeletionAttributes()
    {
        $expected = '
    /**
     * Items of Team relation marked for deletion.
     */
    protected TeamCollection|null $teamsScheduledForDeletion = null;
';
        $this->assertProducedCodeMatches('addScheduledForDeletionAttribute', $expected);
    }

    public function testDeleteScheduledItemsCode()
    {
        $expected = '
        if ($this->teamsScheduledForDeletion !== null && !$this->teamsScheduledForDeletion->isEmpty()) {
            $pks = [];
            foreach ($this->teamsScheduledForDeletion as $entry) {
                $entryPk = [];

                $entryPk[1] = $this->getId();
                $entryPk[0] = $entry->getId();
                $pks[] = $entryPk;
            }

            ChildTeamUserQuery::create()
                ->filterByPrimaryKeys($pks)
                ->delete($con);

            $this->teamsScheduledForDeletion = null;
        }

        if ($this->collTeams) {
            foreach ($this->collTeams as $team) {
                if (!$team->isDeleted() && ($team->isNew() || $team->isModified())) {
                    $team->save($con);
                }
            }
        }
';
        $this->assertProducedCodeMatches('addDeleteScheduledItemsCode', $expected);
    }

    public function testClear()
    {
        $expected = '
    /**
     * Clears out the collTeams collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     */
    public function clearTeams(): void
    {
        $this->collTeams = null; // important to set this to NULL since that means it is uninitialized
    }
';
        $this->assertProducedCodeMatches('addClear', $expected);
    }

    public function testInit()
    {
        $expected = '
    /**
     * Initializes the collTeams crossRef collection.
     *
     * By default this just sets the collTeams collection to an empty collection (like clearTeams());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @return void
     */
    public function initTeams(): void
    {
        $this->collTeams = new TeamCollection();
        $this->collTeamsIsPartial = true;
        $this->collTeams->setModel(\'\Team\');
    }
';
        $this->assertProducedCodeMatches('addInit', $expected);
    }

    public function testOnReloadCode()
    {
        $expected = '
            $this->collTeams = null;';

        $this->assertProducedCodeMatches('addOnReloadCode', $expected);
    }

    public function testIsLoaded()
    {
        $expected = '
    /**
     * Checks if the collTeams collection is loaded.
     *
     * @return bool
     */
    public function isTeamsLoaded(): bool
    {
        return $this->collTeams !== null;
    }
';
        $this->assertProducedCodeMatches('addisLoaded', $expected);
    }

    public function testCreateQuery()
    {
        $expected = '';
        $this->assertProducedCodeMatches('addCreateQuery', $expected);
    }

    /**
     * @return void
     */
    public function testReserveNamesForGetters()
    {
        $reservedNames = $this->getCodeProducer()->reserveNamesForGetters();
        $expected = ['Team'];
        $this->assertEqualsCanonicalizing($expected, $reservedNames);
    }

    public function testGet()
    {
        $expected = '
    /**
     * Gets a collection of Team objects related by a many-to-many relationship
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
     * @return \Base\Collection\TeamCollection
     */
    public function getTeams(?Criteria $criteria = null, ?ConnectionInterface $con = null): TeamCollection
    {
        $partial = $this->collTeamsIsPartial && !$this->isNew();
        if ($this->collTeams === null || $criteria !== null || $partial) {
            if ($this->isNew()) {
                // return empty collection
                if ($this->collTeams === null) {
                    $this->initTeams();
                }
            } else {
                $query = ChildTeamQuery::create(null, $criteria)
                    ->filterByUser($this);
                $collTeams = $query->findObjects($con);
                if ($criteria !== null) {
                    return $collTeams;
                }

                if ($partial && $this->collTeams) {
                    //make sure that already added objects gets added to the list of the database.
                    foreach ($this->collTeams as $obj) {
                        if (!$collTeams->contains($obj)) {
                            $collTeams[] = $obj;
                        }
                    }
                }

                $this->collTeams = $collTeams;
                $this->collTeamsIsPartial = false;
            }
        }

        return $this->collTeams;
    }
';
        $this->assertProducedCodeMatches('addGetters', $expected);
    }

    public function testSet()
    {
        $expected = '
    /**
     * Sets a collection of Team objects related by a many-to-many relationship
     * to the current object by way of the team_user cross-reference table.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param \Propel\Runtime\Collection\Collection<\Base\Team> $teams A Propel collection.
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con Optional connection object
     *
     * @return static
     */
    public function setTeams(Collection $teams, ?ConnectionInterface $con = null): static
    {
        $this->clearTeams();
        $currentTeams = $this->getTeams();

        $teamsScheduledForDeletion = $currentTeams->diff($teams);

        foreach ($teamsScheduledForDeletion as $toDelete) {
            $this->removeTeam($toDelete);
        }

        foreach ($teams as $team) {
            if (!$currentTeams->contains($team)) {
                $this->doAddTeam($team);
            }
        }

        $this->collTeamsIsPartial = false;
        $this->collTeams = $teams instanceof TeamCollection
            ? $teams : new TeamCollection($teams->getData());

        return $this;
    }
';
        $this->assertProducedCodeMatches('addSetters', $expected);
    }

    public function testCount()
    {
        $expected = '
    /**
     * Gets the number of Team objects related by a many-to-many relationship
     * to the current object by way of the team_user cross-reference table.
     *
     * @param \Propel\Runtime\ActiveQuery\Criteria|null $criteria Optional query object to filter the query
     * @param bool $distinct Set to true to force count distinct
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con Optional connection object
     *
     * @return int The number of related Team objects
     */
    public function countTeams(?Criteria $criteria = null, bool $distinct = false, ?ConnectionInterface $con = null): int
    {
        $partial = $this->collTeamsIsPartial && !$this->isNew();
        if ($this->collTeams && !$criteria && !$partial) {
            return count($this->collTeams);
        }

        if ($this->isNew() && $this->collTeams === null) {
            return 0;
        }

        if ($partial && !$criteria) {
            return count($this->getTeams());
        }

        $query = ChildTeamQuery::create(null, $criteria);
        if ($distinct) {
            $query->distinct();
        }

        return $query
            ->filterByUser($this)
            ->count($con);
    }
';
        $this->assertProducedCodeMatches('addCount', $expected);
    }

    public function testAdd()
    {
        $expected = '
    /**
     * Associate a Team with this object through the team_user cross reference table.
     *
     * @param \Base\Team $team
     *
     * @return static
     */
    public function addTeam(Team $team): static
    {
        if ($this->collTeams === null) {
            $this->initTeams();
        }

        if (!$this->getTeams()->contains($team)) {
            // only add it if the **same** object is not already associated
            $this->collTeams->push($team);
            $this->doAddTeam($team);
        }

        return $this;
    }
';
        $this->assertProducedCodeMatches('addAdders', $expected);
    }

    public function testDoAdd()
    {
        $expected = '
    /**
     * @param \Base\Team $team
     *
     * @return void
     */
    protected function doAddTeam(Team $team): void
    {
        $teamUser = new ChildTeamUser();
        $teamUser->setTeam($team);
        $teamUser->setUser($this);

        $this->addTeamUser($teamUser);

        // set the back reference to this object directly as using provided method either results
        // in endless loop or in multiple relations
        if (!$team->isUsersLoaded()) {
            $team->initUsers();
            $team->getUsers()->push($this);
        } elseif (!$team->getUsers()->contains($this)) {
            $team->getUsers()->push($this);
        }
    }
';
        $this->assertProducedCodeMatches('buildDoAdd', $expected);
    }

    public function testRemove()
    {
        $expected = '
    /**
     * Remove team of this object through the team_user cross reference table.
     *
     * @param \Base\Team $team
     *
     * @return static
     */
    public function removeTeam(Team $team): static
    {
        if (!$this->getTeams()->contains($team)) {
            return $this;
        }

        $teamUser = new ChildTeamUser();
        $teamUser->setTeam($team);
        if ($team->isUsersLoaded()) {
            //remove the back reference if available
            $team->getUsers()->removeObject($this);
        }

        $teamUser->setUser($this);
        $this->removeTeamUser(clone $teamUser);
        $teamUser->clear();

        $this->collTeams->remove($this->collTeams->search($team));

        if ($this->teamsScheduledForDeletion === null) {
            $this->teamsScheduledForDeletion = clone $this->collTeams;
            $this->teamsScheduledForDeletion->clear();
        }

        $this->teamsScheduledForDeletion->push($team);

        return $this;
    }
';
        $this->assertProducedCodeMatches('addRemove', $expected);
    }

    public function testClearReferencesCode()
    {
        $expected = '
            if ($this->collTeams) {
                foreach ($this->collTeams as $o) {
                    $o->clearAllReferences($deep);
                }
            }';

        $this->assertProducedCodeMatches('addClearReferencesCode', $expected);
    }

    /**
     * @return string
     */
    protected function getSchema(): string
    {
        /* 
         * User <---n--- member of ---m---> Team
         */  
        return <<<EOF
<database>

    <table name="user">
        <column name="id" type="INTEGER" primaryKey="true" autoIncrement="true"/>
    </table>

    <table name="team_user" isCrossRef="true">
        <column name="team_id" type="INTEGER" primaryKey="true" />
        <column name="user_id" type="INTEGER" primaryKey="true" />

        <foreign-key foreignTable="team">
            <reference local="team_id" foreign="id" />
        </foreign-key>
        <foreign-key foreignTable="user">
            <reference local="user_id" foreign="id" />
        </foreign-key>
    </table>

    <table name="team">
        <column name="id" type="INTEGER" autoIncrement="true" primaryKey="true" required="true"/>
    </table>
</database>
EOF;
    }

}
