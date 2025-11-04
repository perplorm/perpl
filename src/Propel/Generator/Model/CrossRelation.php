<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Generator\Model;

use Propel\Runtime\Exception\LogicException;

/**
 * A class for information about table cross foreign keys which are used in many-to-many relations.
 *
 *
 *    ___CrossTable1___ ___User___
 *   | PK1 userId |----------FK1------------------->| id |
 *   ||_Group__|name|
 *   | PK2 groupId |-----+----FK2----->| id | |__________|
 *   ||/ \->| id2 |
 *   | PK3 relationId | / | name |
 *   ||/|________|
 *   | PK4 groupId2 |-/
 *   |_________________|
 *
 *
 *    User->getCrossFks():
 *      0:
 *         getTable() -> User
 *         getCrossForeignKeys() -> [FK2]
 *         getMiddleTable() -> CrossTable1
 *         getIncomingForeignKey() -> FK1
 *         getUnclassifiedPrimaryKeys() -> [PK3]
 *
 *    Group->getCrossFks():
 *      0:
 *         getTable() -> Group
 *         getCrossForeignKeys() -> [FK1]
 *         getMiddleTable() -> CrossTable1
 *         getIncomingForeignKey() -> FK2
 *         getUnclassifiedPrimaryKeys() -> [PK3]
 */
class CrossRelation
{
    /**
     * The source table.
     *
     * @var \Propel\Generator\Model\Table
     */
    protected $sourceTable;

    /**
     * The cross-ref table (which has crossRef=true).
     *
     * @var \Propel\Generator\Model\Table
     */
    protected $middleTable;

    /**
     * All other outgoing relations from the middle-table to other tables.
     *
     * @var list<\Propel\Generator\Model\ForeignKey>
     */
    protected $crossForeignKeys = [];

    /**
     * The incoming foreign key from the middle-table to this table.
     *
     * @var \Propel\Generator\Model\ForeignKey|null
     */
    protected $incomingForeignKey;

    /**
     * @param \Propel\Generator\Model\ForeignKey $middleToSourceFk
     * @param \Propel\Generator\Model\Table $sourceTable
     */
    public function __construct(ForeignKey $middleToSourceFk, Table $sourceTable)
    {
        $this->setIncomingForeignKey($middleToSourceFk);
        $this->setSourceTable($sourceTable);
    }

    /**
     * @param \Propel\Generator\Model\ForeignKey $foreignKey
     *
     * @return void
     */
    public function setIncomingForeignKey(ForeignKey $foreignKey): void
    {
        $this->setMiddleTable($foreignKey->getTable());
        $this->incomingForeignKey = $foreignKey;
    }

    /**
     * The foreign key from the middle-table to the target table.
     *
     * @return \Propel\Generator\Model\ForeignKey|null
     */
    public function getIncomingForeignKey(): ?ForeignKey
    {
        return $this->incomingForeignKey;
    }

