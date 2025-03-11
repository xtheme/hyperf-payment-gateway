<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Exception\ApiException;
use App\Request\WithdrawCreateRequest;
use App\Request\WithDrawQueryRequest;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

class WithdrawController extends BaseController
{
    /**
     * @return ResponseInterface|PsrResponseInterface;
     */
    public function create(WithdrawCreateRequest $request): ResponseInterface|PsrResponseInterface
    {
        return $this->getDriverMethod($request, 'withdrawCreate');
    }

    public function query(WithDrawQueryRequest $request): ResponseInterface|PsrResponseInterface
    {
        return $this->getDriverMethod($request, 'withdrawQuery');
    }

    public function notify(RequestInterface $request): ResponseInterface|PsrResponseInterface
    {
        return $this->getDriverMethod($request, 'withdrawNotify');
    }

    public function mock(RequestInterface $request): mixed
    {
        $action = $request->route('action') ?? '';
        $order_no = $request->route('order_no') ?? '';

        [$driver_name, $order_id] = explode('_', $order_no);

        return match ($action) {
            'query' => $this->platformGateway->getDriver($driver_name)->mockWithdrawQuery($order_no),
            'notify' => $this->platformGateway->getDriver($driver_name)->mockWithdrawNotify($order_no),
            default => throw new ApiException('Unknown mock method'),
        };
    }
}
