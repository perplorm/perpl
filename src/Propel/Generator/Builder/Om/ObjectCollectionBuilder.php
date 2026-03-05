<?php

declare(strict_types = 1);

namespace Propel\Generator\Builder\Om;

use Propel\Generator\Config\GeneratorConfig;
use Propel\Generator\Config\GeneratorConfigInterface;
use Propel\Generator\Model\Table;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Formatter\ObjectFormatter;
use function strpos;
use function strrpos;
use function substr;

class ObjectCollectionBuilder extends AbstractOMBuilder
{
    /**
     * Used in {@see AbstractOMBuilder::applyBehaviorModifierBase()} to call {@see \Propel\Generator\Model\Behavior::getObjectCollectionBuilderModifier()}.
     *
     * @var string
     */
    public const MODIFIER_ID = 'ObjectCollectionBuilderModifier';

    /**
     * @var array<array{relationIdentifier: string, relationIdentifierInMethod: string, collectionClassType: string, collectionClassNameFq: class-string, collectionClassName: string}>|null
     */
    protected $relationCollectionMapping;

    /**
     * @param \Propel\Generator\Model\Table $table
     * @param \Propel\Generator\Config\GeneratorConfigInterface|null $generatorConfig
     *
     * @return void
     */
    #[\Override]
    protected function init(Table $table, ?GeneratorConfigInterface $generatorConfig): void
    {
        parent::init($table, $generatorConfig);
    }

    /**
     * @return array<array{relationIdentifier: string, relationIdentifierInMethod: string, collectionClassName: string, collectionClassNameFq: class-string, collectionClassType: string}>
     */
    protected function getMapping(): array
    {
        if (!$this->relationCollectionMapping) {
            $this->relationCollectionMapping = $this->buildCollectionMappings();
        }

        return $this->relationCollectionMapping;
    }

    /**
     * Returns the package for the base object classes.
     *
     * @return string
     */
    #[\Override]
    public function getPackage(): string
    {
        return parent::getPackage() . '.Base.Collection';
    }

    /**
     * @return string|null
     */
    #[\Override]
    public function getNamespace(): ?string
    {
        $namespace = parent::getNamespace();
        if (!$namespace) {
            return 'Base\\Collection';
        }

        $namespaceMap = $this->getBuildPropertyString('generator.objectModel.namespaceCollection');
        if (!$namespaceMap) {
            return $namespace . '\\Base\\Collection';
        }

        return "$namespace\\$namespaceMap";
    }

    /**
     * @return string
     */
    #[\Override]
    public function getUnprefixedClassName(): string
    {
        return $this->getTable()->getPhpName() . 'Collection';
    }

    /**
     * @return bool
     */
    public function skip(): bool
    {
        return !$this->getTable()->useGeneratedCollectionClass();
    }

    /**
     * @return void
     */
    protected function registerClasses(): void
    {
        $this->declareClasses(
            ObjectFormatter::class,
            ConnectionInterface::class,
            Criteria::class,
            $this->resolveParentCollectionClassNameFq(),
        );

        foreach ($this->getMapping() as $mapping) {
            $this->declareClass($mapping['collectionClassNameFq']);
        }
    }

    /**
     * @param string $script
     *
     * @return void
     */
    #[\Override]
    protected function addClassOpen(string &$script): void
    {
        $this->registerClasses(); // does not work on init

        $parentClassFq = $this->resolveParentCollectionClassNameFq();

        $script .= $this->renderTemplate('objectCollectionClassOpen', [
            'modelClassName' => $this->getObjectName(),
            'unqualifiedClassName' => $this->getUnprefixedClassName(),
            'parentClass' => substr($parentClassFq, 1 + strrpos($parentClassFq, '\\')),
            'parentType' => $this->resolveParentCollectionType(),
            'modelClassNameFq' => $this->codeBuilderStore->getStubObjectBuilder()->getFullyQualifiedClassName(),
        ]);

        $this->applyBehaviorModifier('addObjectCollectionMethods', $script);
    }

    /**
     * @param string $script
     *
     * @return void
     */
    #[\Override]
    protected function addClassBody(string &$script): void
    {
        $script .= $this->renderTemplate('objectCollectionClassBody', [
            'relationMapping' => $this->getMapping(),
        ]);
    }

    /**
     * @param string $script
     *
     * @return void
     */
    #[\Override]
    protected function addClassClose(string &$script): void
    {
        $script .= "\n}\n";
    }

