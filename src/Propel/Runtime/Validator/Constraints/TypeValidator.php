<?php

declare(strict_types=1);

namespace Propel\Runtime\Validator\Constraints;

use Symfony\Component\Validator\Constraints\TypeValidator as SymfonyTypeValidator;

/**
 * Validator for Type constraint - delegates to Symfony's TypeValidator
 */
class TypeValidator extends SymfonyTypeValidator
{
}
