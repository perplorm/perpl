<?php

declare(strict_types = 1);

namespace Propel\Generator\Behavior\QueryCache;

use Propel\Generator\Builder\Om\AbstractOMBuilder;
use Propel\Generator\Model\Behavior;
use function trigger_deprecation;

/**
 * @deprecated No performance gain in Perpl.
 */
class QueryCacheBehavior extends Behavior
{
    public function __construct()
    {
        trigger_deprecation('perpl', '2.9.0', 'The query_cache behavior is deprecated. Perpl is faster without it. See https://github.com/perplorm/perpl/issues/95#issuecomment-3618764440');
    }

    /**
     * @param \Propel\Generator\Builder\Om\AbstractOMBuilder $builder
     *
     * @return string
     */
    public function queryMethods(AbstractOMBuilder $builder): string
    {
        $builder->declareGlobalFunction('trigger_deprecation');

        return $this->buildDeprecatedFunction('setQueryKey($key)', '$this')
            . $this->buildDeprecatedFunction('getQueryKey()', '""')
            . $this->buildDeprecatedFunction('cacheContains($key)', 'false')
            . $this->buildDeprecatedFunction('cacheStore($key, $value, $lifetime = -1)', 'null')
            . $this->buildDeprecatedFunction('cacheFetch($key)', 'null');
    }

    /**
     * @param string $methodHead
     * @param string $returnValue
     *
     * @return string
     */
    protected function buildDeprecatedFunction(string $methodHead, string $returnValue): string
    {
        return "
/**
 * @deprecated Method was added by deprecated query_cache behavior. Perpl is faster without it.
 */
public function $methodHead
{
    trigger_deprecation('perpl', '2.9.0', 'The query_cache behavior is deprecated. Perpl is faster without it. See https://github.com/perplorm/perpl/issues/95#issuecomment-3618764440');

    return $returnValue;
}\n";
    }
}
