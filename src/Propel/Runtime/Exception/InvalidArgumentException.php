<?php

declare(strict_types = 1);

namespace Propel\Runtime\Exception;

use InvalidArgumentException as CoreInvalidArgumentException;

class InvalidArgumentException extends CoreInvalidArgumentException implements ExceptionInterface
{
}
