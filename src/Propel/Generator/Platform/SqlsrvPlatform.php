<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Propel\Generator\Platform;

/**
 * MS SQL Server using pdo_sqlsrv implementation.
 *
 * @author Benjamin Runnels
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
