<?php

declare(strict_types=1);

namespace App\Logger;

use Hyperf\Logger\LoggerFactory;

class Log
{
    public static function get(string $name = 'app', string $group = 'default')
    {
        return container()->get(LoggerFactory::class)->get($name, $group);
    }
}
