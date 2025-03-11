<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Jupay;

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
            'porder' => $order['trade_no'],
            'orderid' => $order['order_no'],
            'money' => $order['amount'],
            'status' => 0, // 1 为成功，其余为失败
            'des' => '',
            'para' => '',
        ];

        $mock[$this->signField] = $this->getSignature($mock, $order['merchant_key']);

        return response()->json($mock);
    }
}
