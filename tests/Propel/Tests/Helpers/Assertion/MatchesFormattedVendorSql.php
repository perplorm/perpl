<?php

declare(strict_types = 1);

namespace Propel\Tests\Helpers\Assertion;

use PHPUnit\Framework\Constraint\Constraint;
use Propel\Tests\TestCase;
use function in_array;
use function sprintf;
use function strlen;
use function substr;
use function substr_replace;
use function var_export;

final class MatchesFormattedVendorSql extends Constraint
{
    /**
     * @var array<string>
     */
    private const FORMAT_CHARS = [' ', "\n"];

    private string $formattedVendorSql;

    private int $expectedCharPos = 0;

    private int $actualCharPos = 0;

    public function __construct(string $formattedVendorSql)
    {
        $this->formattedVendorSql = TestCase::toVendorSql($formattedVendorSql);
    }

    #[\Override]
    public function toString(): string
    {
        return 'SQL matches reference';
    }

    #[\Override]
    protected function matches(mixed $actualSql): bool
    {
        $expectedSql = $this->formattedVendorSql;

        $this->expectedCharPos = 0;
        $this->actualCharPos = 0;

        $expectedInputLength = strlen($expectedSql);
        $actualInputLength = strlen($actualSql);

        // inside quotes
        while ($this->expectedCharPos < $expectedInputLength && $this->actualCharPos < $actualInputLength) {
            $actualChar = $actualSql[$this->actualCharPos];
            $expectedChar = $expectedSql[$this->expectedCharPos];

            if ($actualChar === $expectedChar || ($actualChar === ' ' && $expectedChar === "\n")) {
                $this->expectedCharPos++;
                $this->actualCharPos++;

                continue;
            }

            // cannot pad between words (when previous and current are alnum)
            if (ctype_alnum($actualChar) && $this->actualCharPos !== 0 && ctype_alnum($actualSql[$this->actualCharPos-1])) {
                return false;
            }

            while ($actualChar !== $expectedChar && $this->expectedCharPos < $expectedInputLength && in_array($expectedChar, self::FORMAT_CHARS)) {
                $expectedChar = $expectedSql[++$this->expectedCharPos];
            }

            if ($actualChar !== $expectedChar) {
                return false;
            }
        }

        for (; $this->expectedCharPos < $expectedInputLength; $this->expectedCharPos++) {
            if (!in_array($expectedChar, self::FORMAT_CHARS)) {
                return false;
            }
        }

        return $this->actualCharPos >= $actualInputLength;
    }

    #[\Override]
    protected function failureDescription(mixed $other): string
    {
        $diffIndicator = '[------>]';
        $expectedWithDiffIndicator = substr_replace($this->formattedVendorSql, $diffIndicator, $this->expectedCharPos, 0);
        $expected = $this->actualCharPos < strlen($other)
            ? var_export(substr($other, $this->actualCharPos), true)
            : '<end of input>';

        return sprintf(
            "actual SQL:\n\n%s\n\nmatches expected SQL:\n\n%s\n\nexpected (after $diffIndicator): %s",
            var_export($other, true),
            var_export($expectedWithDiffIndicator, true),
            $expected,
        );
    }
}
