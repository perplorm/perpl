<?php

declare(strict_types = 1);

namespace Propel\Generator\Builder\Om;

use LogicException;
use Propel\Generator\Builder\Util\EntityObjectClassNames;
use Propel\Generator\Model\Column;
use Propel\Generator\Model\CrossRelation;
use Propel\Generator\Model\ForeignKey;
use Propel\Generator\Model\PropelTypes;
use Propel\Generator\Model\Table;
use Propel\Runtime\ActiveQuery\FilterExpression\ExistsFilter;
use Propel\Runtime\ActiveQuery\TypedModelCriteria;
use function addslashes;
use function array_any;
use function array_filter;
use function array_map;
use function array_merge;
use function array_slice;
use function count;
use function implode;
use function sprintf;
use function str_replace;
use function strrpos;
use function substr;
use function var_export;

/**
 * Generates a base Query class for user object model (OM).
 *
 * This class produces the base query class (e.g. BaseBookQuery) which contains
 * all the custom-built query methods.
 */
class QueryBuilder extends AbstractOMBuilder
{
    /**
     * @var \Propel\Generator\Builder\Util\EntityObjectClassNames
     */
    protected EntityObjectClassNames $tableNames;

    /**
     * @param \Propel\Generator\Model\Table $table
     */
    public function __construct(Table $table)
    {
        parent::__construct($table);
        $this->tableNames = $this->referencedClasses->useEntityObjectClassNames($table);
    }

    /**
     * Returns the package for the [base] object classes.
     *
     * @return string
     */
    #[\Override]
    public function getPackage(): string
    {
        return parent::getPackage() . '.Base';
    }

    /**
     * Returns the namespace for the query object classes.
     *
     * @return string|null
     */
    #[\Override]
    public function getNamespace(): ?string
    {
        $namespace = parent::getNamespace();

        return $namespace ? "$namespace\\Base" : 'Base';
    }

    /**
     * Returns the name of the current class being built.
     *
     * @return string
     */
    #[\Override]
    public function getUnprefixedClassName(): string
    {
        return $this->getStubQueryBuilder()->getUnprefixedClassName();
    }

    /**
     * Returns parent class name that extends TableQuery Object if is set this class must extends ModelCriteria for be compatible
     *
     * @param bool $fqcn
     *
     * @return string
     */
    public function getParentClass(bool $fqcn = false): string
    {
        $parentClass = $this->resolveParentClass();
        if ($fqcn) {
            return $parentClass;
        }

        $slashPos = strrpos($parentClass, '\\');

        return $slashPos === false ? $parentClass : substr($parentClass, $slashPos + 1);
    }

    /**
     * @return class-string
     */
    protected function resolveParentClass(): string
    {
        /** @var class-string|null $parentClass */
        $parentClass = $this->getBehaviorContent('parentClass');
        if ($parentClass) {
            return $parentClass;
        }

        $baseQueryClass = $this->getTable()->getBaseQueryClass();
        if ($baseQueryClass) {
            return $baseQueryClass;
        }

        return TypedModelCriteria::class;
    }

    /**
     * Adds class phpdoc comment and opening of class.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    #[\Override]
    protected function addClassOpen(string &$script): void
    {
        $table = $this->getTable();
        $collectionBuilder = $this->builderFactory->createObjectCollectionBuilder($this->getTable());

        $script .= $this->renderTemplate('baseQueryClassHeader.php', [
            'tableName' => $table->getName(),
            'tableDesc' => $table->getDescription(),
            'queryClass' => $this->tableNames->useQueryStubClassName(false),
            'modelClass' => $this->tableNames->useObjectStubClassName(false),
            'parentClass' => $this->getParentClass(),
            'parentClassFq' => $this->getParentClass(true),
            'entityNotFoundExceptionClass' => $this->getEntityNotFoundExceptionClass(),
            'unqualifiedClassName' => $this->getUnqualifiedClassName(),

            'addTimestamp' => $this->getBuildProperty('generator.objectModel.addTimeStamp'),
            'propelVersion' => $this->getBuildProperty('general.version'),

            'columns' => $table->getColumns(),

            'relationNames' => $this->getRelationNames(),
            'relatedTableQueryClassNames' => $this->getRelatedTableQueryClassNames(),

            'objectCollectionType' => $collectionBuilder->resolveTableCollectionClassType(),
        ]);
    }

    /**
     * Get names of all foreign key relations to and from this table.
     *
     * @return array<string>
     */
    protected function getRelationNames(): array
    {
        $table = $this->getTable();
        $fkRelationNames = array_map([$this, 'getFKPhpNameAffix'], $table->getForeignKeys());
        $refFkRelationNames = array_filter(array_map([$this, 'getRefFKPhpNameAffix'], $table->getReferrers()));

        return array_merge($fkRelationNames, $refFkRelationNames);
    }

    /**
     * Get query class names of all tables connected to this table with a foreign key relation.
     *
     * @return array<string>
     */
    protected function getRelatedTableQueryClassNames(): array
    {
        $table = $this->getTable();
        $fkTables = array_map(fn ($fk) => $fk->getForeignTable(), $table->getForeignKeys());
        $refFkTables = array_map(fn ($fk) => $fk->getTable(), $table->getReferrers());
        $relationTables = array_merge($fkTables, $refFkTables);

        return array_map(fn ($table) => $this->getNewStubQueryBuilder($table)->getQueryClassName(true), $relationTables);
    }

