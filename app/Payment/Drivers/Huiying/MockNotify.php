<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Huiying;

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

        // Custom body params
        $body_params = json_decode($order['body_params'], true);

        $mock = [
            'payOrderId' => $order['trade_no'],
            'mchId' => $order['merchant_id'],
            'appId' => $body_params['appId'] ?? '',
            'productId' => $order['payment_channel'],
            'mchOrderNo' => $order['order_no'],
            'income' => $order['amount'],
            'amount' => $order['amount'],
            'status' => '1',
            'channelOrderNo' => '',
            'channelAttach' => '',
            'paySuccTime' => $this->getTimestamp(),
            'backType' => 1,
        ];

        $mock[$this->signField] = $this->getSignature($mock, $order['merchant_key']);

        return response()->json($mock);
    }
}
