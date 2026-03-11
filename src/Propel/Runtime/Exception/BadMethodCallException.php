<?php

declare(strict_types = 1);

namespace Propel\Runtime\Exception;

use BadMethodCallException as CoreBadMethodCallException;

class BadMethodCallException extends CoreBadMethodCallException implements ExceptionInterface
{
}
