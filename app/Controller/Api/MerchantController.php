<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class MerchantController extends BaseController
{
    public function balance(RequestInterface $request): ResponseInterface
    {
        return $this->getDriverMethod($request, 'balance');
    }
}
