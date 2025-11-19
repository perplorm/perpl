<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Generator\Builder\Om\RelationCodeProducer;

use Propel\Generator\Builder\Om\ObjectBuilder\RelationCodeProducer\ManyToManyRelationCodeProducer;

class ManyToManyNamedRelationCodeTest extends AbstractManyToManyCodeTest
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
     * Objects associated via LeTeam relation (n:m).
     */
    protected TeamCollection|null $collLeTeams = null;

    /**
     * If $collLeTeams contains all objects in LeTeam relation.
     */
    protected bool $collLeTeamsIsPartial = false;
';
        $this->assertProducedCodeMatches('addAttributes', $expected);
    }

    public function testDeletionAttributes()
    {
        $expected = '
    /**
     * Items of LeTeam relation marked for deletion.
     */
    protected TeamCollection|null $leTeamsScheduledForDeletion = null;
';
        $this->assertProducedCodeMatches('addScheduledForDeletionAttribute', $expected);
    }

    public function testDeleteScheduledItemsCode()
    {
        $expected = '
        if ($this->leTeamsScheduledForDeletion !== null && !$this->leTeamsScheduledForDeletion->isEmpty()) {
            $pks = [];
            foreach ($this->leTeamsScheduledForDeletion as $entry) {
                $entryPk = [];

                $entryPk[1] = $this->getId();
                $entryPk[0] = $entry->getId();
                $pks[] = $entryPk;
            }

            ChildTeamUserQuery::create()
                ->filterByPrimaryKeys($pks)
                ->delete($con);

            $this->leTeamsScheduledForDeletion = null;
        }

        if ($this->collLeTeams) {
            foreach ($this->collLeTeams as $leTeam) {
                if (!$leTeam->isDeleted() && ($leTeam->isNew() || $leTeam->isModified())) {
                    $leTeam->save($con);
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
     * Clears out the collLeTeams collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     */
    public function clearLeTeams(): void
    {
        $this->collLeTeams = null; // important to set this to NULL since that means it is uninitialized
    }
';
        $this->assertProducedCodeMatches('addClear', $expected);
    }

    public function testInit()
    {
        $expected = '
    /**
     * Initializes the collLeTeams crossRef collection.
     *
     * By default this just sets the collLeTeams collection to an empty collection (like clearLeTeams());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @return void
     */
    public function initLeTeams(): void
    {
        $this->collLeTeams = new TeamCollection();
        $this->collLeTeamsIsPartial = true;
        $this->collLeTeams->setModel(\'\Team\');
    }
';
        $this->assertProducedCodeMatches('addInit', $expected);
    }

    public function testOnReloadCode()
    {
        $expected = '
            $this->collLeTeams = null;';

        $this->assertProducedCodeMatches('addOnReloadCode', $expected);
    }

    public function testIsLoaded()
    {
        $expected = '
    /**
     * Checks if the collLeTeams collection is loaded.
     *
     * @return bool
     */
    public function isLeTeamsLoaded(): bool
    {
        return $this->collLeTeams !== null;
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
        $expected = ['LeTeam'];
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
    public function getLeTeams(?Criteria $criteria = null, ?ConnectionInterface $con = null): TeamCollection
    {
        $partial = $this->collLeTeamsIsPartial && !$this->isNew();
        if ($this->collLeTeams === null || $criteria !== null || $partial) {
            if ($this->isNew()) {
                // return empty collection
                if ($this->collLeTeams === null) {
                    $this->initLeTeams();
                }
            } else {
                $query = ChildTeamQuery::create(null, $criteria)
                    ->filterByLeUser($this);
                $collLeTeams = $query->findObjects($con);
                if ($criteria !== null) {
                    return $collLeTeams;
                }

                if ($partial && $this->collLeTeams) {
                    //make sure that already added objects gets added to the list of the database.
                    foreach ($this->collLeTeams as $obj) {
                        if (!$collLeTeams->contains($obj)) {
                            $collLeTeams[] = $obj;
                        }
                    }
                }

                $this->collLeTeams = $collLeTeams;
                $this->collLeTeamsIsPartial = false;
            }
        }

        return $this->collLeTeams;
    }
';
        $this->assertProducedCodeMatches('addGetters', $expected);
    }

    public function testSet()
    {
        $expected = '
    /**
     * Sets a collection of LeTeam objects related by a many-to-many relationship
     * to the current object by way of the team_user cross-reference table.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param \Propel\Runtime\Collection\Collection<\Base\Team> $leTeams A Propel collection.
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con Optional connection object
     *
     * @return static
     */
    public function setLeTeams(Collection $leTeams, ?ConnectionInterface $con = null): static
    {
        $this->clearLeTeams();
        $currentLeTeams = $this->getLeTeams();

        $leTeamsScheduledForDeletion = $currentLeTeams->diff($leTeams);

        foreach ($leTeamsScheduledForDeletion as $toDelete) {
            $this->removeLeTeam($toDelete);
        }

        foreach ($leTeams as $leTeam) {
            if (!$currentLeTeams->contains($leTeam)) {
                $this->doAddLeTeam($leTeam);
            }
        }

        $this->collLeTeamsIsPartial = false;
        $this->collLeTeams = $leTeams instanceof TeamCollection
            ? $leTeams : new TeamCollection($leTeams->getData());

        return $this;
    }
';
        $this->assertProducedCodeMatches('addSetters', $expected);
    }

    public function testCount()
    {
        $expected = '
    /**
     * Gets the number of LeTeam objects related by a many-to-many relationship
     * to the current object by way of the team_user cross-reference table.
     *
     * @param \Propel\Runtime\ActiveQuery\Criteria|null $criteria Optional query object to filter the query
     * @param bool $distinct Set to true to force count distinct
     * @param \Propel\Runtime\Connection\ConnectionInterface|null $con Optional connection object
     *
     * @return int The number of related LeTeam objects
     */
    public function countLeTeams(?Criteria $criteria = null, bool $distinct = false, ?ConnectionInterface $con = null): int
    {
        $partial = $this->collLeTeamsIsPartial && !$this->isNew();
        if ($this->collLeTeams && !$criteria && !$partial) {
            return count($this->collLeTeams);
        }

        if ($this->isNew() && $this->collLeTeams === null) {
            return 0;
        }

        if ($partial && !$criteria) {
            return count($this->getLeTeams());
        }

        $query = ChildTeamQuery::create(null, $criteria);
        if ($distinct) {
            $query->distinct();
        }

        return $query
            ->filterByLeUser($this)
            ->count($con);
    }
';
        $this->assertProducedCodeMatches('addCount', $expected);
    }

    public function testAdd()
    {
        $expected = '
    /**
     * Associate a LeTeam with this object through the team_user cross reference table.
     *
     * @param \Base\Team $leTeam
     *
     * @return static
     */
    public function addLeTeam(Team $leTeam): static
    {
        if ($this->collLeTeams === null) {
            $this->initLeTeams();
        }

        if (!$this->getLeTeams()->contains($leTeam)) {
            // only add it if the **same** object is not already associated
            $this->collLeTeams->push($leTeam);
            $this->doAddLeTeam($leTeam);
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
     * @param \Base\Team $leTeam
     *
     * @return void
     */
    protected function doAddLeTeam(Team $leTeam): void
    {
        $teamUser = new ChildTeamUser();
        $teamUser->setLeTeam($leTeam);
        $teamUser->setLeUser($this);

        $this->addTeamUser($teamUser);

        // set the back reference to this object directly as using provided method either results
        // in endless loop or in multiple relations
        if (!$leTeam->isLeUsersLoaded()) {
            $leTeam->initLeUsers();
            $leTeam->getLeUsers()->push($this);
        } elseif (!$leTeam->getLeUsers()->contains($this)) {
            $leTeam->getLeUsers()->push($this);
        }
    }
';
        $this->assertProducedCodeMatches('buildDoAdd', $expected);
    }

    public function testRemove()
    {
        $expected = '
    /**
     * Remove leTeam of this object through the team_user cross reference table.
     *
     * @param \Base\Team $leTeam
     *
     * @return static
     */
    public function removeLeTeam(Team $leTeam): static
    {
        if (!$this->getLeTeams()->contains($leTeam)) {
            return $this;
        }

        $teamUser = new ChildTeamUser();
        $teamUser->setLeTeam($leTeam);
        if ($leTeam->isLeUsersLoaded()) {
            //remove the back reference if available
            $leTeam->getLeUsers()->removeObject($this);
        }

        $teamUser->setLeUser($this);
        $this->removeTeamUser(clone $teamUser);
        $teamUser->clear();

        $this->collLeTeams->remove($this->collLeTeams->search($leTeam));

        if ($this->leTeamsScheduledForDeletion === null) {
            $this->leTeamsScheduledForDeletion = clone $this->collLeTeams;
            $this->leTeamsScheduledForDeletion->clear();
        }

        $this->leTeamsScheduledForDeletion->push($leTeam);

        return $this;
    }
';
        $this->assertProducedCodeMatches('addRemove', $expected);
    }

    public function testClearReferencesCode()
    {
        $expected = '
            if ($this->collLeTeams) {
                foreach ($this->collLeTeams as $o) {
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
        return <<<EOF
<database>
    <table name="user">
        <column name="id" type="INTEGER" primaryKey="true" autoIncrement="true"/>
    </table>

    <table name="team_user" isCrossRef="true">
        <column name="team_id" type="INTEGER" primaryKey="true" />
        <column name="user_id" type="INTEGER" primaryKey="true" />

        <foreign-key foreignTable="user" phpName="LeUser">
            <reference local="user_id" foreign="id" />
        </foreign-key>

        <foreign-key foreignTable="team" phpName="LeTeam">
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
