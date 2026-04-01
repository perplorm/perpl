<?php

declare(strict_types = 1);

namespace Propel\Tests\Attributes;

use function array_map;

/**
 * Attribute class for test data providers that test against files.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class ComparesGeneratedFile
{
    /**
     * Attributed method returns text data and a file name. 
     *
     * If $textBuilder is set, it identifies a public method on the object
     * which builds the text from input. In this case, $textPosition must identify
     * an array that can be applied to that method:
     * <code>
     * $text = $object->{$attribute->textBuilder}(...$data[$attribute->textPosition]);
     * </code>
     *
     * @param int $textPosition
     * @param int $fileNamePosition
     * @param string|null $textBuilder
     */
    public function __construct(
        public int $textPosition = 0,
        public int $fileNamePosition = 1,
        public string|null $textBuilder = null,
    ) {
    }

    /**
     * @param array $data
     *
     * @return string
     */
    public function getText(array $data): string
    {
        return $data[$this->textPosition];
    }

    /**
     * @param array $data
     *
     * @return string
     */
    public function getFileName(array $data): string
    {
        return $data[$this->fileNamePosition];
    }

    /**
     * @param array $data
     *
     * @return string
     */
    public function buildText(object $object, array $data): string
    {
        return $object->{$this->textBuilder}(...(array)$data[$this->textPosition]);
    }
}
