<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Generator\Builder\Util;

use DateTimeInterface;
use Propel\Generator\Builder\BuilderFactory\BuilderFactory;
use Propel\Generator\Builder\Om\AbstractOMBuilder;
use Propel\Generator\Config\GeneratorConfigInterface;
use Propel\Generator\Exception\LogicException;
use Propel\Generator\Model\ForeignKey;
use Propel\Generator\Model\PropelTypes;
use Propel\Generator\Model\Table;

class ReferencedClasses
{
    /**
     * @var \Propel\Generator\Builder\Om\AbstractOMBuilder
     */
    protected $builder;

    /**
     * @var \Propel\Generator\Builder\BuilderFactory\BuilderFactory
     */
    protected $builderFactory;

    /**
     * Declared fully qualified classnames, to build the 'namespace' statements
     * according to this table's namespace.
     *
     * @var array<string, array<string, string>>
     */
    protected $declaredClasses = [];

    /**
     * Mapping between fully qualified classnames and their short classname or alias
     *
     * @var array<string, string>
     */
    protected $declaredShortClassesOrAlias = [];

    /**
     * List of classes that can be use without alias when model don't have namespace
     *
     * @var array<string>
     */
    protected $whiteListOfDeclaredClasses = ['PDO', 'Exception', 'RuntimeException', 'DateTime', 'ReflectionClass', 'ReflectionProperty', 'DateTimeInterface'];

