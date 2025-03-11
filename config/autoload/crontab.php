<?php

declare(strict_types=1);

use function Hyperf\Support\env;

return [
    'enable' => env('CRONTAB_ENABLE', false),
    'crontab' => [
    ],
];
