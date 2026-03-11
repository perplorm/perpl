<?php

declare(strict_types = 1);

namespace Propel\Common\Config\Exception;

use InvalidArgumentException as CoreInvalidArgumentException;

class InvalidArgumentException extends CoreInvalidArgumentException implements ExceptionInterface
{
}
