<?php

declare(strict_types=1);

namespace App\Common;

use FriendsOfHyperf\Http\Client\Http;

use function Hyperf\Config\config;

class BotNotify
{
    public static function send(string $message): void
    {
        $enable = config('bot.enable', false);

        if (!$enable) {
            return;
        }

        $siteId = config('app_name');
        $env = config('app_env');
        $message = sprintf('[%s-%s] %s', $siteId, $env, $message);

        $driver = config('bot.driver', 'telegram');

        switch ($driver) {
            case 'telegram':
                self::telegram($message);

                break;

            default:
                stdLog()->error('BotNotify ' . $driver . ' not found');

                break;
        }
    }

    public static function telegram(string $message): void
    {
        $url = config('bot.api_url');
        $chatId = config('bot.chat_id');
        $token = config('bot.token');

        $url .= '/bot' . $token . '/sendMessage?chat_id=' . $chatId . '&text=' . urlencode($message);

        try {
            $client = Http::connectTimeout(5);
            $response = $client->get($url);
            stdLog()->debug($response->getBody()->getContents());
        } catch (\Throwable $e) {
            stdLog()->error('BotNotify ' . $e->getMessage());
        }
    }
}
