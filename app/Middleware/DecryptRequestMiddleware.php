<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\Encryption\Crypt;
use Hyperf\Context\Context;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function Hyperf\Config\config;

class DecryptRequestMiddleware extends BaseMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (true === config('switch.decrypt_request')) {
            // 未解密字串
            $rawBody = $request->getBody()->getContents();

            // 解密字串
            $decrypt = Crypt::decrypt($rawBody);
            $params = json_decode($decrypt, true);

            $request = Context::override(ServerRequestInterface::class, function (ServerRequestInterface $request) use ($params) {
                return $request->withParsedBody($params);
            });
        }

        return $handler->handle($request);
    }
}
