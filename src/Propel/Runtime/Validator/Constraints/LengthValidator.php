<?php

declare(strict_types = 1);

namespace Propel\Runtime\Validator\Constraints;

use Symfony\Component\Validator\Constraints\LengthValidator as SymfonyLengthValidator;

/**
 * Validator for Length constraint - delegates to Symfony's LengthValidator
 */
class LengthValidator extends SymfonyLengthValidator
{
}
