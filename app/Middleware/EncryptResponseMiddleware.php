<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\Encryption\Crypt;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function Hyperf\Config\config;

class EncryptResponseMiddleware extends BaseMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if (false === config('switch.encrypt_response')) {
            return $response;
        }

        $body_string = $response->getBody()->getContents();

        // 針對所有返回內容加密
        $body_string = Crypt::encrypt($body_string);

        return $this->response->raw($body_string);

        // 只針對 data 加密處理
        // $data = json_decode($body_string, true);
        // if (!empty($data['data'])) {
        //     // 如果 data 有值則加密
        //     $data['data'] = Crypt::encrypt(json_encode($data['data'], JSON_UNESCAPED_SLASHES), false);
        // } else {
        //     // data 轉型字串
        //     $data['data'] = '';
        // }
        // return $this->response->json($data);
    }
}
