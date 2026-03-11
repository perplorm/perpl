<?php

declare(strict_types = 1);

namespace Propel\Generator\Builder\Util;

use Propel\Generator\Builder\BuilderFactory\BuilderFactory;
use Propel\Generator\Config\GeneratorConfig;
use Propel\Generator\Model\Table;

class EntityObjectClassNames
{
    /**
     * @var \Propel\Generator\Model\Table
     */
    protected Table $table;

    /**
     * @var \Propel\Generator\Builder\Util\ReferencedClasses
     */
    protected ReferencedClasses $referencedClasses;

    /**
     * @var \Propel\Generator\Builder\BuilderFactory\BuilderFactory
     */
    protected BuilderFactory $builderFactory;

    /**
     * @param \Propel\Generator\Model\Table $table
     * @param \Propel\Generator\Builder\Util\ReferencedClasses $referencedClasses
     * @param \Propel\Generator\Builder\BuilderFactory\BuilderFactory $builderFactory
     */
    public function __construct(
        Table $table,
        ReferencedClasses $referencedClasses,
        BuilderFactory $builderFactory
    ) {
        $this->table = $table;
        $this->referencedClasses = $referencedClasses;
        $this->builderFactory = $builderFactory;
    }

    /**
     * @param bool $inLocalNamespace
     * @param string|bool $aliasPrefix
     * @param string $builderType
     *
     * @return string
     */
    protected function getClassNameFromBuilder(bool $inLocalNamespace, string|bool $aliasPrefix, string $builderType): string
    {
        $builder = $this->builderFactory->createBuilderForTable($this->table, $builderType);

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
        return $this->getClassNameFromBuilder($inLocalNamespace, $aliasPrefix, GeneratorConfig::KEY_OBJECT_BASE);
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
        return $this->getClassNameFromBuilder($inLocalNamespace, $aliasPrefix, GeneratorConfig::KEY_OBJECT_STUB);
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
        return $this->getClassNameFromBuilder($inLocalNamespace, $aliasPrefix, GeneratorConfig::KEY_QUERY_BASE);
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
        return $this->getClassNameFromBuilder($inLocalNamespace, $aliasPrefix, GeneratorConfig::KEY_QUERY_STUB);
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
        return $this->getClassNameFromBuilder($inLocalNamespace, $aliasPrefix, GeneratorConfig::KEY_COLLECTION);
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
        return $this->getClassNameFromBuilder($inLocalNamespace, $aliasPrefix, GeneratorConfig::KEY_TABLEMAP);
    }
}
