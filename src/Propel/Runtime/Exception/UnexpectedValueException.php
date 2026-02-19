<?php

declare(strict_types = 1);

namespace Propel\Runtime\Exception;

use UnexpectedValueException as CoreUnexpectedValueException;

class UnexpectedValueException extends CoreUnexpectedValueException implements ExceptionInterface
{
}
