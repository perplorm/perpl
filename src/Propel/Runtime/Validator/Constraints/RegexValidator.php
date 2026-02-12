<?php

declare(strict_types=1);

namespace Propel\Runtime\Validator\Constraints;

use Symfony\Component\Validator\Constraints\RegexValidator as SymfonyRegexValidator;

/**
 * Validator for Regex constraint - delegates to Symfony's RegexValidator
 */
class RegexValidator extends SymfonyRegexValidator
{
}
