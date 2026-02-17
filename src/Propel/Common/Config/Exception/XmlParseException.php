<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Propel\Common\Config\Exception;

use function count;
use const LIBXML_ERR_ERROR;
use const LIBXML_ERR_FATAL;
use const LIBXML_ERR_WARNING;

class XmlParseException extends RuntimeException implements ExceptionInterface
{
    /**
     * Create an exception based on LibXMLError objects
     *
     * @see http://www.php.net/manual/en/class.libxmlerror.php
     *
     * @param array $errors Array of LibXMLError objects
     */
    public function __construct(array $errors)
    {
        $numErrors = count($errors);

        $message = '';
        if ($numErrors === 1) {
            $message = 'An error occurred ';
        } elseif ($numErrors > 1) {
            $message = 'Some errors occurred ';
        }
        $message .= "while parsing XML configuration file:\n";

        foreach ($errors as $error) {
            $message .= ' - ';

            switch ($error->level) {
                case LIBXML_ERR_WARNING:
                    $message .= "Warning $error->code: ";

                    break;
                case LIBXML_ERR_ERROR:
                    $message .= "Error $error->code: ";

                    break;
                case LIBXML_ERR_FATAL:
                    $message .= "Fatal Error $error->code: ";

                    break;
            }

            $message .= $error->message;
        }

        parent::__construct($message);
    }
}
