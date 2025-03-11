<?php

declare(strict_types=1);

use function Hyperf\Support\env;

return [
    'default' => 'aes',

    'driver' => [
        'aes' => [
            'class' => App\Services\Encryption\Driver\AesDriver::class,
            'options' => [
                'key' => env('AES_KEY', ''),
                'cipher' => env('AES_CIPHER', 'AES-128-CBC'),
            ],
        ],
    ],
];
