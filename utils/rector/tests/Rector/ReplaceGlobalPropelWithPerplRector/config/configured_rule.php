<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
require_once __DIR__ . '/../../../../src/Rector/ReplaceGlobalPropelWithPerplRector.php';

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(\Utils\Rector\Rector\ReplaceGlobalPropelWithPerplRector::class);
};
