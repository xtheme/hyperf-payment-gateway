<?php

declare(strict_types=1);

use Hyperf\Context\ApplicationContext;
use Hyperf\Context\Context;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Logger\LoggerFactory;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * 容器实例
 */
if (!function_exists('container')) {
    function container(): ContainerInterface
    {
        return ApplicationContext::getContainer();
    }
}

/**
 * redis 客户端实例
 */
if (!function_exists('redis')) {
    function redis(): Redis
    {
        try {
            return container()->get(Redis::class);
        } catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
            throw new RuntimeException('Redis not found.');
        }
    }
}

/**
 * 缓存实例 简单的缓存
 */
if (!function_exists('cache')) {
    function cache(): CacheInterface
    {
        try {
            return container()->get(CacheInterface::class);
        } catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
            throw new RuntimeException('CacheInterface not found.');
        }
    }
}

/**
 * 控制台日志
 */
if (!function_exists('stdLog')) {
    function stdLog(): StdoutLoggerInterface
    {
        try {
            return container()->get(StdoutLoggerInterface::class);
        } catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
            throw new RuntimeException('StdoutLoggerInterface not found.');
        }
    }
}

/**
 * 文件日志
 */
if (!function_exists('logger')) {
    function logger(): LoggerInterface
    {
        try {
            return container()->get(LoggerFactory::class)->make();
        } catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
            throw new RuntimeException('LoggerFactory not found.');
        }
    }
}

/**
 * 請求實例
 */
if (!function_exists('request')) {
    function request(): RequestInterface
    {
        try {
            return container()->get(RequestInterface::class);
        } catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
            throw new RuntimeException('RequestInterface not found.');
        }
    }
}

/**
 * 回應實例
 */
if (!function_exists('response')) {
    function response(): ResponseInterface
    {
        try {
            return container()->get(ResponseInterface::class);
        } catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
            throw new RuntimeException('ResponseInterface not found.');
        }
    }
}

if (!function_exists('verifyIp')) {
    function verifyIp($ip)
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    }
}

if (!function_exists('getClientIp')) {
    function getClientIp()
    {
        /**
         * @var ServerRequestInterface $request
         */
        $request = Context::get(ServerRequestInterface::class);
        $ip_addr = $request->getHeaderLine('x-forwarded-for');

        if (verifyIp($ip_addr)) {
            return $ip_addr;
        }
        $ip_addr = $request->getHeaderLine('remote-host');

        if (verifyIp($ip_addr)) {
            return $ip_addr;
        }
        $ip_addr = $request->getHeaderLine('x-real-ip');

        if (verifyIp($ip_addr)) {
            return $ip_addr;
        }
        $ip_addr = $request->getServerParams()['remote_addr'] ?? '0.0.0.0';

        if (verifyIp($ip_addr)) {
            return $ip_addr;
        }

        return '0.0.0.0';
    }
}

if (!function_exists('uuid')) {
    /**
     * @throws Exception
     */
    function uuid($length): string
    {
        if (function_exists('random_bytes')) {
            $uuid = bin2hex(random_bytes($length));
        } else {
            if (function_exists('openssl_random_pseudo_bytes')) {
                $uuid = bin2hex(openssl_random_pseudo_bytes($length));
            } else {
                $pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                $uuid = substr(str_shuffle(str_repeat($pool, 5)), 0, $length);
            }
        }

        return $uuid;
    }
}

if (!function_exists('filterEmoji')) {
    function filterEmoji($str): string
    {
        $str = preg_replace_callback(
            '/./u',
            function (array $match) {
                return strlen($match[0]) >= 4 ? '' : $match[0];
            },
            $str
        );
        $cleaned = strip_tags($str);

        return htmlspecialchars(($cleaned));
    }
}

/**
 * Dump variable.
 */
if (!function_exists('d')) {
    function d()
    {
        call_user_func_array('dump', func_get_args());
    }
}
