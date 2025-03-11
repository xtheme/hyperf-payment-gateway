<?php

declare(strict_types=1);

use App\Middleware\CorsMiddleware;

return [
    'http' => [
        CorsMiddleware::class,
        Hyperf\Metric\Middleware\MetricMiddleware::class,
        Hyperf\Validation\Middleware\ValidationMiddleware::class,
    ],
];