    /**
     * Specifies the methods that are added as part of the stub object class.
     *
     * By default there are no methods for the empty stub classes; override this method
     * if you want to change that behavior.
     *
     * @see ObjectBuilder::addClassBody()
     *
     * @param string $script
     *
     * @return void
     */
    #[\Override]
    protected function addClassBody(string &$script): void
    {
        $table = $this->getTable();

        // namespaces
        $this->declareClasses(
            '\Propel\Runtime\Propel',
            '\Propel\Runtime\ActiveQuery\TypedModelCriteria',
            '\Propel\Runtime\ActiveQuery\Criteria',
            '\Propel\Runtime\ActiveQuery\FilerExpression\FilterFactory',
            '\Propel\Runtime\ActiveQuery\ModelJoin',
            '\Exception',
            '\Propel\Runtime\Exception\PropelException',
        );
        $this->declareClassFromBuilder($this->getStubQueryBuilder(), 'Child');
        $this->declareClassFromBuilder($this->getTableMapBuilder());
        $additionalModelClasses = $table->getAdditionalModelClassImports();
        if ($additionalModelClasses) {
            $this->declareClasses(...$additionalModelClasses);
        }

        // apply behaviors
        $this->applyBehaviorModifier('queryAttributes', $script, '    ');
        $this->addEntityNotFoundExceptionClass($script);
        $this->addConstructor($script);
        $this->addFactory($script);
        $this->addFindPk($script);
        $this->addFindPkSimple($script);
        $this->addFindPkComplex($script);
        $this->addFindPks($script);
        $this->addFilterByPrimaryKey($script);
        $this->addFilterByPrimaryKeys($script);
        foreach ($this->getTable()->getColumns() as $col) {
            $this->addColumnCode($script, $col);
        }
        foreach ($this->getTable()->getForeignKeys() as $fk) {
            $this->addFilterByFk($script, $fk);
            $this->addJoinFk($script, $fk);
            $this->addUseFkQuery($script, $fk);
        }
        foreach ($this->getTable()->getReferrers() as $refFK) {
            $this->addFilterByRefFk($script, $refFK);
            $this->addJoinRefFk($script, $refFK);
            $this->addUseRefFkQuery($script, $refFK);
        }
        foreach ($this->getTable()->getCrossRelations() as $crossFKs) {
            $this->addFilterByCrossFK($script, $crossFKs);
        }
        $this->addPrune($script);
        $this->addBasePreSelect($script);
        $this->addBasePreDelete($script);
        $this->addBasePostDelete($script);
        $this->addBasePreUpdate($script);
        $this->addBasePostUpdate($script);

        // add the insert, update, delete, etc. methods
        if (!$table->isAlias() && !$table->isReadOnly()) {
            $this->addDeleteMethods($script);
        }

        // apply behaviors
        $this->applyBehaviorModifier('staticConstants', $script, '    ');
        $this->applyBehaviorModifier('staticAttributes', $script, '    ');
        $this->applyBehaviorModifier('staticMethods', $script, '    ');
        $this->applyBehaviorModifier('queryMethods', $script, '    ');
    }

    /**
     * Adds the entityNotFoundExceptionClass property which is necessary for the `requireOne` method
     * of the `ModelCriteria`
     *
     * @param string $script
     *
     * @return void
     */
    protected function addEntityNotFoundExceptionClass(string &$script): void
    {
        $exeptionClassName = addslashes($this->getEntityNotFoundExceptionClass());
        $script .= "
    /**
     * @var string
     */
    protected \$entityNotFoundExceptionClass = '$exeptionClassName';\n";
    }

    /**
     * @return string|null
     */
    private function getEntityNotFoundExceptionClass(): string|null
    {
        return $this->getGeneratorConfig()?->getConfigPropertyString('generator.objectModel.entityNotFoundExceptionClass');
    }

    /**
     * Adds the doDeleteAll(), etc. methods.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addDeleteMethods(string &$script): void
    {
        $this->addDoDeleteMethods($script);

        if ($this->isEmulateBehaviorOnDelete(ForeignKey::CASCADE)) {
            $this->addDoOnDeleteCascade($script);
        }

        if ($this->isEmulateBehaviorOnDelete(ForeignKey::SETNULL)) {
            $this->addDoOnDeleteSetNull($script);
        }
    }

    /**
     * Adds the delete() and doDeleteAll() methods.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addDoDeleteMethods(string &$script): void
    {
        $this->declareClass('\Propel\Runtime\ActiveQuery\ModelCriteria');
        $script .= $this->renderTemplate('baseQueryDoDelete', [
            'tableName' => $this->getTable()->getName(),
            'tableMapClassName' => $this->getTableMapClass(),
            'emulateDeleteCascade' => $this->isEmulateBehaviorOnDelete(ForeignKey::CASCADE),
            'emulateDeleteSetNull' => $this->isEmulateBehaviorOnDelete(ForeignKey::SETNULL),
        ]);
    }

    /**
     * Check if DBMS platform does not have native ON DELETE behavior.
     *
     * @param string $onDeleteType
     *
     * @return bool
     */
    protected function isEmulateBehaviorOnDelete(string $onDeleteType): bool
    {
        $table = $this->getTable();
        $isCandidate = count($table->getReferrers()) > 0
            && (!$this->getPlatform()->supportsNativeDeleteTrigger()
                || $this->getBuildProperty('generator.objectModel.emulateForeignKeyConstraints')
            );

        return $isCandidate && array_any($table->getReferrers(), fn (ForeignKey $fk): bool => $fk->getOnDelete() === $onDeleteType);
    }

    /**
     * Closes class.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    #[\Override]
    protected function addClassClose(string &$script): void
    {
        $script .= '}';
        $this->applyBehaviorModifier('queryFilter', $script, '');
    }

    /**
     * Adds the constructor for this object.
     *
     * @see addConstructor()
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addConstructor(string &$script): void
    {
        $script .= $this->renderTemplate('baseQueryConstructor', [
            'className' => $this->getUnqualifiedClassName(),
            'dbName' => $this->getTable()->getDatabase()->getName(),
            'modelName' => addslashes($this->tableNames->useObjectStubClassName(false)),
        ]);
    }

    /**
     * Adds the factory for this object.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addFactory(string &$script): void
    {
        $script .= $this->renderTemplate('baseQueryCreate', [
            'stubQueryClassName' => $this->tableNames->useQueryStubClassName(),
            'stubQueryClassNameFq' => $this->tableNames->useQueryStubClassName(false),
        ]);
    }

    /**
     * @param string $script
     *
     * @return void
     */
    protected function addFindPk(string &$script): void
    {
        $objectClassNameFq = $this->tableNames->useObjectBaseClassName(false);
        $table = $this->getTable();
        if (!$table->hasPrimaryKey()) {
            $pkType = 'never';
            $codeExample = '';
        } elseif (!$table->hasCompositePrimaryKey()) {
            $pkType = $table->getPrimaryKey()[0]->resolveQualifiedType();
            $codeExample = '$obj = $c->findPk(12, $con);';
        } else {
            $columnTypes = array_map(fn (Column $col) => "{$col->resolveQualifiedType()}|null", $table->getPrimaryKey());
            $pkType = 'array{' . implode(', ', $columnTypes) . '}';

            $colNames = array_map(fn (Column $col) => '$' . $col->getName(), $table->getPrimaryKey());
            $randomPkValues = array_slice([12, 34, 56, 78, 91], 0, count($colNames));
            $pkCsv = implode(', ', $randomPkValues);
            $codeExample = "\$obj = \$c->findPk([$pkCsv], \$con);";
        }

        if (!$table->hasPrimaryKey()) {
            $this->addNoPkDummyMethod(
                $script,
                ["$pkType \$key", "\Propel\Runtime\Connection\ConnectionInterface|null \$con"],
                "$objectClassNameFq|mixed|array",
                'findPk($key, ?ConnectionInterface $con = null)',
            );

            return;
        }

        $buildPoolKeyStatement = $this->getBuildPoolKeyStatement($table->getPrimaryKey());
        $buildPoolKeyStatement = str_replace('$key === null || ', '', $buildPoolKeyStatement); // remove null check to appease analyzer
        $script .= $this->renderTemplate('baseQueryFindPk', [
            'codeExample' => $codeExample,
            'pkType' => $pkType,
            'objectClassNameFq' => $objectClassNameFq,
            'tableMapClassName' => $this->getTableMapClassName(),
            'buildPoolKeyStatement' => $buildPoolKeyStatement,
        ]);
    }