    /**
     * @param \Propel\Generator\Builder\Om\AbstractOMBuilder $builder
     */
    public function __construct(AbstractOMBuilder $builder)
    {
        $this->builder = $builder;
        $this->builderFactory = new BuilderFactory();
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return \Propel\Generator\Builder\Util\EntityObjectClassNames
     */
    public function useEntityObjectClassNames(Table $table): EntityObjectClassNames
    {
        return new EntityObjectClassNames($table, $this, $this->builderFactory);
    }

    /**
     * @param \Propel\Generator\Config\GeneratorConfigInterface $generatorConfig
     *
     * @return void
     */
    public function setGeneratorConfig(GeneratorConfigInterface $generatorConfig): void
    {
        $this->builderFactory->setGeneratorConfig($generatorConfig);
    }

    /**
     * Get the list of declared classes for a given $namespace or all declared classes
     *
     * @param string|null $namespace the namespace or null
     *
     * @return array list of declared classes
     */
    public function getDeclaredClasses(?string $namespace = null): array
    {
        if ($namespace !== null && isset($this->declaredClasses[$namespace])) {
            return $this->declaredClasses[$namespace];
        }

        return $this->declaredClasses;
    }

    /**
     * Resolve the future class name of one of the to-be-built classes for the given table.
     *
     * @param string $classType
     * @param \Propel\Generator\Model\Table $table
     * @param bool $fullyQualified
     *
     * @return string
     */
    public function resolveClassNameForTable(string $classType, Table $table, bool $fullyQualified = false)
    {
        $builderStub = $this->builderFactory->createBuilderForTable($table, $classType);

        return $this->getInternalNameOfBuilderResultClass($builderStub, $fullyQualified);
    }

    /**
     * This declares the class use and returns the correct name to use (short classname, Alias, or FQCN)
     *
     * @param \Propel\Generator\Builder\Om\AbstractOMBuilder $builder
     * @param bool $fqcn true to return the $fqcn classname
     *
     * @return class-string|string ClassName, Alias or FQCN
     */
    public function getInternalNameOfBuilderResultClass(AbstractOMBuilder $builder, bool $fqcn = false): string
    {
        // old name: getClassNameFromBuilder

        if ($fqcn) {
            return $builder->getFullyQualifiedClassName();
        }

        $namespace = (string)$builder->getNamespace();
        $class = $builder->getUnqualifiedClassName();

        if (
            isset($this->declaredClasses[$namespace])
            && isset($this->declaredClasses[$namespace][$class])
        ) {
            return $this->declaredClasses[$namespace][$class];
        }

        return $this->registerSimpleClassName($class, $namespace, true);
    }

    /**
     * @param \Propel\Generator\Builder\Om\AbstractOMBuilder $builder
     * @param string|bool $aliasPrefix the prefix for the Alias or True for auto generation of the Alias
     *
     * @return string the short ClassName or an alias
     */
    public function registerBuilderResultClass(AbstractOMBuilder $builder, $aliasPrefix = false): string
    {
        // old name: declareClassFromBuilder
        return $this->registerSimpleClassNameWithPrefix(
            $builder->getUnqualifiedClassName(),
            (string)$builder->getNamespace(),
            $aliasPrefix,
        );
    }

    /**
     * This declares the class use and returns the correct name to use
     *
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    public function getInternalNameOfTable(Table $table): string
    {
        // old name: getClassNameFromTable
        $namespace = (string)$table->getNamespace();
        $class = $table->getPhpName();

        return $this->registerSimpleClassName($class, $namespace, true);
    }

    /**
     * Declare a class to be use and return its name or its alias
     *
     * @param string $class the class name
     * @param string $namespace the namespace
     * @param string|bool|null $alias the alias wanted, if set to True, it automatically adds an alias when needed
     *
     * @throws \Propel\Generator\Exception\LogicException
     *
     * @return string The class name or its alias
     */
    public function registerSimpleClassName(string $class, string $namespace = '', $alias = false): string
    {
        // old name: declareClassNamespace
        $namespace = trim($namespace, '\\');

        // check if the class is already declared
        if (isset($this->declaredClasses[$namespace][$class])) {
            return $this->declaredClasses[$namespace][$class];
        }

        $forcedAlias = $this->needAliasForClassName($class, $namespace);

        if ($alias === false || $alias === true || $alias === null) {
            $aliasWanted = $class;
            $alias = $alias || $forcedAlias;
        } else {
            $aliasWanted = $alias;
            $forcedAlias = false;
        }

        if (!$forcedAlias && !isset($this->declaredShortClassesOrAlias[$aliasWanted])) {
            $this->declaredClasses[$namespace][$class] = $aliasWanted;
            $this->declaredShortClassesOrAlias[$aliasWanted] = $namespace . '\\' . $class;

            return $aliasWanted;
        }

        // we have a duplicate class and asked for an automatic Alias
        if ($alias !== false) {
            if (substr($namespace, -5) === '\\Base' || $namespace === 'Base') {
                return $this->registerSimpleClassName($class, $namespace, 'Base' . $class);
            }

            if (substr((string)$alias, 0, 5) === 'Child') {
                //we already requested Child.$class and its in use too,
                //so use the fqcn
                return ($namespace ? '\\' . $namespace : '') . '\\' . $class;
            } else {
                $autoAliasName = 'Child' . $class;
            }

            return $this->registerSimpleClassName($class, $namespace, $autoAliasName);
        }

        throw new LogicException(sprintf(
            'The class %s duplicates the class %s and can\'t be used without alias',
            "$namespace\\$class",
            $this->declaredShortClassesOrAlias[$aliasWanted],
        ));
    }

    /**
     * Declare a use statement for a $class with a $namespace and an $aliasPrefix
     * This return the short ClassName or an alias
     *
     * @param string $class the class
     * @param string $namespace the namespace
     * @param string|bool|null $aliasPrefix optionally an alias or True to force an automatic alias prefix (Base or Child)
     *
     * @return string the short ClassName or an alias
     */
    public function registerSimpleClassNameWithPrefix(string $class, string $namespace = '', $aliasPrefix = false): string
    {
        // old name: declareClassNamespacePrefix
        $alias = is_string($aliasPrefix) ? $aliasPrefix . $class : $aliasPrefix;

        return $this->registerSimpleClassName($class, $namespace, $alias);
    }

    /**
     * Declare a Fully qualified classname with an $aliasPrefix
     * This return the short ClassName to use or an alias
     *
     * @param string $fullyQualifiedClassName the fully qualified classname
     * @param string|bool|null $aliasPrefix optionally an alias or True to force an automatic alias prefix (Base or Child)
     *
     * @return string the short ClassName or an alias
     */
    public function registerClassByFullyQualifiedName(string $fullyQualifiedClassName, $aliasPrefix = false): string
    {
        // old name: declareClass
        $fullyQualifiedClassName = trim($fullyQualifiedClassName, '\\');
        $lastSlashPos = strrpos($fullyQualifiedClassName, '\\');
        if ($lastSlashPos === false) {
            // root namespace
            return $this->registerSimpleClassNameWithPrefix($fullyQualifiedClassName, '', $aliasPrefix);
        }
        $class = substr($fullyQualifiedClassName, $lastSlashPos + 1);
        $namespace = substr($fullyQualifiedClassName, 0, $lastSlashPos);

        return $this->registerSimpleClassNameWithPrefix($class, $namespace, $aliasPrefix);
    }

    /**
     * check if the current $class need an alias or if the class could be used with a shortname without conflict
     *
     * @param string $class
     * @param string $classNamespace
     *
     * @return bool
     */
    protected function needAliasForClassName(string $class, string $classNamespace): bool
    {
        // Should remove this check by not allowing nullable return values in getNamespace
        if ($this->builder->getNamespace() === null) {
            return false;
        }

        $builderNamespace = trim($this->builder->getNamespace(), '\\');

        if ($classNamespace == $builderNamespace) {
            return false;
        }

        if (str_replace('\\Base', '', $classNamespace) == str_replace('\\Base', '', $builderNamespace)) {
            return true;
        }

        if (!$classNamespace && $builderNamespace === 'Base') {
            if (str_replace(['Query'], '', $class) == str_replace(['Query'], '', $this->builder->getUnqualifiedClassName())) {
                return true;
            }

            if ((strpos($class, 'Query') !== false)) {
                return true;
            }

            // force alias for model without namespace
            if (!in_array($class, $this->whiteListOfDeclaredClasses, true)) {
                return true;
            }
        }

        if ($classNamespace === 'Base' && $builderNamespace === '') {
            // force alias for model without namespace
            if (!in_array($class, $this->whiteListOfDeclaredClasses, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param \Propel\Generator\Model\ForeignKey $fk
     *
     * @return string
     */
    protected function resolveSourceModelClassName(ForeignKey $fk): string
    {
        return $this->getInternalNameOfTable($fk->getTable());
    }

    /**
     * @param \Propel\Generator\Model\ForeignKey $fk
     *
     * @return string
     */
    public function resolveForeignKeyTargetModelClassName(ForeignKey $fk): string
    {
        return $this->getInternalNameOfTable($fk->getForeignTableOrFail());
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     *
     * @return string
     */
    public function resolveQualifiedModelClassNameForTable(Table $table): string
    {
        $namespace = trim((string)$table->getNamespace(), '\\');
        $className = $table->getPhpName();

        $this->registerSimpleClassName($className, $namespace, true);

        return $namespace ? "\\$namespace\\$className" : $className;
    }

    /**
     * @param string $ownNamespace
     * @param string $ownClassName
     * @param string|null $ignoredNamespace
     *
     * @return string
     */
    public function buildUseStatements(string $ownNamespace, string $ownClassName, ?string $ignoredNamespace = null): string
    {
        $declaredClasses = $this->getDeclaredClasses();
        unset($declaredClasses[$ignoredNamespace]);
        $usedClasses = [];
        foreach ($declaredClasses as $namespace => $classes) {
            foreach ($classes as $class => $alias) {
                if ($class == $ownClassName && $namespace === $ownNamespace) {
                    continue;
                }
                $fqcn = $namespace ? "$namespace\\$class" : $class;
                $usedClasses[] = ($class === $alias)
                    ? "use $fqcn;"
                    : "use $fqcn as $alias;";
            }
        }
        asort($usedClasses, SORT_STRING | SORT_FLAG_CASE);

        return implode("\n", $usedClasses) . "\n";
    }

    /**
     * Register classes in builder from a doc type string.
     *
     * A string like 'DateTimeImmutable|string|null' would register DateTimeImmutable.
     *
     * @param string $docType
     *
     * @return string
     */
    public function resolveTypeDeclarationFromDocType(string $docType): string
    {
        $docTypeWithoutGenerics = preg_replace('/<[^>]*>/', '', $docType);
        $types = explode('|', $docTypeWithoutGenerics);
        foreach ($types as $key => $typeName) {
            if (!PropelTypes::isPhpObjectType($typeName)) {
                continue;
            }
            if (is_subclass_of($typeName, DateTimeInterface::class)) {
                $typeName = DateTimeInterface::class;
            }
            $types[$key] = $this->registerClassByFullyQualifiedName($typeName);
        }

        return implode('|', array_unique($types));
    }

    /**
     * @param string $typeDeclaration
     *
     * @return bool
     */
    public function isGenericTypeDeclaration(string $typeDeclaration): bool
    {
        return (bool)preg_match('/<.*>\s*$/', $typeDeclaration); // check if type ends with generic param like "array<string>"
    }
}
