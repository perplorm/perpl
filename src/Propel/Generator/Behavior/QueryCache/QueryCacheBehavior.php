<?php

declare(strict_types = 1);

namespace Propel\Generator\Behavior\QueryCache;

use Propel\Generator\Builder\Om\AbstractOMBuilder;
use Propel\Generator\Model\Behavior;
use function trigger_deprecation;

/**
 * Speeds up queries on a model by caching the query
 */
class QueryCacheBehavior extends Behavior
{
    /**
     * Default parameters value
     *
     * @var array<string, mixed>
     */
    protected $parameters = [
        'backend' => 'apc',
        'lifetime' => '3600',
    ];

    /**
     * @var string
     */
    private $tableClassName;

    /**
     * @param \Propel\Generator\Builder\Om\AbstractOMBuilder $builder
     *
     * @return string
     */
    public function queryAttributes(AbstractOMBuilder $builder): string
    {
        $script = "protected \$queryKey = '';
";
        switch ($this->getParameter('backend')) {
            case 'backend':
                $script .= "protected static \$cacheBackend = [];
            ";

                break;
            case 'apc':
                break;
            case 'custom':
            default:
                $script .= "protected static \$cacheBackend;
            ";

                break;
        }

        return $script;
    }

    /**
     * @param \Propel\Generator\Builder\Om\AbstractOMBuilder $builder
     *
     * @return string
     */
    public function queryMethods(AbstractOMBuilder $builder): string
    {
        $builder->declareClasses('\Propel\Runtime\Propel');
        $this->tableClassName = $builder->getTableMapClassName();
        $script = '';
        $this->addSetQueryKey($script);

        return $script;
    }

    /**
     * @param string $script
     *
     * @return void
     */
    protected function addSetQueryKey(string &$script): void
    {
        $script .= "
public function setQueryKey(\$key)
{
    \$this->queryKey = \$key;
    trigger_deprecation('Perpl', '2.8.0', 'setQueryKey() is deprecated. Please remove this function from your queries.');
    trigger_deprecation('Perpl', '2.8.0', 'The query_cache behavior is deprecated. Please remove this behavior from your schema definition.');
    return \$this;
}
";
    }

}
