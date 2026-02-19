<?php

declare(strict_types = 1);

namespace Propel\Runtime\Adapter\MSSQL;

/**
 * dblib doesn't support transactions so we need to add a workaround for transactions, last insert ID, and quoting
 */
class MssqlDebugPDO extends MssqlPropelPDO
{
    /**
     * @var bool
     */
    protected $useDebugModeOnInstance = true;
}
