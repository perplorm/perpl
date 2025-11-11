<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Generator\Builder\Om\ObjectBuilder\RelationCodeProducer;

/**
 * A one-to-one relation from an incoming FK
 */
class RelationFromOneCodeProducer extends AbstractIncomingRelationCode
{
    /**
     * Constructs variable name for fkey-related objects.
     *
     * @return string
     */
    #[\Override]
    public function getAttributeName(): string
    {
        return 'single' . $this->relation->getIdentifierReversed();
    }

    /**
     * @param string $script
     *
     * @return void
     */
    #[\Override]
    public function addMethods(string &$script): void
    {
        $this->addGet($script);
        $this->addSet($script);
    }

    /**
     * Adds the attributes used to store objects that have referrer fkey relationships to this object.
     * <code>protected collVarName;</code>
     * <code>private lastVarNameCriteria = null;</code>
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    #[\Override]
    public function addAttributes(string &$script): void
    {
        $varName = '$' . $this->getAttributeName();
        $className = $this->targetTableNames->useObjectBaseClassName();
        $relationIdentifier = $this->relation->getIdentifierReversed();

        $script .= "
    /**
     * $className associated via $relationIdentifier relation (1:1).
     */
    protected $className|null $varName = null;\n";
    }

    /**
     * @param string $script
     *
     * @return void
     */
    #[\Override]
    public function addDeleteScheduledItemsCode(string &$script): void
    {
        $varName = $this->getAttributeName();
        $script .= "
        if (\$this->$varName !== null) {
            if (!\$this->{$varName}->isDeleted() && (\$this->{$varName}->isNew() || \$this->{$varName}->isModified())) {
                \$affectedRows += \$this->{$varName}->save(\$con);
            }
        }\n";
    }

    /**
     * @param string $script
     *
     * @return string
     */
    #[\Override]
    public function addClearReferencesCode(string &$script): string
    {
        $varName = $this->getAttributeName();
        $script .= "
            if (\$this->$varName) {
                \$this->{$varName}->clearAllReferences(\$deep);
            }";

        return $varName;
    }

    /**
     * Adds the method that gets a one-to-one related referrer fkey.
     * This is for one-to-one relationship special case.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addGet(string &$script): void
    {
        $modelClassName = $this->targetTableNames->useObjectBaseClassName();
        $modelClassNameFq = $this->targetTableNames->useObjectBaseClassName(false);
        $queryClassName = $this->targetTableNames->useQueryStubClassName();
        $varName = $this->getAttributeName();
        $relationIdentifier = $this->relation->getIdentifierReversed();

        $script .= "
    /**
     * Gets a single $modelClassName object, which is related to this object by a one-to-one relationship.
     *
     * @param \Propel\Runtime\Connection\ConnectionInterface|null \$con optional connection object
     *
     * @return $modelClassNameFq|null
     */
    public function get{$relationIdentifier}(?ConnectionInterface \$con = null)
    {
        if (\$this->$varName === null && !\$this->isNew()) {
            \$this->$varName = $queryClassName::create()->findPk(\$this->getPrimaryKey(), \$con);
        }

        return \$this->$varName;
    }\n";
    }

    /**
     * Adds the method that sets a one-to-one related referrer fkey.
     * This is for one-to-one relationships special case.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addSet(string &$script): void
    {
        $referrer = $this->relation;
        $modelClassName = $this->targetTableNames->useObjectBaseClassName();
        $modelClassNameFq = $this->targetTableNames->useObjectBaseClassName(false);

        $ownIdentifier = $referrer->getIdentifier();
        $relationIdentifier = $referrer->getIdentifierReversed();

        $varName = $this->getAttributeName();

        $script .= "
    /**
     * Sets a single $modelClassName object as related to this object by a one-to-one relationship.
     *
     * @param $modelClassNameFq|null \$v
     *
     * @return \$this
     */
    public function set{$relationIdentifier}(?$modelClassName \$v = null)
    {
        \$this->$varName = \$v;

        // Make sure that that the passed-in $modelClassName isn't already associated with this object
        if (\$v && \$v->get{$ownIdentifier}() === null) {
            \$v->set{$ownIdentifier}(\$this);
        }

        return \$this;
    }\n";
    }
}
