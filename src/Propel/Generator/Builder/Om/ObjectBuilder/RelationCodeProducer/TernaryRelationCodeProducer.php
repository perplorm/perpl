<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Propel\Generator\Builder\Om\ObjectBuilder\RelationCodeProducer;

use Propel\Generator\Model\ForeignKey;
use Propel\Runtime\Collection\ObjectCombinationCollection;

/**
 * Produces code for cross/many-to-many relations where the middle table is a
 * ternary relation or its primary key contains additional non-null columns, so
 * additional data is required along with the element of the opposite table to
 * produce entries in the middle table.
 */
class TernaryRelationCodeProducer extends AbstractManyToManyCodeProducer
{
    /**
     * @var string
     */
    protected const ATTRIBUTE_PREFIX = 'combination';

    /**
     * Adds the method that initializes the referrer fkey collection.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    #[\Override]
    protected function addInit(string &$script): void
    {
        $script .= $this->buildInitCode('ObjectCombinationCollection', null, null);
    }

    /**
     * @param string $script
     *
     * @return void
     */
    #[\Override]
    protected function addCreateQuery(string &$script): void
    {
        foreach ($this->crossRelation->getCrossForeignKeys() as $fk) {
            $this->buildCreateQueryForRelation($script, $fk);
        }
    }

