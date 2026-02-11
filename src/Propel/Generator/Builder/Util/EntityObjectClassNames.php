<?php

declare(strict_types = 1);

namespace Propel\Generator\Builder\Util;

use Propel\Generator\Builder\Om\BuilderType;
use Propel\Generator\Model\Table;

class EntityObjectClassNames
{
    protected Table $table;

    protected ReferencedClasses $referencedClasses;

    /**
     * @param \Propel\Generator\Model\Table $table
     * @param \Propel\Generator\Builder\Util\ReferencedClasses $referencedClasses
     */
    public function __construct(
        Table $table,
        ReferencedClasses $referencedClasses
    ) {
        $this->table = $table;
        $this->referencedClasses = $referencedClasses;
    }

    /**
     * @param bool $inLocalNamespace
     * @param string|bool $aliasPrefix
     * @param \Propel\Generator\Builder\Om\BuilderType $builderType
     *
     * @return string
     */
    protected function getClassNameFromBuilder(bool $inLocalNamespace, string|bool $aliasPrefix, BuilderType $builderType): string
    {
        $builder = $this->referencedClasses->getGeneratorConfig()->loadConfiguredBuilder($this->table, $builderType);

        return $inLocalNamespace
            ? $this->referencedClasses->registerBuilderResultClass($builder, $aliasPrefix)
            : $builder->getFullyQualifiedClassName();
    }

    /**
     * @param bool $inLocalNamespace If true, class will be imported into
     *  namespace via `use` and local (possibly aliased) single name will
     *  be returned, fully qualified name otherwise.
     * @param string|bool $aliasPrefix
     *
     * @return string
     */
    public function useObjectBaseClassName(bool $inLocalNamespace = true, string|bool $aliasPrefix = false): string
    {
        return $this->getClassNameFromBuilder($inLocalNamespace, $aliasPrefix, BuilderType::ObjectBase);
    }

    /**
     * @param bool $inLocalNamespace If true, class will be imported into
     *  namespace via `use` and local (possibly aliased) single name will
     *  be returned, fully qualified name otherwise.
     * @param string|bool $aliasPrefix
     *
     * @return string
     */
    public function useObjectStubClassName(bool $inLocalNamespace = true, string|bool $aliasPrefix = false): string
    {
        return $this->getClassNameFromBuilder($inLocalNamespace, $aliasPrefix, BuilderType::ObjectStub);
    }

    /**
     * @param bool $inLocalNamespace If true, class will be imported into
     *  namespace via `use` and local (possibly aliased) single name will
     *  be returned, fully qualified name otherwise.
     * @param string|bool $aliasPrefix
     *
     * @return string
     */
    public function useQueryBaseClassName(bool $inLocalNamespace = true, string|bool $aliasPrefix = false): string
    {
        return $this->getClassNameFromBuilder($inLocalNamespace, $aliasPrefix, BuilderType::QueryBase);
    }

    /**
     * @param bool $inLocalNamespace If true, class will be imported into
     *  namespace via `use` and local (possibly aliased) single name will
     *  be returned, fully qualified name otherwise.
     * @param string|bool $aliasPrefix
     *
     * @return string
     */
    public function useQueryStubClassName(bool $inLocalNamespace = true, string|bool $aliasPrefix = false): string
    {
        return $this->getClassNameFromBuilder($inLocalNamespace, $aliasPrefix, BuilderType::QueryStub);
    }

    /**
     * @param bool $inLocalNamespace If true, class will be imported into
     *  namespace via `use` and local (possibly aliased) single name will
     *  be returned, fully qualified name otherwise.
     * @param string|bool $aliasPrefix
     *
     * @return string
     */
    public function useCollectionClassName(bool $inLocalNamespace = true, string|bool $aliasPrefix = false): string
    {
        return $this->getClassNameFromBuilder($inLocalNamespace, $aliasPrefix, BuilderType::Collection);
    }

    /**
     * @param bool $inLocalNamespace If true, class will be imported into
     *  namespace via `use` and local (possibly aliased) single name will
     *  be returned, fully qualified name otherwise.
     * @param string|bool $aliasPrefix
     *
     * @return string
     */
    public function useTablemapClassName(bool $inLocalNamespace = true, string|bool $aliasPrefix = false): string
    {
        return $this->getClassNameFromBuilder($inLocalNamespace, $aliasPrefix, BuilderType::TableMap);
    }
}
