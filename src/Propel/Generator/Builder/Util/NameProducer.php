<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Propel\Generator\Builder\Util;

use Propel\Common\Pluralizer\PluralizerInterface;
use Propel\Generator\Model\ForeignKey;

class NameProducer
{
    /**
     * The Pluralizer class to use.
     *
     * @var \Propel\Common\Pluralizer\PluralizerInterface
     */
    protected PluralizerInterface $pluralizer;

    /**
     * @param \Propel\Common\Pluralizer\PluralizerInterface $pluralizer
     */
    public function __construct(PluralizerInterface $pluralizer)
    {
        $this->pluralizer = $pluralizer;
    }

    /**
     * Returns new or existing Pluralizer class.
     *
     * @return \Propel\Common\Pluralizer\PluralizerInterface
     */
    public function getPluralizer(): PluralizerInterface
    {
        return $this->pluralizer;
    }

    /**
     * @param string $str
     *
     * @return string
     */
    public function toPluralName(string $str): string
    {
        return $this->pluralizer->getPluralForm($str);
    }

    /**
     * Builds the PHP method name affix to be used for foreign keys for the current table (not referrers to this table).
     *
     * The difference between this method and the getRefFKPhpNameAffix() method is that in this method the
     * classname in the affix is the foreign table classname.
     *
     * @param \Propel\Generator\Model\ForeignKey $fk The local FK that we need a name for.
     * @param bool $plural Whether the php name should be plural (e.g. initRelatedObjs() vs. addRelatedObj()
     * @param bool $lcfirst
     *
     * @return string
     */
    public function resolveRelationIdentifier(ForeignKey $fk, bool $plural = false, bool $lcfirst = false): string
    {
        $name = $fk->getIdentifier($plural ? $this->pluralizer : null);

        return $lcfirst ? lcfirst($name) : $name;
    }

    /**
     * Gets the PHP method name affix to be used for referencing foreign key methods and variable names (e.g. set????(), $coll???).
     *
     * The difference between this method and the getFKPhpNameAffix() method is that in this method the
     * classname in the affix is the classname of the local fkey table.
     *
     * @param \Propel\Generator\Model\ForeignKey $fk The referrer FK that we need a name for.
     * @param bool $plural Whether the php name should be plural (e.g. initRelatedObjs() vs. addRelatedObj()
     *
     * @return string
     */
    public function buildForeignKeyBackReferenceNameAffix(ForeignKey $fk, bool $plural = false): string
    {
        return $fk->getIdentifierReversed($plural ? $this->pluralizer : null);
    }
}
