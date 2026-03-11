<?php

declare(strict_types = 1);

namespace Propel\Common\Config\Exception;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException as BaseConfigurationException;

class InvalidConfigurationException extends BaseConfigurationException implements ExceptionInterface
{
}
