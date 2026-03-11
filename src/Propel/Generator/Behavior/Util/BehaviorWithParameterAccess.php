<?php

declare(strict_types = 1);

namespace Propel\Generator\Behavior\Util;

use Propel\Common\Util\SetColumnConverter;
use Propel\Generator\Exception\SchemaException;
use Propel\Generator\Model\Behavior;
use function array_map;
use function count;
use function in_array;
use function is_array;
use function is_numeric;
use function is_string;
use function reset;
use function strtolower;
use function trim;

abstract class BehaviorWithParameterAccess extends Behavior
{
    /**
     * @psalm-return ($defaultValue is string ? string : string|null)
     *
     * @param string $name
     * @param string|null $defaultValue
     *
     * @return string|null
     */
    #[\Override]
    public function getParameter(string $name, ?string $defaultValue = null): ?string
    {
        $val = $this->parameters[$name] ?? null;

        return is_string($val) ? trim($val) : $defaultValue; // means empty space (' ') cannot be a value, seems ok.
    }

    /**
     * @psalm-return ($defaultValue is bool ? bool : bool|null)
     *
     * @param string $parameterName
     * @param bool|null $defaultValue
     *
     * @return bool|null
     */
    public function getParameterBool(string $parameterName, ?bool $defaultValue = null): ?bool
    {
        $val = $this->getParameter($parameterName);

        return !$val ? $defaultValue : in_array(strtolower($val), ['true', '1']);
    }

    /**
     * @psalm-return ($defaultValue is int ? int : int|null)
     *
     * @param string $parameterName
     * @param int|null $defaultValue
     *
     * @throws \Propel\Generator\Exception\SchemaException
     *
     * @return int|null
     */
    public function getParameterInt(string $parameterName, ?int $defaultValue = null): ?int
    {
        $val = $this->getParameter($parameterName);
        if ($val === null) {
            return $defaultValue;
        }
        if (!is_numeric($val)) {
            throw new SchemaException("Parameter $parameterName should be numeric, but is '$val'");
        }

        return (int)$val;
    }

    /**
     * @psalm-return ($defaultValue is array ? array : array|null)
     *
     * @param string $parameterName
     * @param array|null $defaultValue
     * @param callable|null $mapper
     *
     * @return array|null
     */
    public function getParameterCsv(string $parameterName, ?array $defaultValue = [], ?callable $mapper = null): ?array
    {
        $valString = $this->getParameter($parameterName);
        if (!$valString) {
            return $defaultValue;
        }
        $valList = $this->explodeCsv($valString);

        return $mapper ? array_map($mapper, $valList) : $valList;
    }

    /**
     * @param string $parameterName
     * @param mixed $defaultValue
     *
     * @return mixed|true
     */
    public function getParameterTrueOrValue(string $parameterName, $defaultValue = null)
    {
        $val = $this->parameters[$parameterName] ?? null;
        $isTrue = is_string($val) && $this->getParameterBool($parameterName);

        return $isTrue ?: ($this->parameters[$parameterName] ?? $defaultValue);
    }

    /**
     * @psalm-return ($defaultValue is array ? array|true : array|true|null)
     *
     * @param string $parameterName
     * @param array|null $defaultValue
     * @param callable|null $mapper
     *
     * @return array|true|null
     */
    public function getParameterTrueOrCsv(string $parameterName, ?array $defaultValue = null, $mapper = null)
    {
        $val = $this->getParameterBool($parameterName);

        return $val ?: $this->getParameterCsv($parameterName, $defaultValue, $mapper);
    }

    /**
     * @param string $stringValue
     *
     * @return array<string>|null
     */
    protected function explodeCsv(string $stringValue): ?array
    {
        return SetColumnConverter::itemsCsvToArray($stringValue) ?: null;
    }

    /**
     * Unwraps an array created by `<parameter-list>`.
     *
     * @psalm-param [array]|array<mixed> $parameterListOrList
     *
     * @param array $parameterListOrList
     *
     * @return array
     */
    protected function unwrapParameterList(array $parameterListOrList): array
    {
        $firstElement = reset($parameterListOrList);
        $assumeParameterList = count($parameterListOrList) === 1 && is_array($firstElement);

        return $assumeParameterList ? $firstElement : $parameterListOrList;
    }
}
