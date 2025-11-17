<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Runtime\ActiveRecord;

use Propel\Runtime\ActiveQuery\FilterExpression\FilterCollector;

/**
 * This ActiveRecord interface helps to find Propel Object
 *
 * @author jaugustin
 *
 * @method array toArray(string $keyType = \Propel\Runtime\Map\TableMap::TYPE_FIELDNAME, bool $includeLazyLoadColumns = true, array $alreadyDumpedObjects = [], bool $includeForeignObjects = false): array
 */
interface ActiveRecordInterface
{
    /**
     * Returns true if the primary key for this object is null.
     *
     * @return bool
     */
    public function isPrimaryKeyNull(): bool;

    /**
     * @return mixed
     */
    public function getPrimaryKey();

    /**
     * Set the value of a virtual column in this object
     *
     * @param string $name The virtual column name
     * @param mixed $value The value to give to the virtual column
     *
     * @return $this The current object, for fluid interface
     */
    public function setVirtualColumn(string $name, $value);

    /**
     * If the primary key is not null, return the hashcode of the
     * primary key. Otherwise, return the hash code of the object.
     *
     * @return string|int
     */
    public function hashCode();

    /**
     * Clears the current object, sets all attributes to their default values and removes
     * outgoing references as well as back-references (from other objects to this one. Results probably in a database
     * change of those foreign objects when you call `save` there).
     *
     * @return $this
     */
    public function clear();

    /**
     * Specify whether this object has been deleted.
     *
     * @param bool $b The deleted state of this object.
     *
     * @return void
     */
    public function setDeleted(bool $b): void;

    /**
     * Setter for the isNew attribute. This method will be called
     * by Propel-generated children and objects.
     *
     * @param bool $b the state of the object.
     *
     * @return void
     */
    public function setNew(bool $b): void;

    /**
     * @param \Propel\Runtime\ActiveQuery\FilterExpression\FilterCollector $filterCollector
     *
     * @return static
     */
    public static function createFromFilters(FilterCollector $filterCollector);
}
