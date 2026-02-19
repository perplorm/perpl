<?php

declare(strict_types = 1);

namespace Propel\Runtime\Parser;

use Symfony\Component\Yaml\Yaml;

/**
 * YAML parser. Converts data between associative array and YAML formats
 */
class YamlParser extends AbstractParser
{
    /**
     * Converts data from an associative array to YAML.
     *
     * @param array $array Source data to convert
     * @param string|null $rootKey
     *
     * @return string Converted data, as a YAML string
     */
    #[\Override]
    public function fromArray(array $array, ?string $rootKey = null): string
    {
        return Yaml::dump($rootKey === null ? $array : [$rootKey => $array], 3);
    }

    /**
     * Alias for YamlParser::fromArray()
     *
     * @param array $array Source data to convert
     * @param string|null $rootKey
     *
     * @return string Converted data, as a YAML string
     */
    public function toYAML(array $array, ?string $rootKey = null): string
    {
        return $this->fromArray($array, $rootKey);
    }

    /**
     * Converts data from YAML to an associative array.
     *
     * @param string $data Source data to convert, as a YAML string
     * @param string|null $rootKey
     *
     * @return array Converted data
     */
    #[\Override]
    public function toArray(string $data, ?string $rootKey = null): array
    {
        $data = Yaml::parse($data);

        if ($rootKey === null) {
            return $data;
        }

        if (!isset($data[$rootKey])) {
            return [];
        }

        return $data[$rootKey];
    }

    /**
     * Alias for YamlParser::toArray()
     *
     * @param string $data Source data to convert, as a YAML string
     * @param string|null $rootKey
     *
     * @return array Converted data
     */
    public function fromYAML(string $data, ?string $rootKey = null): array
    {
        return $this->toArray($data, $rootKey);
    }
}
