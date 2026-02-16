<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Propel\Runtime\Collection;

use Propel\Runtime\ActiveRecord\ActiveRecordInterface;

/**
 * Class for iterating over a list of Propel objects
 *
 * @author Francois Zaninotto
 *
 * @template RowFormat of array
 * @extends \Propel\Runtime\Collection\ObjectCollection<RowFormat>
 */
class ObjectCombinationCollection extends ObjectCollection
{
    /**
     * Get an array of the primary keys of all the objects in the collection
     *
     * @param bool $usePrefix
     *
     * @return array The list of the primary keys of the collection
     */
    #[\Override]
    public function getPrimaryKeys(bool $usePrefix = true): array
    {
        $ret = [];

        foreach ($this as $combination) {
            $pkCombo = [];
            foreach ($combination as $key => $obj) {
                $pkCombo[$key] = $obj->getPrimaryKey();
            }
            $ret[] = $pkCombo;
        }

        return $ret;
    }

    /**
     * @param RowFormat $value
     *
     * @return void
     */
    #[\Override]
    public function push($value): void
    {
        if (func_num_args() > 1) { // previous version used type-breaking variadic args
           /** @var RowFormat $value */
            $value = func_get_args();
        }
        parent::push($value);
    }

    /**
     * Returns all values from one position/column.
     *
     * @param int $position beginning with 1
     *
     * @return array
     */
    public function getObjectsFromPosition(int $position = 1): array
    {
        $result = [];
        foreach ($this as $array) {
            $result[] = $array[$position - 1];
        }

        return $result;
    }

    /**
     * @param RowFormat $element
     *
     * @return string|int|false
     */
    #[\Override]
    public function search($element)
    {
        if (func_num_args() > 1) { // previous version used type-breaking variadic args
            /** @var RowFormat $element */
            $element = func_get_args();
        }
        $hashes = [];
        $isActiveRecord = [];
        foreach ($element as $pos => $referenceSubitem) {
            $isRecord = $referenceSubitem instanceof ActiveRecordInterface;
            $isActiveRecord[$pos] = $isRecord;
            $hashes[$pos] = $isRecord ? $referenceSubitem->hashCode() : $referenceSubitem;
        }
        foreach ($this as $pos => $combination) {
            foreach ($combination as $idx => $storedSubitem) {
                if ($storedSubitem === null) {
                    if ($storedSubitem !== $hashes[$idx]) {
                        continue 2;
                    }
                } elseif ($isActiveRecord[$idx] ? $storedSubitem->hashCode() !== $hashes[$idx] : $storedSubitem !== $hashes[$idx]) {
                    continue 2;
                }
            }

            return $pos;
        }

        return false;
    }

    /**
     * @param RowFormat $element
     *
     * @return void
     */
    #[\Override]
    public function removeObject($element): void
    {
        if (func_num_args() > 1) { // previous version used type-breaking variadic args
            /** @var RowFormat $element */
             $element = func_get_args();
        }
        $pos = $this->search($element);
        if ($pos !== false) {
            $this->remove($pos);
        }
    }

    /**
     * @param RowFormat $element
     *
     * @return bool
     */
    #[\Override]
    public function contains($element): bool
    {
        if (func_num_args() > 1) { // previous version used type-breaking variadic args
            /** @var RowFormat $element */
             $element = func_get_args();
        }

        return $this->search($element) !== false;
    }
}
