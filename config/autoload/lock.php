<?php

declare(strict_types=1);

return [
    'default' => [
        'driver' => FriendsOfHyperf\Lock\Driver\RedisLock::class,
        'constructor' => [
            'pool' => 'default',
        ],
    ],
    'file' => [
        'driver' => FriendsOfHyperf\Lock\Driver\FileSystemLock::class,
        'constructor' => [
            'config' => ['prefix' => 'lock:'],
        ],
    ],
    'database' => [
        'driver' => FriendsOfHyperf\Lock\Driver\DatabaseLock::class,
        'constructor' => [
            'pool' => 'default',
            'table' => 'locks',
        ],
    ],
];
