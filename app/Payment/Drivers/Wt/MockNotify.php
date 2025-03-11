<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Wt;

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
            'payment_id' => $order['trade_no'],
            'payment_cl_id' => $order['order_no'],
            'platform_id' => $order['merchant_id'],
            'amount' => $order['amount'],
            'real_amount' => $order['amount'],
            'fee' => 0,
            'status' => 2,
            'create_time' => $this->getTimestamp(),
            'update_time' => $this->getTimestamp(),
        ];

        $mock[$this->signField] = $this->getSignature($mock, $order['merchant_key']);

        return response()->json($mock);
    }
}
