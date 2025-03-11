<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Exception\ApiException;
use FriendsOfHyperf\Lock\Exception\LockTimeoutException;
use Hyperf\Collection\Arr;
use Hyperf\HttpServer\Router\Dispatched;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function FriendsOfHyperf\Lock\lock;
use function Hyperf\Support\optional;

class RateLimiterMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $lock_key = $this->genCacheKey($request);
        $lock = lock($lock_key, 10);

        try {
            $lock->block(3);

            return $handler->handle($request);
        } catch (LockTimeoutException $e) {
            throw new ApiException('系统繁忙中请稍候再试！');
        } finally {
            optional($lock)->release();
        }
    }

    protected function genCacheKey(ServerRequestInterface $request): string
    {
        $dispatched = $request->getAttribute(Dispatched::class);
        $route = $dispatched->handler->route ?? '';
        $route = trim($route, '/');
        $route = str_replace('/', ':', $route);

        $body = $request->getParsedBody();
        $body = Arr::flatten($body);
        $body = implode('', $body);

        return $route . ':' . substr(md5($body), 8, 16);
    }
}
