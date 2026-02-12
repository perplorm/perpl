<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Propel\Runtime\Validator\Constraints;

use Symfony\Component\Validator\Constraints\LengthValidator as SymfonyLengthValidator;

/**
 * Validator for Length constraint - delegates to Symfony's LengthValidator
 */
class LengthValidator extends SymfonyLengthValidator
{
}
