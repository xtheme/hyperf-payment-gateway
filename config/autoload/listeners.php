<?php

declare(strict_types=1);

use Hyperf\Metric\Listener\DBPoolWatcher;
use Hyperf\Metric\Listener\QueueWatcher;
use Hyperf\Metric\Listener\RedisPoolWatcher;

return [
    Hyperf\ExceptionHandler\Listener\ErrorExceptionHandler::class,
    Hyperf\Command\Listener\FailToHandleListener::class,
    DBPoolWatcher::class,
    RedisPoolWatcher::class,
    QueueWatcher::class,
    // Hyperf\ExceptionHandler\Listener\ErrorExceptionHandler::class
];
