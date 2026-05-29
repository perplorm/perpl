<?php

use Rector\Config\RectorConfig;

require_once __DIR__ . '/src/Rector/ReplaceGlobalPropelWithPerplRector.php';

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/../../src',
    ])
    ->withRules([
        \Utils\Rector\Rector\ReplaceGlobalPropelWithPerplRector::class,
    ]);
