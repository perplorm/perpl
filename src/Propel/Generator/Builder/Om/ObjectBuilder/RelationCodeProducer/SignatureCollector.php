<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Generator\Builder\Om\ObjectBuilder\RelationCodeProducer;

use Propel\Generator\Builder\Util\EntityObjectClassNames;
use Propel\Generator\Builder\Util\ReferencedClasses;
use Propel\Generator\Model\Column;
use Propel\Generator\Model\CrossRelation;
use Propel\Generator\Model\ForeignKey;
use RuntimeException;

class SignatureCollector
{
    /**
     * Method argument value for {@see static::addColumn()} to add
     * column default values to generated argument declarations.
     *
     * @var string
     */
    public const USE_COLUMN_DEFAULT = 'col';

    /**
     * Method argument value for {@see static::addColumn()} to add
     * null as default value to generated argument declarations.
     *
     * @var string
     */
    public const USE_DEFAULT_NULL = 'null';

    /**
     * @var \Propel\Generator\Model\CrossRelation
     */
    protected CrossRelation $relation;

    /**
     * @var \Propel\Generator\Builder\Util\ReferencedClasses
     */
    protected ReferencedClasses $referencedClasses;

    /**
     * Maps fk-relation identifiers to the corresponding name resolver.
     *
     * @var array<string, \Propel\Generator\Builder\Util\EntityObjectClassNames>
     */
    protected array $nameImporters;

    /**
     * Argument declarations, possibly with type hint and default value
     * i.e. '?int $id = null' or '$id'.
     *
     * @var array<string>
     */
    protected array $argumentDeclarations = [];

    /**
     * Argument declarations without type hint '$id'
     *
     * @var array<string>
     */
    protected array $functionParameterVariables = [];

    /**
     * Param declarations for phpdoc.
     *
     * @var array<string>
     */
    protected array $phpDocParamDeclaration = [];

    /**
     * @var array<string>
     */
    protected array $classNames = [];

    /**
     * @param \Propel\Generator\Model\CrossRelation $relation
     * @param \Propel\Generator\Builder\Util\ReferencedClasses $referencedClasses
     */
    public function __construct(CrossRelation $relation, ReferencedClasses $referencedClasses)
    {
        $this->relation = $relation;
        $this->referencedClasses = $referencedClasses;
        $this->nameImporters = $this->setupNameImporters($relation, $referencedClasses);
    }

    /**
     * @param \Propel\Generator\Model\CrossRelation $relation
     * @param \Propel\Generator\Builder\Util\ReferencedClasses $referencedClasses
     *
     * @return array<\Propel\Generator\Builder\Util\EntityObjectClassNames>
     */
    protected function setupNameImporters(CrossRelation $relation, ReferencedClasses $referencedClasses): array
    {
        $classNames = [];
        foreach ($relation->getCrossForeignKeys() as $fk) {
            $identifier = $fk->getIdentifier();
            $classNames[$identifier] = $referencedClasses->useEntityObjectClassNames($fk->getForeignTable());
        }

        return $classNames;
    }

    /**
     * @param \Propel\Generator\Model\ForeignKey $fk
     *
     * @throws \RuntimeException
     *
     * @return \Propel\Generator\Builder\Util\EntityObjectClassNames
     */
    public function getClassNameImporter(ForeignKey $fk): EntityObjectClassNames
    {
        $identifier = $fk->getIdentifier();
        if (empty($this->nameImporters[$identifier])) {
            throw new RuntimeException("Fk-relation '$identifier' is not registered in signature");
        }

        return $this->nameImporters[$identifier];
    }

    /**
     * @param array{firstFk?: \Propel\Generator\Model\ForeignKey, fkToIgnore?: \Propel\Generator\Model\ForeignKey}|array $opts
     *
     * @return array<string>
     */
    public function arrangeArgumentOrder(array $opts): array
    {
        $identifiers = array_keys($this->nameImporters);
        $firstIdentifier = ($opts['firstFk'] ?? null)?->getIdentifier();
        $ignoreIdentifier = ($opts['fkToIgnore'] ?? null)?->getIdentifier();
        $identifiers = array_diff($identifiers, [$firstIdentifier, $ignoreIdentifier]);
        if ($firstIdentifier) {
            array_unshift($identifiers, $firstIdentifier);
        }

        return $identifiers;
    }

    /**
     * @param array{firstFk?: \Propel\Generator\Model\ForeignKey, fkToIgnore?: \Propel\Generator\Model\ForeignKey, withDefaultValues?: string}|array $opts
     *
     * @return string
     */
    public function buildParameterDeclaration(array $opts = []): string
    {
        $arguments = [];

        $identifierOrder = $this->arrangeArgumentOrder($opts);
        foreach ($identifierOrder as $identifier) {
            $nameImporter = $this->nameImporters[$identifier];
            $phpTypeHint = $nameImporter->useObjectBaseClassName();
            $arguments[] = $this->buildArgumentDeclarationStatement($identifier, $phpTypeHint);
        }

        $withDefaultValue = $opts['withDefaultValues'] ?? null;
        foreach ($this->relation->getUnclassifiedPrimaryKeys() as $column) {
            $identifier = $column->getPhpName();
            $phpTypeHint = $qualifiedType = $column->getPhpType();
            $defaultValue = $this->resolveDefaultValue($column, $withDefaultValue);
            $arguments[] = $this->buildArgumentDeclarationStatement($identifier, $phpTypeHint, $defaultValue);
        }

        return implode(', ', $arguments);
    }

