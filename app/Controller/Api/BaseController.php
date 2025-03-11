<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AbstractController;
use App\Exception\ApiException;
use App\Payment\Contracts\PaymentGatewayInterface;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function Hyperf\Config\config;

class BaseController extends AbstractController
{
    #[Inject]
    protected PaymentGatewayInterface $platformGateway;

    protected function getDriverMethod(RequestInterface $request, string $method): ResponseInterface
    {
        $driver_name = $request->input('payment_platform') ?? ($request->route('payment_platform') ?? '');

        try {
            return $this->platformGateway->getDriver($driver_name)->{$method}($request);
        } catch (\Throwable $e) {
            $message = $e->getMessage();

            if ('dev' === config('app_env')) {
                $message .= ' ' . $e->getFile() . ' ' . $e->getLine();
            }

            throw new ApiException('錯誤：' . $message, 501);
        }
    }
}
