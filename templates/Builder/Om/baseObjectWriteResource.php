
    /**
     * @param resource|string|null $value
     *
     * @throws \RuntimeException
     *
     * @return resource|null
     */
    protected function writeResource($value)
    {
        if (!is_string($value)) {
            return $value;
        }
        $stream = fopen('php://memory', 'r+');
        if (is_bool($stream)) {
            throw new RuntimeException('Could not open memory stream');
        }
        fwrite($stream, $value);
        rewind($stream);

        return $stream;
    }
