<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Propel\Runtime\Exception;

use UnexpectedValueException as CoreUnexpectedValueException;

/**
 * @author William Durand <william.durand1@gmail.com>
 */
class UnexpectedValueException extends CoreUnexpectedValueException implements ExceptionInterface
{
}
