<?php

declare(strict_types = 1);

namespace Propel\Runtime\Connection;

/**
 * Class kept for BC sake - the functionality of the old PropelPDO class was moved to:
 * - ConnectionWrapper for the nested transactions, and logging
 * - PDOConnection for the PDO wrapper
 */
class PropelPDO extends ConnectionWrapper
{
}
