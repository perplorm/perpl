<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Propel\Generator\Behavior\OutputGroup;

use Propel\Generator\Builder\Om\ObjectCollectionBuilder;
use Propel\Generator\Model\Behavior;
use Propel\Generator\Model\MappingModel;
use Propel\Generator\Model\Table;

class OutputGroupBehavior extends Behavior
{
    /**
     * @var string
     */
    public const PARAMETER_OBJECT_COLLECTION_CLASS = 'object_collection_class';

    /**
     * Default parameters value
     *
     * @var array<string, mixed>
     */
    protected $parameters = [
        self::PARAMETER_OBJECT_COLLECTION_CLASS => null,
    ];

    /**
     * @var string
     */
    public const SCHEMA_ATTRIBUTE_OUTPUT_GROUP = 'outputGroup';

    /**
     * @var string
     */
    public const SCHEMA_ATTRIBUTE_IGNORE_GROUP = 'ignoreGroup';

    /**
     * @var string
     */
    public const SCHEMA_ATTRIBUTE_REF_OUTPUT_GROUP = 'refOutputGroup';

    /**
     * @var string
     */
    public const SCHEMA_ATTRIBUTE_REF_IGNORE_GROUP = 'refIgnoreGroup';

    /**
     * @var \Propel\Generator\Behavior\OutputGroup\OgObjectModifier|null
     */
    protected $objectModifier;

    /**
     * @var \Propel\Generator\Behavior\OutputGroup\OgTableMapModifier|null
     */
    protected $tableModifier;

    /**
     * @var \Propel\Generator\Behavior\OutputGroup\OgQueryModifier|null
     */
    protected $queryModifier;

    /**
     * @see \Propel\Generator\Model\Behavior::getObjectBuilderModifier()
     *
     * @return $this|\Propel\Generator\Behavior\OutputGroup\OgObjectModifier
     */
    #[\Override]
    public function getObjectBuilderModifier()
    {
        if ($this->objectModifier === null) {
            $this->objectModifier = new OgObjectModifier($this);
        }

        return $this->objectModifier;
    }

    /**
     * @see \Propel\Generator\Model\Behavior::getTableMapBuilderModifier()
     *
     * @return $this|\Propel\Generator\Behavior\OutputGroup\OgTableMapModifier
     */
    #[\Override]
    public function getTableMapBuilderModifier()
    {
        if ($this->tableModifier === null) {
            $this->tableModifier = new OgTableMapModifier($this);
        }

        return $this->tableModifier;
    }

    /**
     * @see \Propel\Generator\Model\Behavior::getQueryBuilderModifier()
     *
     * @return $this|\Propel\Generator\Behavior\OutputGroup\OgQueryModifier
     */
    #[\Override]
    public function getQueryBuilderModifier()
    {
        if ($this->queryModifier === null) {
            $this->queryModifier = new OgQueryModifier($this);
        }

        return $this->queryModifier;
    }

    /**
     * This method is automatically called on table behaviors when the database
     * model is finished. Override this method to add columns to the current
     * table.
     *
     * @see Database::doFinalInitialization()
     *
     * @return void
     */
    #[\Override]
    public function modifyTable(): void
    {
        $table = $this->getTable();
        if ($table->useGeneratedCollectionClass() || $table->hasCollectionClassAttribute()) {
             // output group method will be added in addObjectCollectionMethods() or is handled by user
            return;
        }

        $table->setCollectionClass(ObjectCollectionWithOutputGroups::class);
    }

    /**
     * Hook in {@see \Propel\Generator\Builder\Om\ObjectCollectionBuilder::addClassOpen()}.
     *
     * @param \Propel\Generator\Builder\Om\ObjectCollectionBuilder $builder
     *
     * @return string
     */
    public function addObjectCollectionMethods(ObjectCollectionBuilder $builder): string
    {
        return $this->renderLocalTemplate('toOutputGroupMethod');
    }

    /**
     * @return class-string
     */
    public function getObjectCollectionClass(): string
    {
        return $this->getParameter(self::PARAMETER_OBJECT_COLLECTION_CLASS)
            ?: ObjectCollectionWithOutputGroups::class;
    }

    /**
     * @see Propel\Generator\Model\Behavior\Behavior::renderTemplate()
     *
     * @param string $filename
     * @param array $vars
     *
     * @return string
     */
    public function renderLocalTemplate(string $filename, array $vars = []): string
    {
        $templatePath = $this->getDirname() . '/templates/';

        return $this->renderTemplate($filename, $vars, $templatePath);
    }

    /**
     * @param \Propel\Generator\Model\Table $table
     * @param \Propel\Generator\Model\MappingModel $model
     * @param string $groupAttributeName
     * @param string $ignoreGroupAttributeName
     *
     * @return array<string>
     */
    public function getGroupNames(
        Table $table,
        MappingModel $model,
        string $groupAttributeName = self::SCHEMA_ATTRIBUTE_OUTPUT_GROUP,
        string $ignoreGroupAttributeName = self::SCHEMA_ATTRIBUTE_IGNORE_GROUP
    ): array {
        $tableGroupNames = $this->parseListAttribute($table, self::SCHEMA_ATTRIBUTE_OUTPUT_GROUP);
        $localGroupNames = $this->parseListAttribute($model, $groupAttributeName);
        $ignoredGroupNames = $this->parseListAttribute($model, $ignoreGroupAttributeName);

        return array_merge($localGroupNames, array_filter($tableGroupNames, fn (string $name) => !in_array($name, $ignoredGroupNames)));
    }

    /**
     * @param \Propel\Generator\Model\MappingModel $model
     * @param string $attributeName
     *
     * @return array<string>
     */
    protected function parseListAttribute(MappingModel $model, string $attributeName): array
    {
        return $this->getDefaultValueForSet($model->getAttribute($attributeName, '')) ?? [];
    }
}
