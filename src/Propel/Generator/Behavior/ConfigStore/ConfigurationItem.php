<?php

declare(strict_types = 1);

namespace Propel\Generator\Behavior\ConfigStore;

class ConfigurationItem
{
    /**
     * @var array
     */
    protected $behaviorAttributes;

    /**
     * @var array
     */
    protected $parameters;

    /**
     * @param array $behaviorAttributes
     * @param array $parameters
     */
    public function __construct(array $behaviorAttributes, array $parameters)
    {
        $this->behaviorAttributes = $behaviorAttributes;
        $this->parameters = $parameters;
    }

    /**
     * @return array
     */
    public function getBehaviorAttributes(): array
    {
        return $this->behaviorAttributes;
    }

    /**
     * @return array
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }
}