    /**
     * @param string $script
     *
     * @return void
     */
    protected function addFindPkSimple(string &$script): void
    {
        $table = $this->getTable();
        if (!$table->hasPrimaryKey()) {
            return;
        }

        $usesConcreteInheritance = $table->usesConcreteInheritance();
        if ($table->isAbstract() && !$usesConcreteInheritance) {
            $tableName = $table->getPhpName();
            $script .= "
    protected function findPkSimple(\$key, ConnectionInterface \$con)
    {
        throw new PropelException('$tableName is declared abstract, you cannot query it.');
    }\n";

            return;
        }

        $this->declareClasses('\PDO');
        $this->declareGlobalFunction('is_bool', 'sprintf');

        $objectClassName = $this->tableNames->useObjectStubClassName();
        $isBulkLoad = $table->isBulkLoadTable();

        $script .= $this->renderTemplate('baseQueryFindPkSimple', [
            'query' => $this->buildSimpleSqlSelectStatement($table, !$isBulkLoad),
            'tableMapClassName' => $this->tableNames->useTablemapClassName(),
            'objectClassName' => $objectClassName,
            'objectClassNameFq' => $this->tableNames->useObjectStubClassName(false),
            'bindValueStatements' => $isBulkLoad ? '' : $this->buildPrimaryKeyColumnBindingStatements($table),
            'isBulkLoad' => $isBulkLoad,
            'classNameLiteral' => $usesConcreteInheritance ? '$cls' : $objectClassName,
            'buildPoolKeyStatement' => $this->getBuildPoolKeyStatement($table->getPrimaryKey(), $isBulkLoad ? '$pk' : '$key'),
            'buildPoolKeyStatementFromKey' => $isBulkLoad ? $this->getBuildPoolKeyStatement($table->getPrimaryKey()) : '',
        ]);
    }

    /**
     * Create select SQL statement for the given table with binding statements
     * for the primary key.
     *
     * @param \Propel\Generator\Model\Table $table
     * @param bool $withBinding If false, pk column bindings are omitted
     *
     * @return string
     */
    protected function buildSimpleSqlSelectStatement(Table $table, bool $withBinding = true): string
    {
        $selectColumns = array_filter(array_map(fn (Column $col) => $col->isLazyLoad() ? null : $col->getName(), $table->getColumns()));
        $selectColumnsCSV = implode(', ', array_map([$this, 'quoteIdentifier'], $selectColumns));

        if (!$withBinding) {
            return sprintf('SELECT %s FROM %s', $selectColumnsCSV, $this->quoteIdentifier($table->getName()));
        }

        $conditions = [];
        foreach ($table->getPrimaryKey() as $index => $column) {
            $quotedColumnName = $this->quoteIdentifier($column->getName());
            $conditions[] = sprintf('%s = :p%d', $quotedColumnName, $index);
        }

        return sprintf(
            'SELECT %s FROM %s WHERE %s',
            $selectColumnsCSV,
            $this->quoteIdentifier($table->getName()),
            implode(' AND ', $conditions),
        );
    }

    /**
     * Build PHP column binding statements for the primary key of a table (i.e. "$stmt->bindValue(':p0', $key, PDO::PARAM_INT);".
     *
     * @param \Propel\Generator\Model\Table $table
     * @param string $keyVariableLiteral
     *
     * @return string
     */
    protected function buildPrimaryKeyColumnBindingStatements(Table $table, string $keyVariableLiteral = '$key'): string
    {
        $platform = $this->getPlatform();
        $columns = (array)$table->getPrimaryKey();
        $tab = '        ';

        if (!$table->hasCompositePrimaryKey()) {
            return $platform->getColumnBindingPHP($columns[0], "':p0'", $keyVariableLiteral, $tab);
        }

        $statements = '';
        foreach ((array)$table->getPrimaryKey() as $index => $column) {
            $accessorExpression = "{$keyVariableLiteral}[$index]"; // i.e. "$key[2]"
            $statements .= $platform->getColumnBindingPHP($column, "':p$index'", $accessorExpression, $tab);
        }

        return $statements;
    }

    /**
     * Build PHP statement that creates a hash key from column values.
     *
     * @param array<\Propel\Generator\Model\Column> $pkColumns Columns used to build hash.
     * @param string $varLiteral The literal for the variable holding the key in the script.
     *
     * @throws \LogicException
     *
     * @return string
     */
    protected function getBuildPoolKeyStatement(array $pkColumns, string $varLiteral = '$key'): string
    {
        $numberOfPks = count($pkColumns);
        if ($numberOfPks === 0) {
            throw new LogicException("PoolKeyStatement cannot be created for table without PKs (in {$this->getQualifiedClassName()}).");
        }
        if ($numberOfPks === 1) {
            return "(string)$varLiteral";
        }
        $this->declareGlobalFunction('serialize', 'array_map');

        return "serialize(array_map(fn (\$k) => (string)\$k, $varLiteral))";
    }

    /**
     * Adds the findPk method for this object.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addFindPkComplex(string &$script): void
    {
        if (!$this->getTable()->hasPrimaryKey()) {
            return;
        }
        $this->declareClasses('\Propel\Runtime\Connection\ConnectionInterface');

        $script .= $this->renderTemplate('baseQueryFindPkComplex', [
            'modelClassNameFq' => $this->tableNames->useObjectBaseClassName(false),
        ]);
    }

    /**
     * @param string $script
     * @param array<string> $paramDocs
     * @param string $returnTypeDoc
     * @param string $functionDeclaration
     *
     * @return void
     */
    protected function addNoPkDummyMethod(
        string &$script,
        array $paramDocs,
        string $returnTypeDoc,
        string $functionDeclaration,
    ): void {
        $this->declareClass('Propel\\Runtime\\Exception\\LogicException');
        $script .= $this->renderTemplate('baseQueryNoPkDummyMethod', [
            'paramDocs' => $paramDocs,
            'returnTypeDoc' => $returnTypeDoc,
            'functionDeclaration' => $functionDeclaration,
            'objectName' => $this->getObjectName(),
        ]);
    }

