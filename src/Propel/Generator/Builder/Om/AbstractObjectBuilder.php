<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Propel\Generator\Builder\Om;

/**
 * Base class for object-building classes.
 *
 * This class is designed so that it can be extended the "standard" ObjectBuilder
 * and ComplexOMObjectBuilder. Hence, this class should not have any actual
 * template code in it -- simply basic logic & utility methods.
 */
abstract class AbstractObjectBuilder extends AbstractOMBuilder
{
    /**
     * Gets the baseClass path if specified for table/db.
     *
     * @return string|null
     */
    protected function getBaseClass(): ?string
    {
        return $this->getTable()->getBaseClass();
    }

    /**
     * Gets the interface path if specified for current table.
     *
     * @return string|null
     */
    protected function getInterface(): ?string
    {
        return $this->getTable()->getInterface();
    }

    /**
     * Whether to add the generic mutator methods (setByName(), setByPosition(), fromArray()).
     * This is based on the build property propel.addGenericMutators, and also whether the
     * table is read-only or an alias.
     *
     * @return bool
     */
    protected function isAddGenericMutators(): bool
    {
        $table = $this->getTable();

        return (!$table->isAlias() && $this->getBuildProperty('generator.objectModel.addGenericMutators') && !$table->isReadOnly());
    }

    /**
     * Whether to add the generic accessor methods (getByName(), getByPosition(), toArray()).
     * This is based on the build property propel.addGenericAccessors, and also whether the
     * table is an alias.
     *
     * @return bool
     */
    protected function isAddGenericAccessors(): bool
    {
        $table = $this->getTable();

        return (!$table->isAlias() && $this->getBuildProperty('generator.objectModel.addGenericAccessors'));
    }

    /**
     * @return bool
     */
    protected function hasDefaultValues(): bool
    {
        foreach ($this->getTable()->getColumns() as $col) {
            if ($col->getDefaultValue() !== null) {
                return true;
            }
        }

        return false;
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
         return parent::hasBehaviorModifier($hookName, 'ObjectBuilderModifier');
    }

    /**
     * Checks whether any registered behavior on that table has a modifier for a hook
     *
     * @param string $hookName The name of the hook as called from one of this class methods, e.g. "preSave"
     * @param string $script The script will be modified in this method.
     * @param string $tab
     *
     * @return void
     */
    public function applyBehaviorModifier(string $hookName, string &$script, string $tab = '        '): void
    {
        $this->applyBehaviorModifierBase($hookName, 'ObjectBuilderModifier', $script, $tab);
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
        return $this->getBehaviorContentBase($contentName, 'ObjectBuilderModifier');
    }
}
