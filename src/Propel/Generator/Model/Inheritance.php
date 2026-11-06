<?php

declare(strict_types = 1);

namespace Propel\Generator\Model;

use LogicException;
use function preg_replace;
use function rtrim;
use function strtoupper;

/**
 * A class for information regarding possible objects representing a table.
 */
class Inheritance extends MappingModel
{
    private string|null $key = null;

    private string|null $className = null;

    private string|null $package = null;

    private string|null $parentClassIdentifier = null;

    private Column|null $column = null;

    /**
     * Returns a key name.
     *
     * @return string|null
     */
    public function getKey(): ?string
    {
        return $this->key;
    }

    /**
     * Get constant names' safe value of the key name.
     *
     * @return string
     */
    public function getConstantSuffix(): string
    {
        $separator = PhpNameGenerator::STD_SEPARATOR_CHAR;

        return strtoupper(rtrim(preg_replace('/(\W|_)+/', $separator, $this->key), $separator));
    }

    /**
     * @param string $key
     *
     * @return void
     */
    public function setKey(string $key): void
    {
        $this->key = $key;
    }

    /**
     * Sets the parent column
     *
     * @param \Propel\Generator\Model\Column $column
     *
     * @return void
     */
    public function setColumn(Column $column): void
    {
        $this->column = $column;
    }

    /**
     * Returns the parent column.
     *
     * @return \Propel\Generator\Model\Column|null
     */
    public function getColumn(): ?Column
    {
        return $this->column;
    }

    /**
     * @throws \LogicException
     *
     * @return string
     */
    public function getClassName(): string
    {
        if (!$this->className) {
            throw new LogicException('Inheritance was not setup correctly, className is missing.');
        }

        return $this->className;
    }

    /**
     * @param string $name
     *
     * @return void
     */
    public function setClassName(string $name): void
    {
        $this->className = $name;
    }

    /**
     * @return string|null
     */
    public function getPackage(): ?string
    {
        return $this->package;
    }

    /**
     * @param string $package
     *
     * @return void
     */
    public function setPackage(string $package): void
    {
        $this->package = $package;
    }

    /**
     * @return string|null
     */
    public function getParentClassIdentifier(): ?string
    {
        return $this->parentClassIdentifier;
    }

    /**
     * @deprecated Use aptly named {@see static::parentClassIdentifier()}
     *
     * @return string|null
     */
    public function getAncestor(): ?string
    {
        return $this->getParentClassIdentifier();
    }

    /**
     * @param string $ancestor
     *
     * @return void
     */
    public function setAncestor(string $ancestor): void
    {
        $this->parentClassIdentifier = $ancestor;
    }

    /**
     * @return void
     */
    #[\Override]
    protected function setupObject(): void
    {
        $this->key = $this->getAttribute('key');
        $this->className = $this->getAttribute('class');
        $this->package = $this->getAttribute('package');
        $this->parentClassIdentifier = $this->getAttribute('extends');
    }

    /**
     * Check if this is the instance associated with the main table,
     * rather than a subclass extending the root class or another subclass.
     *
     * @return bool
     */
    public function isMainTableClass(): bool
    {
        return $this->parentClassIdentifier === null;
    }
}
