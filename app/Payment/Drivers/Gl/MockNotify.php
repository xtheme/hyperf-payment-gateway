<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Gl;

use App\Payment\Contracts\DriverMockInterface;
use Psr\Http\Message\ResponseInterface;

class MockNotify extends Driver implements DriverMockInterface
{
    /**
     * [Mock] 返回三方渠道回调参数
     */
    public function request(string $orderNo): ResponseInterface
    {
        // 查询订单
        $order = $this->getOrder($orderNo);

        $mock = [
            'merchant_id' => $order['merchant_id'],
            'payee_amount' => $this->convertAmount($order['amount']),
            'client_order_id' => $order['order_no'],
            'state' => 1009, // 1001=待充值, 1009=代收完成
        ];

        $mock[$this->signField] = $this->getSignature($mock, $order['merchant_key']);

        return response()->json($mock);
    }
}
