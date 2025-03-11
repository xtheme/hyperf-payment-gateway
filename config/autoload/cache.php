<?php

declare(strict_types=1);

use Hyperf\Codec\Packer\PhpSerializerPacker;

return [
    'default' => [
        'driver' => Hyperf\Cache\Driver\RedisDriver::class,
        'packer' => PhpSerializerPacker::class,
        'prefix' => '',
    ],
];