    /**
     * Adds the findPks method for this object.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addFindPks(string &$script): void
    {
        $this->declareClasses('\Propel\Runtime\Connection\ConnectionInterface');

        $table = $this->getTable();
        $modelClassNameFq = $this->tableNames->useObjectBaseClassName(false);

        if (!$table->hasPrimaryKey()) {
            $this->addNoPkDummyMethod(
                $script,
                ['array $keys', "\Propel\Runtime\Connection\ConnectionInterface|null \$con"],
                "\Propel\Runtime\Collection\Collection<$modelClassNameFq>|mixed|array",
                'findPks($keys, ?ConnectionInterface $con = null)',
            );

            return;
        }

        $this->declareClasses('\Propel\Runtime\Propel');

        $exampleCode = count($table->getPrimaryKey()) === 1
            ? '$c->findPks(array(12, 56, 832), $con);'
            : '$c->findPks(array(array(12, 56), array(832, 123), array(123, 456)), $con);';

        $script .= $this->renderTemplate('baseQueryFindPks', [
            'exampleCode' => $exampleCode,
            'modelClassNameFq' => $modelClassNameFq,
        ]);
    }

    /**
     * Adds the filterByPrimaryKey method for this object.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addFilterByPrimaryKey(string &$script): void
    {
        $pkColumns = $this->getTable()->getPrimaryKey();
        if (!$pkColumns) {
            $this->addNoPkDummyMethod($script, ['mixed $key'], '$this', 'filterByPrimaryKey($key)');

            return;
        }

        $script .= $this->renderTemplate('baseQueryFilterByPrimaryKey', [
            'columnNames' => array_map(fn (Column $col) => $col->getName(), $pkColumns),
        ]);
    }

    /**
     * Adds the filterByPrimaryKey method for this object.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addFilterByPrimaryKeys(string &$script): void
    {
        $table = $this->getTable();
        if (!$table->hasPrimaryKey()) {
            $this->addNoPkDummyMethod($script, ['array $keys'], 'static', 'filterByPrimaryKeys(array $keys)');

            return;
        }
        $script .= $this->renderTemplate('baseQueryFilterByPrimaryKeys', [
            'pkColumnNames' => array_map(fn (Column $col) => $col->getName(), $table->getPrimaryKey()),
        ]);
    }

    /**
     * @param string $script
     * @param \Propel\Generator\Model\Column $col
     *
     * @return void
     */
    protected function addColumnCode(string &$script, Column $col): void
    {
        $this->addFilterByCol($script, $col);
        if ($col->isNamePlural()) {
            if ($col->getType() === PropelTypes::PHP_ARRAY) {
                $this->addFilterByArrayCol($script, $col);
            } elseif (in_array($col->getType(), [PropelTypes::SET_BINARY, PropelTypes::SET_NATIVE], true)) {
                $this->addFilterBySetCol($script, $col);
            }
        }
    }