    /**
     * @param string $script
     * @param \Propel\Generator\Model\ForeignKey $relationFk
     *
     * @return void
     */
    protected function buildCreateQueryForRelation(string &$script, ForeignKey $relationFk): void
    {
        $sourceIdentifierSingular = $this->names->getSourceIdentifier(false);
        $relationIdentifierPlural = $relationFk->getIdentifier($this->getPluralizer());

        $relatedQueryClassName = $this->signature->getClassNameImporter($relationFk)->useQueryStubClassName();

        [$signature, $_, $phpDoc] = $this->signature->buildFullSignature(['fkToIgnore' => $relationFk, 'withDefaultValues' => SignatureCollector::USE_DEFAULT_NULL]);

        $relatedUseQueryClassName = $this->getNewStubQueryBuilder($this->crossRelation->getMiddleTable())->getUnqualifiedClassName();
        $relatedUseQueryGetter = 'use' . ucfirst($relatedUseQueryClassName);
        $relatedUseQueryVariableName = lcfirst($relatedUseQueryClassName);

        $script .= "
    /**
     * Returns a new query object pre configured with filters from current object and given arguments to query the database.
     *$phpDoc
     * @param \Propel\Runtime\ActiveQuery\Criteria|null \$criteria
     *
     * @return $relatedQueryClassName
     */
    public function create{$relationIdentifierPlural}Query($signature, ?Criteria \$criteria = null): $relatedQueryClassName
    {
        \$query = $relatedQueryClassName::create(\$criteria)
            ->filterBy{$sourceIdentifierSingular}(\$this);

        \$$relatedUseQueryVariableName = \$query->{$relatedUseQueryGetter}();\n";

        foreach ($this->crossRelation->getCrossForeignKeys() as $fk) {
            if ($this->crossRelation->getIncomingForeignKey() === $fk || $relationFk === $fk) {
                continue;
            }

            $filterName = $this->nameProducer->resolveRelationIdentifier($fk);
            $argName = lcfirst($filterName);

            $script .= "
        if (\$$argName !== null) {
            \${$relatedUseQueryVariableName}->filterBy{$filterName}(\$$argName);
        }\n";
        }
        foreach ($this->crossRelation->getUnclassifiedPrimaryKeys() as $pk) {
            $filterName = $pk->getPhpName();
            $argName = lcfirst($filterName);

            $script .= "
        if (\$$argName !== null) {
            \${$relatedUseQueryVariableName}->filterBy{$filterName}(\$$argName);
        }\n";
        }

        $script .= "
        \${$relatedUseQueryVariableName}->endUse();

        return \$query;
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
        $varName = $this->names->getAttributeWithCollectionName();

        $script .= "
            if (\$this->$varName) {
                foreach (\$this->$varName as \$o) {
                    \$o[0]->clearAllReferences(\$deep);
                }
            }";

        return $varName;
    }

    /**
     * Reports the names used in getters/setters created for this cross relation.
     *
     * Names should be in singular form. Used for schema validation.
     *
     * @return array<string>
     */
    #[\Override]
    public function reserveNamesForGetters(): array
    {
        $targetIdentifierSingular = $this->names->getTargetIdentifier(false);
        $additionalGetterNames = array_map(fn (ForeignKey $fk) => $fk->getIdentifier(), $this->crossRelation->getCrossForeignKeys());

        return [$targetIdentifierSingular, ...$additionalGetterNames];
    }

    /**
     * @param string $script
     *
     * @return void
     */
    #[\Override]
    protected function addGetters(string &$script): void
    {
        [$objectCollectionClassName, $objectCollectionType] = $this->resolveObjectCollectionClassNameAndType();
        $sourceIdentifierSingular = $this->names->getSourceIdentifier(false);
        $crossRefTableName = $this->crossRelation->getMiddleTable()->getName();

        $targetIdentifierPlural = $this->names->getTargetIdentifier(true);
        $attributeName = $this->names->getAttributeWithCollectionName();
        $attributeIsPartialName = $this->names->getAttributeIsPartialName();

        $combinedType = $this->signature->buildCombinedType();
        $relatedQueryClassName = $this->middleTableNames->useQueryStubClassName();
        $ownModelClassName = $this->getTable()->getPhpName();

        $script .= "
    /**
     * Gets a combined collection of $combinedType objects related by a many-to-many relationship
     * to the current object by way of the $crossRefTableName cross-reference table.
     *
     * If the \$criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without \$criteria, the cached collection is returned.
     * If this $ownModelClassName is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param \Propel\Runtime\ActiveQuery\Criteria|null \$criteria Optional query object to filter the query
     * @param \Propel\Runtime\Connection\ConnectionInterface|null \$con Optional connection object
     *
     * @return $objectCollectionType
     */
    public function get{$targetIdentifierPlural}(?Criteria \$criteria = null, ?ConnectionInterface \$con = null): $objectCollectionClassName
    {
        \$partial = \$this->$attributeIsPartialName && !\$this->isNew();
        if (\$this->$attributeName !== null && !\$partial && !\$criteria) {
            return \$this->$attributeName;
        }

        if (\$this->isNew()) {
            // return empty collection
            if (\$this->$attributeName === null) {
                \$this->init{$targetIdentifierPlural}();
            }

            return \$this->$attributeName;
        }

        \$query = $relatedQueryClassName::create(null, \$criteria)
            ->filterBy{$sourceIdentifierSingular}(\$this)";
        foreach ($this->crossRelation->getCrossForeignKeys() as $fk) {
            $concreteTargetIdentifierSingular = $this->nameProducer->resolveRelationIdentifier($fk, false);
            $script .= "
            ->join{$concreteTargetIdentifierSingular}()";
        }

            $script .= "
            ;

        \$items = \$query->find(\$con);
        \$$attributeName = new ObjectCombinationCollection();
        foreach (\$items as \$item) {
            \$combination = [];\n";

        foreach ($this->crossRelation->getCrossForeignKeys() as $fk) {
            $concreteTargetIdentifierSingular = $this->nameProducer->resolveRelationIdentifier($fk, false);
            $script .= "
            \$combination[] = \$item->get{$concreteTargetIdentifierSingular}();";
        }

        foreach ($this->crossRelation->getUnclassifiedPrimaryKeys() as $pk) {
            $middlePkName = $pk->getPhpName();
            $script .= "
            \$combination[] = \$item->get{$middlePkName}();";
        }

        $script .= "

            \${$attributeName}[] = \$combination;
        }

        if (\$criteria) {
            return \$$attributeName;
        }

        if (\$partial && \$this->{$attributeName}) {
            //make sure that already added objects gets added to the list of the database.
            foreach (\$this->{$attributeName} as \$obj) {
                if (!\${$attributeName}->contains(\$obj)) {
                    \${$attributeName}[] = \$obj;
                }
            }
        }

        \$this->$attributeName = \$$attributeName;
        \$this->$attributeIsPartialName = false;

        return \$this->$attributeName;
    }
";

        foreach ($this->getCrossRelation()->getCrossForeignKeys() as $fk) {
            $this->buildGetFromQueryMethods($script, $fk);
        }
    }

    /**
     * @param string $script
     * @param \Propel\Generator\Model\ForeignKey $relationFk
     *
     * @return void
     */
    protected function buildGetFromQueryMethods(string &$script, ForeignKey $relationFk): void
    {
        $targetIdentifierPlural = $this->names->getTargetIdentifier(true);
        $relationIdentifier = $this->nameProducer->resolveRelationIdentifier($relationFk, true);

        $relatedObjectClassName = $this->signature->getClassNameImporter($relationFk)->useObjectBaseClassName();

        [$argumentDeclaration, $functionParameters, $phpDoc] = $this->signature->buildFullSignature(['fkToIgnore' => $relationFk, 'withDefaultValues' => SignatureCollector::USE_DEFAULT_NULL]);
        $objectCollectionType = $this->signature->getClassNameImporter($relationFk)->useCollectionClassName(false);

        $script .= "
    /**
     * Returns a not cached ObjectCollection of $relatedObjectClassName objects. This will hit always the databases.
     * If you have attached new $relatedObjectClassName object to this object you need to call `save` first to get
     * the correct return value. Use get$targetIdentifierPlural() to get the current internal state.
     *$phpDoc
     * @param \Propel\Runtime\ActiveQuery\Criteria|null \$criteria
     * @param \Propel\Runtime\Connection\ConnectionInterface|null \$con
     *
     * @return $objectCollectionType
     */
    public function get{$relationIdentifier}($argumentDeclaration, ?Criteria \$criteria = null, ?ConnectionInterface \$con = null)
    {
        return \$this->create{$relationIdentifier}Query($functionParameters, \$criteria)->find(\$con);
    }
";
    }

    /**
     * @return bool
     */
    #[\Override]
    protected function setterItemIsArray(): bool
    {
        return true;
    }

    /**
     * @return array{string, string}
     */
    #[\Override]
    protected function resolveObjectCollectionClassNameAndType(): array
    {
        $this->referencedClasses->registerClassByFullyQualifiedName(ObjectCombinationCollection::class);
        $className = 'ObjectCombinationCollection';
        $collectionType = $this->signature->buildCombinedType();
        $typeString = '\\' . ObjectCombinationCollection::class . "<$collectionType>";

        return [$className, $typeString];
    }

    /**
     * @param string $script
     *
     * @return void
     */
    #[\Override]
    public function addDeleteScheduledItemsCode(string &$script): void
    {
        $scheduledForDeletionVarName = $this->names->getAttributeScheduledForDeletionName();
        $middleQueryClassName = $this->middleTableNames->useQueryStubClassName();

        $crossPks = $this->crossRelation->getMiddleTable()->getPrimaryKey();

        $script .= "
        if (\$this->$scheduledForDeletionVarName !== null && !\$this->{$scheduledForDeletionVarName}->isEmpty()) {
            \$pks = [];
            foreach (\$this->{$scheduledForDeletionVarName} as \$combination) {
                \$entryPk = [];\n";

        foreach ($this->crossRelation->getIncomingForeignKey()->getColumnObjectsMapping() as $reference) {
            $local = $reference['local'];
            $foreign = $reference['foreign'];

            $idx = array_search($local, $crossPks, true);
            $script .= "
                \$entryPk[$idx] = \$this->get{$foreign->getPhpName()}();";
        }

        $combinationIdx = 0;
        foreach ($this->crossRelation->getCrossForeignKeys() as $crossFK) {
            foreach ($crossFK->getColumnObjectsMapping() as $reference) {
                $local = $reference['local'];
                $foreign = $reference['foreign'];

                $idx = array_search($local, $crossPks, true);
                $script .= "
                \$entryPk[$idx] = \$combination[$combinationIdx]->get{$foreign->getPhpName()}();";
            }
            $combinationIdx++;
        }

        foreach ($this->crossRelation->getUnclassifiedPrimaryKeys() as $pk) {
            $idx = array_search($pk, $crossPks, true);
            $script .= "
                \$entryPk[$idx] = \$combination[$combinationIdx];";
            $combinationIdx++;
        }

        $combineVarName = $this->names->getAttributeWithCollectionName();
        $script .= "

                \$pks[] = \$entryPk;
            }

            $middleQueryClassName::create()
                ->filterByPrimaryKeys(\$pks)
                ->delete(\$con);

            \$this->$scheduledForDeletionVarName = null;
        }

        if (\$this->$combineVarName !== null) {
            foreach (\$this->$combineVarName as \$combination) {";

        $combinationIdx = 0;
        foreach ($this->crossRelation->getCrossForeignKeys() as $crossFK) {
            $script .= "
                \$model = \$combination[$combinationIdx];
                if (!\$model->isDeleted() && (\$model->isNew() || \$model->isModified())) {
                    \$model->save(\$con);
                }\n";
            $combinationIdx++;
        }

        foreach ($this->crossRelation->getUnclassifiedPrimaryKeys() as $pk) {
            $combinationIdx++;
        }

        $script .= "
            }
        }\n";
    }

    /**
     * @return string
     */
    #[\Override]
    protected function buildAdditionalCountMethods(): string
    {
        $methods = array_map([$this, 'buildCountRelationMethod'], $this->crossRelation->getCrossForeignKeys());

        return implode('', $methods);
    }

    /**
     * @param \Propel\Generator\Model\ForeignKey $fk
     *
     * @return string
     */
    protected function buildCountRelationMethod(ForeignKey $fk): string
    {
        $targetIdentifierPlural = $this->names->getTargetIdentifier(true);
        $relationIdentifier = $this->nameProducer->resolveRelationIdentifier($fk, true);

        $argsCsv = $this->signature->getClassNameImporter($fk)->useObjectBaseClassName();

        [$argumentDeclarations, $functionParameters, $phpDoc] = $this->signature->buildFullSignature(['fkToIgnore' => $fk, 'withDefaultValues' => SignatureCollector::USE_DEFAULT_NULL]);

        return "
    /**
     * Returns the not cached count of $argsCsv objects. This will hit always the databases.
     * If you have attached new $argsCsv object to this object you need to call `save` first to get
     * the correct return value. Use get$targetIdentifierPlural() to get the current internal state.
     *$phpDoc
     * @param \Propel\Runtime\ActiveQuery\Criteria|null \$criteria
     * @param \Propel\Runtime\Connection\ConnectionInterface|null \$con
     *
     * @return int
     */
    public function count{$relationIdentifier}($argumentDeclarations, ?Criteria \$criteria = null, ?ConnectionInterface \$con = null): int
    {
        return \$this->create{$relationIdentifier}Query($functionParameters, \$criteria)->count(\$con);
    }
";
    }

    /**
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    #[\Override]
    protected function buildDoAdd(string &$script): void
    {
        $targetIdentifierSingular = $this->names->getTargetIdentifier(false);
        $sourceIdentifierSingular = $this->names->getSourceIdentifier(false);
        $middleModelClassName = $this->middleTableNames->useObjectStubClassName();

        $refKObjectClassName = $this->nameProducer->buildForeignKeyBackReferenceNameAffix($this->crossRelation->getIncomingForeignKey(), false);
        $tblFK = $this->crossRelation->getIncomingForeignKey()->getTable();
        $foreignObjectName = '$' . $tblFK->getCamelCaseName();

        [$argumentDeclarations, $_, $phpDoc] = $this->signature->buildFullSignature();

        $script .= "
    /**{$phpDoc}
     *
     * return void
     */
    protected function doAdd{$targetIdentifierSingular}($argumentDeclarations): void
    {
        {$foreignObjectName} = new {$middleModelClassName}();";

        foreach ($this->crossRelation->getCrossForeignKeys() as $fk) {
            $targetClassName = $this->nameProducer->resolveRelationIdentifier($fk, false, false);
            $concreteTargetVar = lcfirst($targetClassName);

            $script .= "
        {$foreignObjectName}->set{$targetClassName}(\${$concreteTargetVar});";
        }

        foreach ($this->crossRelation->getUnclassifiedPrimaryKeys() as $primaryKey) {
            $paramName = lcfirst($primaryKey->getPhpName());
            $script .= "
        {$foreignObjectName}->set{$primaryKey->getPhpName()}(\$$paramName);";
        }

        $script .= "
        {$foreignObjectName}->set{$sourceIdentifierSingular}(\$this);

        \$this->add{$refKObjectClassName}({$foreignObjectName});\n";

        foreach ($this->crossRelation->getCrossForeignKeys() as $fk) {
            $concreteTargetVar = $this->nameProducer->resolveRelationIdentifier($fk, false, true);
            $getterName = $this->concatRelationTargetNames($fk);
            $varName = lcfirst($getterName) . 'Entry';
            $middleTableArgsCsv = $this->buildMiddleTableArgumentCsv($fk);

            $script .= "
        // set the back reference to this object directly as using provided method either results
        // in endless loop or in multiple relations
        \$$varName = [$middleTableArgsCsv];
        if (\${$concreteTargetVar}->is{$getterName}Loaded()) {
            \${$concreteTargetVar}->get{$getterName}()->push(\$$varName);
        } elseif (!\${$concreteTargetVar}->get{$getterName}()->contains(\$$varName)) {
            \${$concreteTargetVar}->init{$getterName}();
            \${$concreteTargetVar}->get{$getterName}()->push(\$$varName);
        }";
        }

        $script .= "
    }
";
    }
}
