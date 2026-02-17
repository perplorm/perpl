<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

 declare(strict_types = 1);

namespace Propel\Runtime\ActiveQuery\ColumnResolver;

 use Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression;
use function preg_match;
use function preg_replace_callback;
use function strlen;

class NormalizedFilterExpression
{
    /**
     * @var string
     */
    public const COLUMN_LITERAL_PATTERN = '/[\w§\\\]+\.\w*[A-Za-z]\w*/';

    /**
     * @var string
     */
    protected $normalizedFilterExpression;

    /**
     * Columns found in statement
     *
     * @var array<\Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression>
     */
    protected $replacedColumns = [];

    /**
     * @param string $normalizedFilterExpression
     * @param array $replacedColumns
     */
    protected function __construct(string $normalizedFilterExpression, array $replacedColumns)
    {
        $this->normalizedFilterExpression = $normalizedFilterExpression;
        $this->replacedColumns = $replacedColumns;
    }

    /**
     * @return string
     */
    public function getNormalizedFilterExpression(): string
    {
        return $this->normalizedFilterExpression;
    }

    /**
     * @return array<\Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression>
     */
    public function getReplacedColumns(): array
    {
        return $this->replacedColumns;
    }

    /**
     * @return \Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression|null
     */
    public function findFirstColumnWithColumnMap(): ?AbstractColumnExpression
    {
        return AbstractColumnExpression::findFirstColumnExpressionWithColumnMap($this->replacedColumns);
    }

    /**
     * Replaces complete column names (like Article.AuthorId) in an SQL clause
     * by their exact Propel column fully qualified name (e.g. article.author_id).
     *
     * Ignores column names inside quotes.
     *
     * <code>
     * 'CONCAT(Book.AuthorID, "Book.AuthorID") = ?'
     *   => 'CONCAT(book.author_id, "Book.AuthorID") = ?'
     * </code>
     *
     * @param string $sql SQL clause to inspect
     * @param callable(string):\Propel\Runtime\ActiveQuery\ColumnResolver\ColumnExpression\AbstractColumnExpression $columnProcessor
     *
     * @return \Propel\Runtime\ActiveQuery\ColumnResolver\NormalizedFilterExpression
     */
    public static function normalizeExpression(string $sql, $columnProcessor)
    {
        $replacedColumns = [];
        $pushColumnAndReturnName = function (array $matches) use (&$replacedColumns, $columnProcessor): string {
            $columnExpression = $columnProcessor($matches[0]);
            $replacedColumns[] = $columnExpression;

            return $columnExpression->getColumnExpressionInQuery(true);
        };

        //$sql = trim($sql); // Tests don't like this
        $parsedString = ''; // collects the result
        $stringToTransform = ''; // collects substrings from input to be processed before written to result
        $len = strlen($sql);
        $pos = 0;
        // go through string, write text in quotes to output, rest is written after transform
        while ($pos < $len) {
            $char = $sql[$pos];

            if (($char !== "'" && $char !== '"') || ($pos > 0 && $sql[$pos - 1] === '\\')) {
                $stringToTransform .= $char;
            } else {
                // start of quote, process what was found so far
                $parsedString .= preg_replace_callback(static::COLUMN_LITERAL_PATTERN, $pushColumnAndReturnName, $stringToTransform);
                $stringToTransform = '';

                // copy to result until end of quote
                $openingQuoteChar = $char;
                $parsedString .= $char;
                while (++$pos < $len) {
                    $char = $sql[$pos];
                    $parsedString .= $char;
                    if ($char === $openingQuoteChar && $sql[$pos - 1] !== '\\') {
                        break;
                    }
                }
            }
            $pos++;
        }

        if ($stringToTransform) {
            $parsedString .= preg_replace_callback(static::COLUMN_LITERAL_PATTERN, $pushColumnAndReturnName, $stringToTransform);
        }

        return new self($parsedString, $replacedColumns);
    }

    /**
     * @param string $columnOrClause
     *
     * @return bool
     */
    public static function isColumnLiteral(string $columnOrClause): bool
    {
        $pattern = <<<REGEX
~
^\s*            # any number of spaces after start
[\'"]?          # optional quotes
((              # optional schema and table before column name (has to end with '.')
  [\w\\\]+      # opt1: PHP class name, word with slashes, i.e. \Bookstore\Book
  |             # opt2:
  (\w*§)?       # optional sqlite schema ends with '§' i.e. bookstore§ 
  \w+(\.\w+)*   # words separated by dots
 )\.)?          # opt1 and opt2 have to end with dot or not be there
\w+             # word (the column name)
[\'"]?          # optional quotes
\s*$            # any number of spaces before end
~ix
REGEX;

        return (bool)preg_match($pattern, $columnOrClause);
    }
}
