<?php

declare(strict_types = 1);

namespace Propel\Runtime\Exception;

use LogicException as CoreLogicException;

class LogicException extends CoreLogicException implements ExceptionInterface
{
}
