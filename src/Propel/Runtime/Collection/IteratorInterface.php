<?php

declare(strict_types = 1);

namespace Propel\Runtime\Collection;

use Countable;
use Iterator;

/**
 * @template RowFormat
 * @extends \Iterator<(int|string), RowFormat>
 */
interface IteratorInterface extends Iterator, Countable
{
}
