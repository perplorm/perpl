
    /**
     * @param array $array
     *
     * @return string
     */
    public static function serializeArray(array $array): string
    {
        return '| ' . implode(' | ', $array) . ' |';
    }

    /**
     * @param string $serializedArray
     *
     * @return array<string>
     */
    public static function unserializeArray(string $serializedArray): array
    {
        $unboundString = trim(substr($serializedArray, 2, -2));

        return $unboundString ? explode(' | ', $unboundString) : [];
    }
