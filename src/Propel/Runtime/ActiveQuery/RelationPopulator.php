<?php

declare(strict_types = 1);

namespace Propel\Runtime\ActiveQuery;

use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Map\RelationMap;
use Propel\Runtime\Map\TableMap;
use function assert;
use function class_alias;
use function ltrim;

/**
 * Data object to describe a joined hydration in a Model Query
 * ModelWith objects are used by formatters to hydrate related objects
 */
class RelationPopulator
{
    protected ModelJoin $join;

    protected string $modelName;

    protected string $relationName;

    protected bool $populatesListOnTarget;

    protected string $populateRelationMethod;

    protected string|null $initMethod = null;

    protected string|null $resetPartialMethod = null;

    protected string|null $parentJoinIdentifier = null;

    /**
     * @param \Propel\Runtime\ActiveQuery\ModelJoin $join
     */
    public function __construct(ModelJoin $join)
    {
        $this->join = $join;

        $tableMap = $join->getTableMap();
        $this->modelName = ltrim($tableMap->getClassNameOrFail(), '\\');
        $relation = $join->getRelationMap();
        $relationName = $relation->getName();

        $populatesListOnTarget = $relation->getType() == RelationMap::ONE_TO_MANY;
        $this->populatesListOnTarget = $populatesListOnTarget;
        $this->relationName = $populatesListOnTarget ? $relation->getPluralName() : $relationName;
        $this->populateRelationMethod = ($populatesListOnTarget ? 'add' : 'set') . $relationName;
        if ($populatesListOnTarget) {
            $this->initMethod = "init{$this->relationName}";
            $this->resetPartialMethod = "resetPartial{$this->relationName}";
        }

        if (!$join->isPrimary()) {
            $this->parentJoinIdentifier = $join->getLeftTableAlias() ?? $join->getParentJoin()->getRightTableAliasOrName();
        }
    }

    /**
     * @deprecated Done in construcor - this method does nothing.
     *
     * @param \Propel\Runtime\ActiveQuery\ModelJoin $join
     *
     * @return void
     */
    public function init(ModelJoin $join): void
    {
    }

    /**
     * @return \Propel\Runtime\Map\TableMap
     */
    public function getTableMap(): TableMap
    {
        return $this->join->getTableMapOrFail();
    }

    /**
     * @return string
     */
    public function getModelName(): string
    {
        return $this->modelName;
    }

    /**
     * @return bool
     */
    public function isSingleTableInheritance(): bool
    {
        return $this->getTableMap()->isSingleTableInheritance();
    }

    /**
     * @return bool
     */
    public function populatesListOnTarget(): bool
    {
        return $this->populatesListOnTarget;
    }

    /**
     * @param bool $populatesListOnTarget
     *
     * @return void
     */
    public function overridePopulatesListOnTarget(bool $populatesListOnTarget): void
    {
        $this->populatesListOnTarget = $populatesListOnTarget;
    }

    /**
     * @deprecated Use {@see static::overridePopulatesListOnTarget()}
     *
     * @param bool $populatesListOnTarget
     *
     * @return void
     */
    public function setIsWithOneToMany(bool $populatesListOnTarget): void
    {
        $this->overridePopulatesListOnTarget($populatesListOnTarget);
    }

    /**
     * @return string
     */
    public function getRelationName(): string
    {
        return $this->relationName;
    }

    /**
     * @param \Propel\Runtime\ActiveRecord\ActiveRecordInterface $target
     *
     * @return void
     */
    public function initRelationOnTarget(ActiveRecordInterface $target): void
    {
        if (!$this->initMethod) {
            return;
        }

        $initMethod = $this->initMethod;
        $target->$initMethod(false);
    }

    /**
     * @param \Propel\Runtime\ActiveRecord\ActiveRecordInterface $model
     * @param \Propel\Runtime\ActiveRecord\ActiveRecordInterface $target
     *
     * @return void
     */
    public function addModelToTarget(ActiveRecordInterface $model, ActiveRecordInterface $target): void
    {
        $relationMethod = $this->populateRelationMethod;
        $target->$relationMethod($model);
    }

    /**
     * @param \Propel\Runtime\ActiveRecord\ActiveRecordInterface $target
     *
     * @return void
     */
    public function resetPartialRelationOnTarget(ActiveRecordInterface $target): void
    {
        if (!$this->resetPartialMethod) {
            return;
        }

        $resetPartialMethod = $this->resetPartialMethod;
        $target->$resetPartialMethod(false);
    }

    /**
     * @deprecated Use aptly named {@see static::getParentJoinIdentifier()}
     *
     * @return string|null
     */
    public function getLeftPhpName(): ?string
    {
        return $this->getParentJoinIdentifier();
    }

    /**
     * @return string|null
     */
    public function getParentJoinIdentifier(): ?string
    {
        return $this->parentJoinIdentifier;
    }

    /**
     * @return string
     */
    public function getJoinIdentifier(): string
    {
        return $this->getNameInQuery();
    }

    /**
     * @deprecated Use aptly named {@see static::getJoinIdentifier()}
     *
     * @return string|null
     */
    public function getRightPhpName(): ?string
    {
        return $this->getJoinIdentifier();
    }

    /**
     * @return string
     */
    public function getNameInQuery(): string
    {
        $name = $this->join->getRightTableAliasOrName();
        assert($name !== null);

        return $name;
    }

    /**
     * @return bool
     */
    public function joinsToMainModel(): bool
    {
        return $this->parentJoinIdentifier === null;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return "modelName: {$this->modelName}, relationName: {$this->relationName}, relationMethod: {$this->populateRelationMethod}, parent join: {$this->parentJoinIdentifier}, name in query: {$this->getNameInQuery()}";
    }
}

/*
    @deprecated compatibility alias
    @phpstan-ignore classConstant.deprecatedClass
*/
class_alias(RelationPopulator::class, ModelWith::class);
