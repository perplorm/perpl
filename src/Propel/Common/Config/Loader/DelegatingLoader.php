<?php

declare(strict_types = 1);

namespace Propel\Common\Config\Loader;

use Symfony\Component\Config\Loader\DelegatingLoader as BaseDelegatingLoader;

/**
 * Class DelegatingLoader
 */
class DelegatingLoader extends BaseDelegatingLoader
{
    public function __construct()
    {
        parent::__construct(new LoaderResolver());
    }
}
