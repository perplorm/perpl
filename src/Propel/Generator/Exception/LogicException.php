<?php

declare(strict_types = 1);

namespace Propel\Generator\Exception;

use LogicException as CoreLogicException;

class LogicException extends CoreLogicException implements ExceptionInterface
{
}