    /**
     * Returns true if at least one of the local columns of $fk is not already covered by another
     * foreignKey in our collection (getCrossForeignKeys)
     *
     * E.g.
     *
     * table (local primary keys -> foreignKey):
     *
     *   pk1 -> FK1
     *   pk2
     *      \
     *        -> FK2
     *      /
     *   pk3 -> FK3
     *      \
     *        -> FK4
     *      /
     *   pk4
     *
     *  => FK1(pk1), FK2(pk2, pk3), FK3(pk3), FK4(pk3, pk4).
     *
     *  isAtLeastOneLocalPrimaryKeyNotCovered(FK1) where none fks in our collection: true
     *  isAtLeastOneLocalPrimaryKeyNotCovered(FK2) where FK1 is in our collection: true
     *  isAtLeastOneLocalPrimaryKeyNotCovered(FK3) where FK1,FK2 is in our collection: false
     *  isAtLeastOneLocalPrimaryKeyNotCovered(FK4) where FK1,FK2 is in our collection: true
     *
     * @param \Propel\Generator\Model\ForeignKey $fk
     *
     * @return bool
     */
    public function isAtLeastOneLocalPrimaryKeyNotCovered(ForeignKey $fk): bool
    {
        $primaryKeys = $fk->getLocalColumnObjects();
        foreach ($primaryKeys as $primaryKey) {
            foreach ($this->getCrossForeignKeys() as $crossFK) {
                if ($crossFK->hasLocalColumn($primaryKey)) {
                    continue 2; // found match, continue outer loop
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Returns all primary keys of middle-table which are not already covered by at least one of our cross foreignKey collection.
     *
     * @return list<\Propel\Generator\Model\Column>
     */
    public function getUnclassifiedPrimaryKeys(): array
    {
        $pks = [];
        foreach ($this->getMiddleTable()->getPrimaryKey() as $pk) {
            if ($this->getIncomingForeignKey()->hasLocalColumn($pk)) {
                continue;
            }
            foreach ($this->getCrossForeignKeys() as $crossFK) {
                if ($crossFK->hasLocalColumn($pk)) {
                    continue 2; // continue outer loop
                }
            }
            $pks[] = $pk;
        }

        return $pks;
    }

    /**
     * @param \Propel\Generator\Model\ForeignKey $foreignKey
     *
     * @return void
     */
    public function addCrossForeignKey(ForeignKey $foreignKey): void
    {
        $this->crossForeignKeys[] = $foreignKey;
    }

    /**
     * @return bool
     */
    public function hasCrossForeignKeys(): bool
    {
        return (bool)$this->crossForeignKeys;
    }

    /**
     * @param list<\Propel\Generator\Model\ForeignKey> $foreignKeys
     *
     * @return void
     */
    public function setCrossForeignKeys(array $foreignKeys): void
    {
        $this->crossForeignKeys = $foreignKeys;
    }

    /**
     * All other outgoing relations from the middle-table to other tables.
     *
     * @return array<\Propel\Generator\Model\ForeignKey>
     */
    public function getCrossForeignKeys(): array
    {
        return $this->crossForeignKeys;
    }

    /**
     * @param \Propel\Generator\Model\Table $foreignTable
     *
     * @return void
     */
    public function setMiddleTable(Table $foreignTable): void
    {
        $this->middleTable = $foreignTable;
    }

    /**
     * The middle table (which has crossRef=true).
     *
     * @return \Propel\Generator\Model\Table
     */
    public function getMiddleTable(): Table
    {
        return $this->middleTable;
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return void
     */
    public function setSourceTable(Table $table): void
    {
        $this->sourceTable = $table;
    }

    /**
     * The table owning this instance of CrossRelation (all of of the target
     * tables have their own).
     *
     * @return \Propel\Generator\Model\Table
     */
    public function getTable(): Table
    {
        return $this->sourceTable;
    }

    /**
     * @return bool
     */
    public function isMultiModel(): bool
    {
        return count($this->crossForeignKeys) > 1 || (bool)$this->getUnclassifiedPrimaryKeys();
    }

    /**
     * @throws \Propel\Runtime\Exception\LogicException
     *
     * @return \Propel\Generator\Model\Table
     */
    public function getTargetTable(): Table
    {
        if (!$this->crossForeignKeys) {
            throw new LogicException('Accessed cross FK on empty CrossForeignKey');
        }

        return $this->crossForeignKeys[0]->getForeignTableOrFail();
    }

    /**
     * @return string
     */
    #[\Override]
    public function __toString(): string
    {
        if (!$this->crossForeignKeys) {
            return 'Incomplete many-to-many relation';
        }
        $sourceTableName = $this->getTable()->getName();
        $middleTableName = $this->getMiddleTable()->getName();
        $targetTableName = $this->getTargetTable()->getName();
        // $fks = array_map(fn ($fk) => $fk->__toString(), $this->getCrossForeignKeys());

        return "Cross relation from '$sourceTableName' via middle table '$middleTableName' to '$targetTableName'\n";

        //. " Middle to source key ('incoming key'):\n" . $this->incomingForeignKey->__toString()
        //. " Middle to target keys ('crossForeignKeys'):\n" . implode("\n", $fks)
    }

    /**
     * @param \Propel\Generator\Model\ForeignKey $excludeFk
     *
     * @return array<\Propel\Generator\Model\ForeignKey>
     */
    public function getKeysInOrder(ForeignKey $excludeFk): array
    {
        $relationFks = [$this->incomingForeignKey, ...$this->crossForeignKeys];
        $middleTableFks = $this->middleTable->getForeignKeys();

        return array_filter($middleTableFks, fn ($fk) => $fk !== $excludeFk && in_array($fk, $relationFks));
    }
}
