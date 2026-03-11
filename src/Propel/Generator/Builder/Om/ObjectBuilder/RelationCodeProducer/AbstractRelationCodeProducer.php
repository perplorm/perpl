<?php

declare(strict_types = 1);

namespace Propel\Generator\Builder\Om\ObjectBuilder\RelationCodeProducer;

use Propel\Generator\Builder\Om\ObjectBuilder\ObjectCodeProducer;
use Propel\Generator\Config\GeneratorConfigInterface;
use Propel\Generator\Model\Table;
use function is_string;

/**
 * Generates a database loader file, which is used to register all table maps with the DatabaseMap.
 */
abstract class AbstractRelationCodeProducer extends ObjectCodeProducer
{
    protected bool $omitConnectionInterfaceParam = true;

    /**
     * @param string $script
     *
     * @return void
     */
    abstract public function addMethods(string &$script): void;

    /**
     * Adds the class attributes that are needed to store fkey related objects.
     *
     * @param string $script The script will be modified in this method.
     *
     * @return void
     */
    abstract public function addAttributes(string &$script): void;

    /**
     * @param string $script
     *
     * @return void
     */
    abstract public function addOnReloadCode(string &$script): void;

    /**
     * @param string $script
     *
     * @return void
     */
    abstract public function addDeleteScheduledItemsCode(string &$script): void;

    /**
     * @param string $script
     *
     * @return string Attribute name used by ObjectBuilder.
     */
    abstract public function addClearReferencesCode(string &$script): string;

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
        if (!$generatorConfig) {
            return;
        }
        $this->omitConnectionInterfaceParam = (bool)$this->getBuildProperty('generator.omitConnectionInterfaceParameterOnRelationMethods', true);
    }

    /**
     * @param bool $withTrailingEmptyLine
     *
     * @return string
     */
    protected function putConDoc(bool $withTrailingEmptyLine = false): string
    {
        $trailingEmptyLine = !$withTrailingEmptyLine ? '' : "\n     *";

        return $this->omitConnectionInterfaceParam ? '' : "
     * @param \Propel\Runtime\Connection\ConnectionInterface|null \$con{$trailingEmptyLine}";
    }

    /**
     * @param string|bool $withLeadingComma
     * @param string|bool $withTrailingComma
     *
     * @return string
     */
    protected function putConParam(bool|string $withLeadingComma = false, bool|string $withTrailingComma = false): string
    {
        return $this->omitConnectionInterfaceParam ? '' : $this->withCommas($withLeadingComma, '?ConnectionInterface $con = null', $withTrailingComma);
    }

    /**
     * @param string|bool $withLeadingComma
     * @param string|bool $withTrailingComma
     *
     * @return string
     */
    protected function putConVar(bool|string $withLeadingComma = false, bool|string $withTrailingComma = false): string
    {
        return $this->omitConnectionInterfaceParam ? '' : $this->withCommas($withLeadingComma, '$con', $withTrailingComma);
    }

    /**
     * @param string|bool $withLeadingComma
     * @param string $content
     * @param string|bool $withTrailingComma
     *
     * @return string
     */
    protected function withCommas(bool|string $withLeadingComma, string $content, bool|string $withTrailingComma): string
    {
        $leadingComma = is_string($withLeadingComma) ? $withLeadingComma : ($withLeadingComma ? ', ' : '');
        $trailingComma = is_string($withTrailingComma) ? $withTrailingComma : ($withTrailingComma ? ', ' : '');

        return "{$leadingComma}{$content}{$trailingComma}";
    }
}
