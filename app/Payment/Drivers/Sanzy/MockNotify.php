<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Sanzy;

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

        // 请依据三方回调字段调整以下内容
        $mock = [
            'payOrderId' => $order['trade_no'],
            'mchId' => $order['merchant_id'],
            'productId' => $order['payment_channel'],
            'mchOrderNo' => $order['order_no'],
            'amount' => $order['amount'],
            'status' => '1',
            'paySuccTime' => '',
        ];

        $mock[$this->signField] = $this->getSignature($mock, $order['merchant_key']);

        return response()->json($mock);
    }
}
