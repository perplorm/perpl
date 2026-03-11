<?php

declare(strict_types = 1);

namespace Propel\Runtime\ActiveQuery\SqlBuilder;

class PreparedStatementDto
{
    /**
     * @var string
     */
    private $sqlStatement;

    /**
     * @var array<mixed>
     */
    private $parameters;

    /**
     * @param string $sqlStatement
     * @param array $parameters
     */
    public function __construct(string $sqlStatement, array &$parameters = [])
    {
        $this->sqlStatement = $sqlStatement;
        $this->parameters = $parameters;
    }

    /**
     * @return string
     */
    public function getSqlStatement(): string
    {
        return $this->sqlStatement;
    }

    /**
     * @return array<mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }
}
