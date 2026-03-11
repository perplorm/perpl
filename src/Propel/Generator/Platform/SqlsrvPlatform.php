<?php

declare(strict_types = 1);

namespace Propel\Generator\Platform;

/**
 * MS SQL Server using pdo_sqlsrv implementation.
 */
class SqlsrvPlatform extends MssqlPlatform
{
    /**
     * @see Platform#getMaxColumnNameLength()
     *
     * @return int
     */
    #[\Override]
    public function getMaxColumnNameLength(): int
    {
        return 128;
    }
}
