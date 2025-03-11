<?php

declare(strict_types=1);

namespace App\Logger;

use Hyperf\Context\Context;
use Hyperf\Stringable\Str;
use Psr\Container\ContainerInterface;

use function Hyperf\Config\config;

class StdoutLoggerFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $logger = Log::get('stdout', 'stdout');

        if ('testing' != config('app_env')) {
            // 針對同個協程的日誌加入追蹤碼
            $logger->pushProcessor(function ($log) {
                $tracing_id = Context::get('tracing_id');

                if (!$tracing_id) {
                    $tracing_id = Str::orderedUuid()->toString();
                    Context::set('tracing_id', $tracing_id);
                }

                $log['extra']['request-id'] = $tracing_id;

                return $log;
            });
        }

        return $logger;
    }
}