    /**
     * Adds the filterByCol method for this object.
     *
     * @param string $script The script will be modified in this method.
     * @param \Propel\Generator\Model\Column $col
     *
     * @return void
     */
    protected function addFilterByCol(string &$script, Column $col): void
    {
        $colPhpName = $col->getPhpName();
        $colName = $col->getName();
        $variableName = $col->getCamelCaseName();
        $colName = $col->getName();
        $tableMapClassName = $this->getTableMapClassName();
        $script .= "
    /**
     * Filter the query on the $colName column
     *";
        if ($col->isNumericType()) {
            $script .= "
     * Example usage:
     * <code>
     * \$query->filterBy$colPhpName(1234); // WHERE $colName = 1234
     * \$query->filterBy$colPhpName(array(12, 34)); // WHERE $colName IN (12, 34)
     * \$query->filterBy$colPhpName(array('min' => 12)); // WHERE $colName > 12
     * </code>";

            if ($col->isForeignKey()) {
                $script .= "
     *";
                foreach ($col->getForeignKeys() as $fk) {
                    $script .= "
     * @see static::filterBy" . $fk->getIdentifier() . '()';
                }
            }
            $script .= "
     *
     * @param mixed \$$variableName The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => \$minValue, 'max' => \$maxValue) for intervals.";
        } elseif ($col->isTemporalType()) {
            $script .= "
     * Example usage:
     * <code>
     * \$query->filterBy$colPhpName('2011-03-14'); // WHERE $colName = '2011-03-14'
     * \$query->filterBy$colPhpName('now'); // WHERE $colName = '2011-03-14'
     * \$query->filterBy$colPhpName(array('max' => 'yesterday')); // WHERE $colName > '2011-03-13'
     * </code>
     *
     * @param mixed \$$variableName The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => \$minValue, 'max' => \$maxValue) for intervals.";
        } elseif ($col->getType() == PropelTypes::PHP_ARRAY) {
            $script .= "
     * @param array|null \$$variableName The values to use as filter.";
        } elseif ($col->isTextType()) {
            $script .= "
     * Example usage:
     * <code>
     * \$query->filterBy$colPhpName('fooValue'); // WHERE $colName = 'fooValue'
     * \$query->filterBy$colPhpName('%fooValue%', Criteria::LIKE); // WHERE $colName LIKE '%fooValue%'
     * \$query->filterBy$colPhpName(['foo', 'bar']); // WHERE $colName IN ('foo', 'bar')
     * </code>
     *
     * @param array<string>|string|null \$$variableName The value to use as filter.";
        } elseif ($col->isBooleanType()) {
            $script .= "
     * Example usage:
     * <code>
     * \$query->filterBy$colPhpName(true); // WHERE $colName = true
     * \$query->filterBy$colPhpName('yes'); // WHERE $colName = true
     * </code>
     *
     * @param string|bool|null \$$variableName The value to use as filter.
     *      Non-boolean arguments are converted using the following rules:
     *          - 1, '1', 'true', 'on', and 'yes' are converted to boolean true
     *          - 0, '0', 'false', 'off', and 'no' are converted to boolean false
     *      Check on string values is case insensitive (so 'FaLsE' is seen as 'false').";
        } else {
            $script .= "
     * @param mixed \$$variableName The value to use as filter";
        }

        $script .= "
     * @param string|null \$comparison Operator to use for the column comparison, defaults to Criteria::EQUAL";

        if ($col->isBinarySetType() || $col->isBinaryEnumType()) {
            $script .= "
     *
     * @throws \Propel\Runtime\Exception\PropelException";
        }

        $script .= "
     *
     * @return \$this
     */
    public function filterBy$colPhpName(\$$variableName = null, ?string \$comparison = null)
    {
        \$resolvedColumn = \$this->resolveLocalColumnByName('$colName');";

        if ($col->isNumericType() || $col->isTemporalType()) {
            $this->declareGlobalFunction('is_array');
            $script .= "
        if (is_array(\$$variableName)) {
            \$useMinMax = false;
            if (isset(\${$variableName}['min'])) {
                \$this->addUsingOperator(\$resolvedColumn, \${$variableName}['min'], Criteria::GREATER_EQUAL);
                \$useMinMax = true;
            }
            if (isset(\${$variableName}['max'])) {
                \$this->addUsingOperator(\$resolvedColumn, \${$variableName}['max'], Criteria::LESS_EQUAL);
                \$useMinMax = true;
            }
            if (\$useMinMax) {
                return \$this;
            }
            if (\$comparison === null) {
                \$comparison = Criteria::IN;
            }
        }";
        } elseif ($col->getType() == PropelTypes::OBJECT) {
            $this->declareGlobalFunction('is_object', 'serialize');
            $script .= "
        if (is_object(\$$variableName)) {
            \$$variableName = serialize(\$$variableName);
        }";
        } elseif ($col->getType() == PropelTypes::PHP_ARRAY) {
            $this->declareGlobalFunction('in_array');
            $script .= "
        \$arrayOps = [null, Criteria::CONTAINS_ALL, Criteria::CONTAINS_SOME, Criteria::CONTAINS_NONE];
        if (in_array(\$comparison, \$arrayOps, true)) {
            \$andOr = (\$comparison === Criteria::CONTAINS_SOME) ? Criteria::LOGICAL_OR : Criteria::LOGICAL_AND;
            \$operator = (\$comparison === Criteria::CONTAINS_NONE) ? Criteria::NOT_LIKE : Criteria::LIKE;

            \$this->combineFilters();
            foreach (\$$variableName as \$value) {
                \$this->addFilterWithConjunction(\$andOr, \$resolvedColumn, \"%| \$value |%\", \$operator);
            }
            \$this->endCombineFilters();

            if (\$comparison == Criteria::CONTAINS_NONE) {
                \$this->addOr(\$resolvedColumn, null, Criteria::ISNULL);
            }

            return \$this;
        }";
        } elseif ($col->getType() === PropelTypes::SET_NATIVE) {
            $this->declareClasses(
                '\Propel\Common\Util\SetColumnConverter',
            );
            $columnConstant = $this->getColumnConstant($col);
            $script .= "
        \$binaryOperator = match(\$comparison) {
            null,
            Criteria::CONTAINS_ALL => Criteria::BINARY_ALL,
            Criteria::CONTAINS_SOME,
            Criteria::IN => Criteria::BINARY_AND,
            Criteria::CONTAINS_NONE => Criteria::BINARY_NONE,
            default => null,
        };

        if (\$binaryOperator) {
            if (!\${$variableName} && in_array(\$binaryOperator, [Criteria::BINARY_AND, Criteria::BINARY_ALL], true)) {
                return \$this;
            }
            if (!\${$variableName} && \$binaryOperator === Criteria::BINARY_NONE) {
                \$itemBitmask = -1; // none but empty set (Propel-specific behavior)
            } else {
                \$valueSet = $tableMapClassName::getValueSet($columnConstant);
                \$itemBitmask = SetColumnConverter::convertToBitmask(\$$variableName, \$valueSet);
            }

            \$this->addUsingOperator(\$resolvedColumn, \$itemBitmask, \$binaryOperator);
            if (\$binaryOperator === Criteria::BINARY_NONE) {
                \$this->addOr(\$resolvedColumn, null, Criteria::ISNULL);
            }

            return \$this;
        }\n";
        } elseif ($col->isBinarySetType()) {
            $this->declareClasses(
                '\Propel\Common\Util\SetColumnConverter',
                '\Propel\Common\Exception\SetColumnConverterException',
            );
            $script .= "
        \$valueSet = $tableMapClassName::getValueSet(" . $this->getColumnConstant($col) . ");
        try {
            \${$variableName} = SetColumnConverter::convertToBitmask(\${$variableName}, \$valueSet);
        } catch (SetColumnConverterException \$e) {
            throw new PropelException(sprintf('Value \"%s\" is not accepted in this set column', \$e->getValue()), \$e->getCode(), \$e);
        }
        if (\$comparison === null || \$comparison == Criteria::CONTAINS_ALL) {
            if (\${$variableName} === 0) {
                return \$this;
            }
            \$comparison = Criteria::BINARY_ALL;
        } elseif (\$comparison == Criteria::CONTAINS_SOME || \$comparison == Criteria::IN) {
            if (\${$variableName} === 0) {
                return \$this;
            }
            \$comparison = Criteria::BINARY_AND;
        } elseif (\$comparison == Criteria::CONTAINS_NONE) {
            if (\${$variableName} !== 0) {
                \$this->addAnd(\$resolvedColumn, \${$variableName}, Criteria::BINARY_NONE);
            }
            \$this->addOr(\$resolvedColumn, null, Criteria::ISNULL);

            return \$this;
        }";
        } elseif ($col->isBinaryEnumType()) {
            $this->declareGlobalFunction('in_array', 'is_scalar', 'array_search');
            $script .= "
        \$valueSet = " . $this->getTableMapClassName() . '::getValueSet(' . $this->getColumnConstant($col) . ");
        if (is_scalar(\$$variableName)) {
            if (!in_array(\$$variableName, \$valueSet)) {
                throw new PropelException(\"Value '\$$variableName' is not accepted in this enumerated column\");
            }
            \$$variableName = array_search(\$$variableName, \$valueSet);
        } elseif (is_array(\$$variableName)) {
            \$convertedValues = [];
            foreach (\$$variableName as \$value) {
                if (!in_array(\$value, \$valueSet)) {
                    throw new PropelException(\"Value '\$value' is not accepted in this enumerated column\");
                }
                \$convertedValues[] = array_search(\$value, \$valueSet);
            }
            \$$variableName = \$convertedValues;
            \$comparison ??= Criteria::IN;
        }";
        } elseif ($col->isTextType()) {
            $this->declareGlobalFunction('is_array');
            $script .= "
        if (\$comparison === null && is_array(\$$variableName)) {
            \$comparison = Criteria::IN;
        }";
        } elseif ($col->isBooleanType()) {
            $this->declareGlobalFunction('is_string', 'in_array', 'strtolower');
            $script .= "
        if (is_string(\$$variableName)) {
            \$$variableName = in_array(strtolower(\$$variableName), ['false', 'off', '-', 'no', 'n', '0', ''], true) ? false : true;
        }";
        } elseif ($col->isUuidBinaryType()) {
            $uuidSwapFlag = $this->getUuidSwapFlagLiteral();
            $script .= "
        \$$variableName = UuidConverter::uuidToBinRecursive(\$$variableName, $uuidSwapFlag);";
        }
        $script .= "
        \$this->addUsingOperator(\$resolvedColumn, \$$variableName, \$comparison);

        return \$this;
    }
";
    }

    /**
     * Adds the singular filterByCol method for an Array column.
     *
     * @param string $script The script will be modified in this method.
     * @param \Propel\Generator\Model\Column $col
     *
     * @return void
     */
    protected function addFilterByArrayCol(string &$script, Column $col): void
    {
        $script .= $this->renderTemplate('baseQueryFilterByArrayColumn', [
            'colName' => $col->getName(),
            'variableName' => '$' . $col->getCamelCaseName(),
            'singularPhpName' => $col->getPhpSingularName(),
        ]);
    }

    /**
     * Adds the singular filterByCol method for an Array column.
     *
     * @param string $script The script will be modified in this method.
     * @param \Propel\Generator\Model\Column $col
     *
     * @return void
     */
    protected function addFilterBySetCol(string &$script, Column $col): void
    {
        $script .= $this->renderTemplate('baseQueryFilterBySetColumn', [
            'colName' => $col->getName(),
            'colPhpName' => $col->getPhpName(),
            'variableName' => '$' . $col->getCamelCaseName(),
            'singularPhpName' => $col->getPhpSingularName(),
        ]);
    }

    /**
     * Adds the filterByFk method for this object.
     *
     * @param string $script The script will be modified in this method.
     * @param \Propel\Generator\Model\ForeignKey $fk ForeignKey
     *
     * @return void
     */
    protected function addFilterByFk(string &$script, ForeignKey $fk): void
    {
        $this->declareClass('\Propel\Runtime\Exception\PropelException');
        if (!$fk->isComposite()) {
            $this->declareClass('\Propel\Runtime\Collection\ObjectCollection');
        }

        $fkTable = $fk->getForeignTable();
        $targetObjectBuilder = $this->getNewObjectBuilder($fkTable);
        $varName = '$' . $fkTable->getCamelCaseName();

        $columnNameAndValueStatement = [];
        foreach ($fk->getMapping() as [$localColumn, $rightValueOrColumn]) {
            $valueStatement = ($rightValueOrColumn instanceof Column)
                ? "{$varName}->get" . $rightValueOrColumn->getPhpName() . '()'
                : var_export($rightValueOrColumn, true);
            $columnNameAndValueStatement[] = [$localColumn->getName(), $valueStatement];
        }

        $foreignColumnName = $fk->getForeignColumn()?->getPhpName();

        $script .= $this->renderTemplate('baseQueryFilterByRelation', [
            'targetClassName' => $this->declareClassFromBuilder($targetObjectBuilder),
            'targetClassNameFq' => $this->getClassNameFromBuilder($targetObjectBuilder, true),
            'varName' => $varName,
            'relationName' => $fk->getIdentifier(),
            'isComposite' => $fk->isComposite(),
            'columnNameAndValueStatement' => $columnNameAndValueStatement,
            'localColumnName' => $fk->getLocalColumn()->getName(),
            'foreignColumnName' => $fk->getForeignColumn()?->getPhpName(),
            'keyColumn' => $fk->getForeignTable()->hasCompositePrimaryKey() ? $foreignColumnName : 'PrimaryKey',
        ]);
    }

    /**
     * Adds the filterByRefFk method for this object.
     *
     * @param string $script The script will be modified in this method.
     * @param \Propel\Generator\Model\ForeignKey $fk
     *
     * @return void
     */
    protected function addFilterByRefFk(string &$script, ForeignKey $fk): void
    {
        $this->declareClass('\Propel\Runtime\Exception\PropelException');
        $this->declareClass('\Propel\Runtime\Collection\ObjectCollection');

        $fkTable = $this->getTable()->getDatabase()->getTable($fk->getTableName());
        $targetObjectBuilder = $this->getNewObjectBuilder($fkTable);

        $relationColumnValues = [];
        foreach ($fk->getInverseMapping() as $mapping) {
            /** @var \Propel\Generator\Model\Column $foreignColumn */
            [$localValueOrColumn, $foreignColumn] = $mapping;
            $relationColumnValues[] = ($localValueOrColumn instanceof Column)
                ? [
                    'getterId' => $foreignColumn->getPhpName(),
                    'columnName' => $localValueOrColumn->getName(),
                ]
                : [
                    'columnExpression' => var_export($localValueOrColumn, true),
                    'getterId' => $foreignColumn->getPhpName(),
                    'pdoBindingType' => $foreignColumn->getPdoType(),
                ];
        }

        $script .= $this->renderTemplate('baseQueryFilterByRefFk', [
            'varName' => '$' . $fkTable->getCamelCaseName(),
            'relationName' => $fk->getIdentifierReversed(),
            'targetClassName' => $this->declareClassFromBuilder($targetObjectBuilder),
            'targetClassNameFq' => $this->getClassNameFromBuilder($targetObjectBuilder, true),
            'relationColumnValues' => $relationColumnValues,
            'isComposite' => $fk->isComposite(),
        ]);
    }

    /**
     * Adds the joinFk method for this object.
     *
     * @param string $script The script will be modified in this method.
     * @param \Propel\Generator\Model\ForeignKey $fk ForeignKey
     *
     * @return void
     */
    protected function addJoinFk(string &$script, ForeignKey $fk): void
    {
        $queryClass = $this->getQueryClassName();
        $fkTable = $fk->getForeignTable();
        $relationName = $this->getFKPhpNameAffix($fk);
        $joinType = $this->getJoinType($fk);
        $this->addJoinRelated($script, $fkTable, $queryClass, $relationName, $joinType);
    }

    /**
     * Adds the joinRefFk method for this object.
     *
     * @param string $script The script will be modified in this method.
     * @param \Propel\Generator\Model\ForeignKey $fk
     *
     * @return void
     */
    protected function addJoinRefFk(string &$script, ForeignKey $fk): void
    {
        $queryClass = $this->getQueryClassName();
        $fkTable = $this->getTable()->getDatabase()->getTable($fk->getTableName());
        $relationName = $this->getRefFKPhpNameAffix($fk);
        $joinType = $this->getJoinType($fk);
        $this->addJoinRelated($script, $fkTable, $queryClass, $relationName, $joinType);
    }

    /**
     * Adds a joinRelated method for this object.
     *
     * @param string $script The script will be modified in this method.
     * @param \Propel\Generator\Model\Table $fkTable
     * @param string $queryClass
     * @param string $relationName
     * @param string $joinType
     *
     * @return void
     */
    protected function addJoinRelated(
        string &$script,
        Table $fkTable,
        string $queryClass,
        string $relationName,
        string $joinType
    ): void {
        $script .= $this->renderTemplate('baseQueryJoinRelated', [
            'relationName' => $relationName,
            'joinType' => $joinType,
        ]);
    }

    /**
     * Adds the useFkQuery method for this object.
     *
     * @param string $script The script will be modified in this method.
     * @param \Propel\Generator\Model\ForeignKey $fk ForeignKey
     *
     * @return void
     */
    protected function addUseFkQuery(string &$script, ForeignKey $fk): void
    {
        $fkTable = $fk->getForeignTable();
        $fkQueryBuilder = $this->getNewStubQueryBuilder($fkTable);
        $queryClass = $this->getClassNameFromBuilder($fkQueryBuilder, true);
        $relationName = $this->getFKPhpNameAffix($fk);
        $joinType = $this->getJoinType($fk);

        $this->addUseRelatedQuery($script, $fkTable, $queryClass, $relationName, $joinType);
        $this->addWithRelatedQuery($script, $fkTable, $queryClass, $relationName, $joinType);
        $this->addUseRelatedExistsQuery($script, $fkTable, $queryClass, $relationName);
        $this->addUseRelatedInQuery($script, $fkTable, $queryClass, $relationName);
    }

    /**
     * Adds the useFkQuery method for this object.
     *
     * @param string $script The script will be modified in this method.
     * @param \Propel\Generator\Model\ForeignKey $fk
     *
     * @return void
     */
    protected function addUseRefFkQuery(string &$script, ForeignKey $fk): void
    {
        $fkTable = $this->getTable()->getDatabase()->getTable($fk->getTableName());
        $fkQueryBuilder = $this->getNewStubQueryBuilder($fkTable);
        $queryClass = $this->getClassNameFromBuilder($fkQueryBuilder, true);
        $relationName = $this->getRefFKPhpNameAffix($fk);
        $joinType = $this->getJoinType($fk);

        $this->addUseRelatedQuery($script, $fkTable, $queryClass, $relationName, $joinType);
        $this->addWithRelatedQuery($script, $fkTable, $queryClass, $relationName, $joinType);
        $this->addUseRelatedExistsQuery($script, $fkTable, $queryClass, $relationName);
        $this->addUseRelatedInQuery($script, $fkTable, $queryClass, $relationName);
    }

    /**
     * Adds a useRelatedQuery method for this object.
     *
     * @param string $script The script will be modified in this method.
     * @param \Propel\Generator\Model\Table $fkTable
     * @param string $queryClass
     * @param string $relationName
     * @param string $joinType
     *
     * @return void
     */
    protected function addUseRelatedQuery(string &$script, Table $fkTable, string $queryClass, string $relationName, string $joinType): void
    {
        $script .= $this->renderTemplate('baseQueryUseRelatedQuery', [
            'relationName' => $relationName,
            'foreignTablePhpName' => $fkTable->getPhpName(),
            'queryClass' => $queryClass,
            'joinType' => $joinType,

        ]);
    }

    /**
     * Adds a useExistsQuery and useNotExistsQuery to the object script.
     *
     * @param string $script The script will be modified in this method.
     * @param \Propel\Generator\Model\Table $fkTable The target of the relation
     * @param string $queryClass Query object class name that will be returned by the exists statement.
     * @param string $relationName Name of the relation
     *
     * @return void
     */
    protected function addUseRelatedExistsQuery(string &$script, Table $fkTable, string $queryClass, string $relationName): void
    {
        $script .= $this->renderTemplate('baseQueryExistsMethods.php', [
            'queryClass' => $queryClass,
            'relationDescription' => $this->getRelationDescription($relationName, $fkTable),
            'relationName' => $relationName,
            'existsType' => ExistsFilter::TYPE_EXISTS,
            'notExistsType' => ExistsFilter::TYPE_NOT_EXISTS,
        ]);
    }

    /**
     * Adds a useInQuery and useNotInQuery to the object script.
     *
     * @param string $script The script will be modified in this method.
     * @param \Propel\Generator\Model\Table $fkTable The target of the relation
     * @param string $queryClass Query object class name that will be returned by the IN statement.
     * @param string $relationName Name of the relation
     *
     * @return void
     */
    protected function addUseRelatedInQuery(string &$script, Table $fkTable, string $queryClass, string $relationName): void
    {
        $vars = [
            'queryClass' => $queryClass,
            'relationDescription' => $this->getRelationDescription($relationName, $fkTable),
            'relationName' => $relationName,
        ];
        $script .= $this->renderTemplate('baseQueryInMethods.php', $vars);
    }

    /**
     * @param string $relationName
     * @param \Propel\Generator\Model\Table $fkTable
     *
     * @return string
     */
    protected function getRelationDescription(string $relationName, Table $fkTable): string
    {
        return ($relationName === $fkTable->getPhpName()) ?
            "relation to $relationName table" :
            "$relationName relation to the {$fkTable->getPhpName()} table";
    }

    /**
     * Adds a withRelatedQuery method for this object.
     *
     * @param string $script The script will be modified in this method.
     * @param \Propel\Generator\Model\Table $fkTable
     * @param string $queryClass
     * @param string $relationName
     * @param string $joinType
     *
     * @return void
     */
    protected function addWithRelatedQuery(string &$script, Table $fkTable, string $queryClass, string $relationName, string $joinType): void
    {
        $script .= $this->renderTemplate('baseQueryWithRelationQuery', [
            'relationName' => $relationName,
            'queryClass' => $queryClass,
            'foreignTablePhpName' => $fkTable->getPhpName(),
            'joinType' => $joinType,
        ]);
    }

    /**
     * @param string $script
     * @param \Propel\Generator\Model\CrossRelation $crossFKs
     *
     * @return void
     */
    protected function addFilterByCrossFK(string &$script, CrossRelation $crossFKs): void
    {
        $relationName = $crossFKs->getIncomingForeignKey()->getIdentifierReversed();

        foreach ($crossFKs->getCrossForeignKeys() as $crossFK) {
            $middleTable = $crossFK->getTable();
            $targetTable = $crossFK->getForeignTable();
            $targetObjectBuilder = $this->getNewObjectBuilder($targetTable);

            $script .= $this->renderTemplate('baseQueryFilterByCrossFk', [
                'targetTableClassName' => $this->declareClassFromBuilder($targetObjectBuilder),
                'targetTableClassNameFq' => $this->getClassNameFromBuilder($targetObjectBuilder, true),
                'crossTableName' => $middleTable->getName(),
                'varName' => '$' . $targetTable->getCamelCaseName(),
                'relationName' => $relationName,
                'crossRelationName' => $crossFK->getIdentifier(),
            ]);
        }
    }

    /**
     * Adds the prune method for this object.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addPrune(string &$script): void
    {
        $table = $this->getTable();
        $modelClassName = $this->tableNames->useObjectStubClassName();
        $modelClassNameFq = $this->tableNames->useObjectStubClassName(false);
        $varName = '$' . $table->getCamelCaseName();
        $pks = $table->getPrimaryKey();

        if (!$table->hasPrimaryKey()) {
            $this->addNoPkDummyMethod($script, ["$modelClassNameFq|null $varName"], '$this', "prune(?$modelClassName $varName = null)");

            return;
        }

        $script .= $this->renderTemplate('baseQueryPrune', [
            'modelClassNameFq' => $modelClassNameFq,
            'modelClassName' => $modelClassName,
            'varName' => $varName,
            'columnNameAndGetterId' => array_map(fn (Column $col) => [$col->getName(), $col->getPhpName()], $pks),
        ]);
    }

    /**
     * Adds the basePreSelect hook for this object.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addBasePreSelect(string &$script): void
    {
        $behaviorCode = '';
        $this->applyBehaviorModifier('preSelectQuery', $behaviorCode, '        ');
        if (!$behaviorCode) {
            return;
        }
        $script .= "
    /**
     * Code to execute before every SELECT statement
     *
     * @param \Propel\Runtime\Connection\ConnectionInterface \$con The connection object used by the query
     *
     * @return void
     */
    protected function basePreSelect(ConnectionInterface \$con): void
    {{$behaviorCode}

        \$this->preSelect(\$con);
    }\n";
    }

    /**
     * Adds the basePreDelete hook for this object.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addBasePreDelete(string &$script): void
    {
        $behaviorCode = '';
        $this->applyBehaviorModifier('preDeleteQuery', $behaviorCode, '        ');
        if (!$behaviorCode) {
            return;
        }
        $script .= "
    /**
     * Code to execute before every DELETE statement
     *
     * @param \Propel\Runtime\Connection\ConnectionInterface \$con The connection object used by the query
     *
     * @return int|null
     */
    protected function basePreDelete(ConnectionInterface \$con): ?int
    {{$behaviorCode}

        return \$this->preDelete(\$con);
    }\n";
    }

