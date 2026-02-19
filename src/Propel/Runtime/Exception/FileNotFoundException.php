<?php

declare(strict_types = 1);

namespace Propel\Runtime\Exception;

use function sprintf;

class FileNotFoundException extends RuntimeException implements ExceptionInterface
{
    /**
     * @param string $path The path to the file that was not found
     */
    public function __construct(string $path)
    {
        parent::__construct(sprintf('The file "%s" does not exist', $path));
    }
}
