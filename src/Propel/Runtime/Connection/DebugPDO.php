<?php

declare(strict_types = 1);

namespace Propel\Runtime\Connection;

/**
 * Connection wrapper class with debug enabled by default.
 *
 * Class kept for BC sake.
 */
class DebugPDO extends ConnectionWrapper
{
    /**
     * @var bool
     */
    protected $useDebugModeOnInstance = true;
}