    /**
     * Adds the basePostDelete hook for this object.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addBasePostDelete(string &$script): void
    {
        $behaviorCode = '';
        $this->applyBehaviorModifier('postDeleteQuery', $behaviorCode, '        ');
        if (!$behaviorCode) {
            return;
        }
        $script .= "
    /**
     * Code to execute after every DELETE statement
     *
     * @param int \$affectedRows the number of deleted rows
     * @param \Propel\Runtime\Connection\ConnectionInterface \$con The connection object used by the query
     *
     * @return int|null
     */
    protected function basePostDelete(int \$affectedRows, ConnectionInterface \$con): ?int
    {{$behaviorCode}

        return \$this->postDelete(\$affectedRows, \$con);
    }\n";
    }

    /**
     * Adds the basePreUpdate hook for this object.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addBasePreUpdate(string &$script): void
    {
        $behaviorCode = '';
        $this->applyBehaviorModifier('preUpdateQuery', $behaviorCode, '        ');
        if (!$behaviorCode) {
            return;
        }
        $script .= "
    /**
     * Code to execute before every UPDATE statement
     *
     * @param array \$values The associative array of columns and values for the update
     * @param \Propel\Runtime\Connection\ConnectionInterface \$con The connection object used by the query
     * @param bool \$forceIndividualSaves If false (default), the resulting call is a Criteria::doUpdate(), otherwise it is a series of save() calls on all the found objects
     *
     * @return int|null
     */
    protected function basePreUpdate(&\$values, ConnectionInterface \$con, \$forceIndividualSaves = false): ?int
    {{$behaviorCode}

        return \$this->preUpdate(\$values, \$con, \$forceIndividualSaves);
    }\n";
    }

    /**
     * Adds the basePostUpdate hook for this object.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addBasePostUpdate(string &$script): void
    {
        $behaviorCode = '';
        $this->applyBehaviorModifier('postUpdateQuery', $behaviorCode, '        ');
        if (!$behaviorCode) {
            return;
        }
        $script .= "
    /**
     * Code to execute after every UPDATE statement
     *
     * @param int \$affectedRows the number of updated rows
     * @param \Propel\Runtime\Connection\ConnectionInterface \$con The connection object used by the query
     *
     * @return int|null
     */
    protected function basePostUpdate(\$affectedRows, ConnectionInterface \$con): ?int
    {{$behaviorCode}

        return \$this->postUpdate(\$affectedRows, \$con);
    }\n";
    }

    /**
     * Checks whether any registered behavior on that table has a modifier for a hook
     *
     * @param string $hookName The name of the hook as called from one of this class methods, e.g. "preSave"
     * @param string $modifier
     *
     * @return bool
     */
    #[\Override]
    public function hasBehaviorModifier(string $hookName, string $modifier = ''): bool
    {
        return parent::hasBehaviorModifier($hookName, 'QueryBuilderModifier');
    }

    /**
     * Checks whether any registered behavior on that table has a modifier for a hook
     *
     * @param string $hookName The name of the hook as called from one of this class methods, e.g. "preSave"
     * @param string $script The script will be modified in this method.
     * @param string $tab
     *
     * @return string
     */
    public function applyBehaviorModifier(string $hookName, string &$script, string $tab = '        '): string
    {
        $this->applyBehaviorModifierBase($hookName, 'QueryBuilderModifier', $script, $tab);

        return $script;
    }

    /**
     * Checks whether any registered behavior content creator on that table exists a contentName
     *
     * @param string $contentName The name of the content as called from one of this class methods, e.g. "parentClassName"
     *
     * @return string|null
     */
    public function getBehaviorContent(string $contentName): ?string
    {
        return $this->getBehaviorContentBase($contentName, 'QueryBuilderModifier');
    }

    /**
     * Adds the doOnDeleteCascade() method, which provides ON DELETE CASCADE emulation.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addDoOnDeleteCascade(string &$script): void
    {
        $script .= $this->renderTemplate('baseQueryDoOnDeleteCascade', [
            'queryClassName' => $this->getQueryClassName(),
            'relationIdentifiers' => $this->collectRelationIdentifiers(ForeignKey::CASCADE),
        ]);
    }

    /**
     * Adds the doOnDeleteSetNull() method, which provides ON DELETE SET NULL emulation.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    protected function addDoOnDeleteSetNull(string &$script): void
    {
        $script .= $this->renderTemplate('baseQueryDoOnDeleteSetNull', [
            'queryClassName' => $this->getQueryClassName(),
            'relationIdentifiers' => $this->collectRelationIdentifiers(ForeignKey::SETNULL),
        ]);
    }

    /**
     * @param string $onDeleteType
     *
     * @return array<array{fkModelName: string, fkQueryClassNameFQ: string, relationColumnIds: array{fkColumnConstant: string, localColumnPhpName: string}}>
     */
    protected function collectRelationIdentifiers(string $onDeleteType): array
    {
        $table = $this->getTable();
        $relationIdentifiers = [];
        foreach ($table->getReferrers() as $fk) {
            $foreignTable = $fk->getTable();

            if ($foreignTable->isForReferenceOnly() || $fk->getOnDelete() !== $onDeleteType) {
                continue;
            }
            $identifiers = [];
            $foreignTableTableMapBuilder = $this->getNewTableMapBuilder($foreignTable);
            $this->declareClassFromBuilder($foreignTableTableMapBuilder);
            $identifiers['fkModelName'] = $foreignTableTableMapBuilder->getObjectClassName();
            $identifiers['fkQueryClassNameFQ'] = $this->declareClassFromBuilder($foreignTableTableMapBuilder->getStubQueryBuilder());

            $localColumnNames = $fk->getLocalColumns();
            $foreignColumnNames = $fk->getForeignColumns(); // should be same num as foreign

            /** @var array{fkColumnConstant:string,localColumnPhpName:string} $relationColumnIds */
            $relationColumnIds = [];
            for ($x = 0, $xlen = count($localColumnNames); $x < $xlen; $x++) {
                $columnFK = $foreignTable->getColumn($localColumnNames[$x]);
                $columnL = $table->getColumn($foreignColumnNames[$x]);
                $relationColumnIds['fkColumnConstant'] = $foreignTableTableMapBuilder->getColumnConstant($columnFK);
                $relationColumnIds['localColumnPhpName'] = $columnL->getPhpName();
            }
            $identifiers['relationColumnIds'] = $relationColumnIds;
            $relationIdentifiers[] = $identifiers;
        }

        return $relationIdentifiers;
    }
}
