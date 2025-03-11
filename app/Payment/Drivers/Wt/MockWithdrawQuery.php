<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Wt;

use App\Payment\Contracts\DriverMockInterface;
use Psr\Http\Message\ResponseInterface;

class MockWithdrawQuery extends Driver implements DriverMockInterface
{
    /**
     * [Mock] 模拟集成网关查询订单请求
     */
    public function request(string $orderNo): ResponseInterface
    {
        $order = $this->getOrder($orderNo, 'withdraw');

        $mock = [
            'site_id' => $order['site_id'],
            'payment_platform' => $order['payment_platform'],
            'merchant_id' => $order['merchant_id'],
            'merchant_key' => $order['merchant_key'],
            'endpoint_url' => $order['query_url'],
            'order_id' => $orderNo,
            'header_params' => json_decode($order['header_params']),
            'body_params' => json_decode($order['body_params']),
        ];

        return response()->json($mock);
    }
}
