<?php

declare(strict_types = 1);

namespace Propel\Generator\Exception;

use InvalidArgumentException as CoreInvalidArgumentException;

class InvalidArgumentException extends CoreInvalidArgumentException implements ExceptionInterface
{
}
