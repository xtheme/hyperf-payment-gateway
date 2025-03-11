<?php

declare(strict_types=1);

use Hyperf\Contract\StdoutLoggerInterface;
use Psr\Log\LogLevel;

use function Hyperf\Support\env;

return [
    'app_name' => env('APP_NAME', 'skeleton'),
    'app_env' => env('APP_ENV', 'dev'),
    'app_host' => env('APP_HOST', ''),
    'scan_cacheable' => env('SCAN_CACHEABLE', false),
    StdoutLoggerInterface::class => [
        'log_level' => [
            LogLevel::ALERT,
            LogLevel::CRITICAL,
            LogLevel::DEBUG,
            LogLevel::EMERGENCY,
            LogLevel::ERROR,
            LogLevel::INFO,
            LogLevel::NOTICE,
            LogLevel::WARNING,
        ],
    ],
    'switch' => [
        'decrypt_request' => env('DECRYPT_REQUEST', false),
        'encrypt_response' => env('ENCRYPT_RESPONSE', false),
    ],
    'bot' => [
        'enable' => env('BOT_ENABLE', false),
        'driver' => env('BOT_DRIVER', 'telegram'),
        'api_url' => env('TELEGRAM_BOT_API_URL'),
        'chat_id' => env('TELEGRAM_BOT_CHAT_ID'),
        'token' => env('TELEGRAM_BOT_TOKEN'),
    ],
];