    /**
     * @return array<array{relationIdentifier: string, relationIdentifierInMethod: string, collectionClassType: string, collectionClassNameFq: class-string, collectionClassName: string}>
     */
    protected function buildCollectionMappings(): array
    {
        $mapping = [];

        foreach ($this->getTable()->getForeignKeys() as $fk) {
            $relationIdentifier = $fk->getIdentifier();
            $table = $fk->getForeignTable();
            $mapping[] = $this->builtTableCollectionMapping($relationIdentifier, $relationIdentifier, $table);
        }

        foreach ($this->getTable()->getReferrers() as $fk) {
            $relationIdentifier = $fk->getIdentifierReversed();
            $relationIdentifierPlural = $this->getPluralizer()->getPluralForm($relationIdentifier);
            $table = $fk->getTable();
            $mapping[] = $this->builtTableCollectionMapping($relationIdentifier, $relationIdentifierPlural, $table);
        }

        foreach ($this->getTable()->getCrossRelations() as $crossRelation) {
            foreach ($crossRelation->getCrossForeignKeys() as $fk) {
                $relationIdentifier = $fk->getIdentifier();
                $relationIdentifierPlural = $fk->getIdentifier($this->getPluralizer());
                $table = $fk->getForeignTable();
                $mapping[] = $this->builtTableCollectionMapping($relationIdentifier, $relationIdentifierPlural, $table);
            }
        }

        return $mapping;
    }

    /**
     * @param string $relationIdentifier
     * @param string $relationIdentifierInMethod
     * @param \Propel\Generator\Model\Table $table
     *
     * @return array{relationIdentifier: string, relationIdentifierInMethod: string, collectionClassName: string, collectionClassNameFq: class-string, collectionClassType: string}
     */
    protected function builtTableCollectionMapping(string $relationIdentifier, string $relationIdentifierInMethod, Table $table): array
    {
        $collectionClassNameFq = $this->resolveTableCollectionClassNameFq($table);

        return [
            'relationIdentifier' => $relationIdentifier,
            'relationIdentifierInMethod' => $relationIdentifierInMethod === 'Relation' ? 'RelationRelation' : $relationIdentifierInMethod, // method `populateRelation()` already exists
            'collectionClassType' => $this->resolveTableCollectionClassType($table),
            'collectionClassName' => substr($collectionClassNameFq, 1 + strrpos($collectionClassNameFq, '\\')),
            'collectionClassNameFq' => $collectionClassNameFq,
        ];
    }

    /**
     * @return string
     */
    protected function resolveParentCollectionClassNameFq(): string
    {
        return $this->getTable()->getCollectionClassNameFq();
    }

    /**
     * @return string
     */
    protected function resolveParentCollectionType(): string
    {
        $table = $this->getTable();
        $collectionClassNameFq = $this->normalizeClassName($table->getCollectionClassNameFq());
        if ($collectionClassNameFq && $collectionClassNameFq !== '\\' . ObjectCollection::class) {
            return $collectionClassNameFq;
        }

        $modelClassName = $this->codeBuilderStore->getStubObjectBuilder()->getFullyQualifiedClassName();

        return '\\' . ObjectCollection::class . "<$modelClassName>";
    }

    /**
     * @param \Propel\Generator\Model\Table|null $table |null
     *
     * @return class-string
     */
    public function resolveTableCollectionClassNameFq(?Table $table = null): string
    {
        $table ??= $this->getTable();
        $collectionBuilder = $table === $this->getTable() ? $this : $this->builderFactory->createObjectCollectionBuilder($table);

        /** @var class-string $fqcn */
        $fqcn = $collectionBuilder->skip()
            ? $table->getCollectionClassNameFq()
            : $this->referencedClasses->getInternalNameOfBuilderResultClass($collectionBuilder, true);

        return $fqcn;
    }

    /**
     * @param \Propel\Generator\Model\Table|null $table |null
     *
     * @return string
     */
    public function resolveTableCollectionClassType(?Table $table = null): string
    {
        $className = $this->normalizeClassName($this->resolveTableCollectionClassNameFq($table));
        if ($className !== '\\' . ObjectCollection::class) {
            return $className;
        }

        $modelClassName = $this->referencedClasses->resolveClassNameForTable(GeneratorConfig::KEY_OBJECT_STUB, $table ?? $this->getTable());

        return '\\' . ObjectCollection::class . "<$modelClassName>";
    }

    /**
     * @param string $className
     *
     * @return string
     */
    protected function normalizeClassName(string $className): string
    {
        $needsSlashesAtStart = $className[0] !== '\\' && strpos('\\', $className) !== false;

        return $needsSlashesAtStart ? "\\$className" : $className;
    }

    /**
     * @param string $hookName The name of the hook as called from one of this class methods, e.g. "preSave"
     * @param string $script The script will be modified in this method.
     * @param string $tab
     *
     * @return void
     */
    public function applyBehaviorModifier(string $hookName, string &$script, string $tab = '        '): void
    {
        $this->applyBehaviorModifierBase($hookName, static::MODIFIER_ID, $script, $tab);
    }
}
