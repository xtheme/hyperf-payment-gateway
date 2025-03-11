<?php

declare(strict_types=1);

namespace App\Common;

class Log
{
    public static function emergency(string $message, array $context = []): void
    {
        stdLog()->emergency($message, $context);
        BotNotify::send($message);
    }

    public static function alert(string $message, array $context = []): void
    {
        stdLog()->alert($message, $context);
        BotNotify::send($message);
    }

    public static function critical(string $message, array $context = []): void
    {
        stdLog()->critical($message, $context);
        BotNotify::send($message);
    }

    public static function error(string $message, array $context = []): void
    {
        stdLog()->error($message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        stdLog()->warning($message, $context);
    }

    public static function notice(string $message, array $context = []): void
    {
        stdLog()->notice($message, $context);
        BotNotify::send($message);
    }

    public static function info(string $message, array $context = []): void
    {
        stdLog()->info($message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        stdLog()->debug($message, $context);
    }
}
