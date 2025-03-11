<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Hyperf\Set\HyperfSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/app',
        __DIR__ . '/config',
        // __DIR__ . '/factories',
        // __DIR__ . '/seeders',
        // __DIR__ . '/test',
    ])->withSets([
        HyperfSetList::HYPERF_31,
    ]);
