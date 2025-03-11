<?php

declare(strict_types=1);

use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;

use function Hyperf\Support\env;

$appEnv = env('APP_ENV', 'dev');

// 線上環境需輸出 Json 格式日誌
if ('prod' != $appEnv) {
    $formatter = [
        'class' => LineFormatter::class,
        'constructor' => [
            'format' => null,
            'dateFormat' => 'Y-m-d H:i:s',
            'allowInlineLineBreaks' => true,
            'includeStacktraces' => true,
            'ignoreEmptyContextAndExtra' => true,
        ],
    ];
} else {
    $formatter = [
        'class' => JsonFormatter::class,
        'constructor' => [],
    ];
}

return [
    'default' => [
        'handler' => [
            'class' => RotatingFileHandler::class,
            'constructor' => [
                'filename' => BASE_PATH . '/runtime/logs/hyperf.log',
                'level' => Level::fromName(env('LOG_LEVEL', 'debug')),
            ],
        ],
        'formatter' => $formatter,
    ],
    'stdout' => [
        'handler' => [
            'class' => StreamHandler::class,
            'constructor' => [
                'stream' => 'php://stdout',
                'level' => Level::fromName(env('LOG_LEVEL', 'debug')),
            ],
        ],
        'formatter' => $formatter,
    ],
];
