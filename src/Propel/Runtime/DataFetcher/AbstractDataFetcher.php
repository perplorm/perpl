<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Propel\Runtime\DataFetcher;

use function current;

/**
 * Abstract class for DataFetcher.
 */
abstract class AbstractDataFetcher implements DataFetcherInterface
{
    /**
     * @var mixed|null
     */
    protected $dataObject;

    /**
     * @param mixed $dataObject
     */
    public function __construct($dataObject)
    {
        $this->setDataObject($dataObject);
    }

    /**
     * @param mixed|null $dataObject
     *
     * @return void
     */
    #[\Override]
    public function setDataObject($dataObject): void
    {
        $this->dataObject = $dataObject;
    }

    /**
     * @return mixed|null
     */
    #[\Override]
    public function getDataObject()
    {
        return $this->dataObject;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function fetchColumn($index = null)
    {
        /** @var array|null $next */
        $next = $this->fetch();

        if (!$next) {
            return null;
        }

        return $index === null ? current($next) : ($next[$index] ?? null);
    }
}
