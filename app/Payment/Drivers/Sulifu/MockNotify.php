<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Sulifu;

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

        // 依据三方回调字段调整以下内容
        $mock = [
            'tradeNo' => $order['order_no'],
            'topupAmount' => $this->convertAmount($order['amount']),
            'tradeStatus' => 1,
            'message' => '',
        ];

        // 參與簽名字段, 有順序性
        $signParams = [
            'tradeNo' => $mock['tradeNo'],
            'topupAmount' => $mock['topupAmount'],
        ];
        $mock[$this->signField] = $this->getSignature($signParams, $order['merchant_key']);

        return response()->json($mock);
    }
}