    /**
     * Build statement like "?int $id = null"
     *
     * @param string $identifier
     * @param string|null $phpTypeHint
     * @param string|null $defaultValue
     *
     * @return string
     */
    protected function buildArgumentDeclarationStatement(string $identifier, ?string $phpTypeHint, ?string $defaultValue = null): string
    {
        $argumentDeclarationStatement = '';

        if ($phpTypeHint) {
            if ($defaultValue === 'null') {
                $argumentDeclarationStatement .= '?';
            }
            $argumentDeclarationStatement .= "$phpTypeHint ";
        }

        $argumentDeclarationStatement .= '$' . lcfirst($identifier);

        if ($defaultValue !== null) {
            $argumentDeclarationStatement .= " = $defaultValue";
        }

        return $argumentDeclarationStatement;
    }

    /**
     * Variable CSV to be put into function calls, i.e. '$relationObject1, $relationObject2, $middleTableColumn'
     *
     * @param array{firstFk?: \Propel\Generator\Model\ForeignKey, fkToIgnore?: \Propel\Generator\Model\ForeignKey, withDefaultValues?: string}|array $opts
     *
     * @return string
     */
    public function buildFunctionParameterVariables(array $opts = []): string
    {
        $variables = [];
        $relationIdentifiers = $this->arrangeArgumentOrder($opts);
        $middleColumns = $this->relation->getUnclassifiedPrimaryKeys();
        $columnIdentifiers = array_map(fn (Column $col): string => $col->getPhpName(), $middleColumns);
        $identifiers = array_merge($relationIdentifiers, $columnIdentifiers);
        $variables = array_map(fn ($identifier): string => '$' . lcfirst($identifier), $identifiers);

        return implode(', ', $variables);
    }

    /**
     * @param array{firstFk?: \Propel\Generator\Model\ForeignKey, fkToIgnore?: \Propel\Generator\Model\ForeignKey, withDefaultValues?: string}|array $opts
     *
     * @return string
     */
    public function buildPhpDocParamDeclaration(array $opts): string
    {
        $declarations = [];
        $withDefaultValue = $opts['withDefaultValues'] ?? null;

        $identifierOrder = $this->arrangeArgumentOrder($opts);
        foreach ($identifierOrder as $identifier) {
            $argumentName = '$' . lcfirst($identifier);
            $nameImporter = $this->nameImporters[$identifier];
            $qualifiedType = $nameImporter->useObjectBaseClassName(false);
            $declarations[] = "\n     * @param $qualifiedType $argumentName";
        }

        foreach ($this->relation->getUnclassifiedPrimaryKeys() as $column) {
            $argumentName = '$' . lcfirst($column->getPhpName());
            $qualifiedType = $column->getPhpType();
            $defaultValue = $this->resolveDefaultValue($column, $withDefaultValue);
            $docType = $defaultValue === 'null' ? "$qualifiedType|null" : $qualifiedType;
            $declarations[] = "\n     * @param $docType $argumentName";
        }

        return implode('', $declarations);
    }

    /**
     * @param \Propel\Generator\Model\Column $column
     * @param string|null $withDefaultValue
     *
     * @return string|null
     */
    protected function resolveDefaultValue(Column $column, ?string $withDefaultValue): ?string
    {
        return match ($withDefaultValue) {
            self::USE_COLUMN_DEFAULT => $column->getDefaultValueString(),
            self::USE_DEFAULT_NULL => 'null',
            default => null
        };
    }

    /**
     * @param array{firstFk?: \Propel\Generator\Model\ForeignKey, fkToIgnore?: \Propel\Generator\Model\ForeignKey, withDefaultValues?: string}|array $opts
     *
     * @return array{string, string, string}
     */
    public function buildFullSignature(array $opts = []): array
    {
        return [
            $this->buildParameterDeclaration($opts),
            $this->buildFunctionParameterVariables($opts),
            $this->buildPhpDocParamDeclaration($opts),
        ];
    }

    /**
     * Builds the tuple type over all items. Used to set Collection content type.
     *
     * @return string
     */
    public function buildCombinedType(): string
    {
        $relationTargetTypes = array_map(fn ($imp) => $imp->useObjectBaseClassName(false), $this->nameImporters);
        $columnTypes = array_map(fn ($col) => $col->getPhpType(), $this->relation->getUnclassifiedPrimaryKeys());
        $types = array_merge($relationTargetTypes, $columnTypes);

        return count($types) <= 1
            ? (reset($types) ?: '')
            : 'array{' . implode(', ', $types) . '}';
    }
}
