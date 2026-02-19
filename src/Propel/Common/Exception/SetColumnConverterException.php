<?php

declare(strict_types = 1);

namespace Propel\Common\Exception;

use Exception;
use InvalidArgumentException;

/**
 * Exception for Propel\Common\Util\SetColumnConverter class.
 */
class SetColumnConverterException extends InvalidArgumentException
{
    /**
     * @var mixed
     */
    protected $value;

    /**
     * @param string $message
     * @param mixed $value
     * @param int $code
     * @param \Exception|null $previous
     */
    public function __construct(string $message, $value, int $code = 0, ?Exception $previous = null)
    {
        $this->value = $value;
        parent::__construct($message, $code, $previous);
    }

    /**
     * Returns param "value".
     *
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }
}
