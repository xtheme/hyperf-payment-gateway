<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Sulifu;

use App\Payment\Contracts\DriverMockInterface;
use Psr\Http\Message\ResponseInterface;

class MockWithdrawNotify extends Driver implements DriverMockInterface
{
    /**
     * [Mock] 返回三方渠道回调参数
     */
    public function request(string $orderNo): ResponseInterface
    {
        // 查询订单
        $order = $this->getOrder($orderNo, 'withdraw');

        // 请依据三方回调字段调整以下内容
        $mock = [
            'tradeNo' => $order['trade_no'],
            'orderAmount' => $order['amount'],
            'tradeStatus' => 1,
            'message' => '',
        ];

        // 參與簽名字段, 有順序性
        $signParams = [
            'tradeNo' => $mock['tradeNo'],
            'orderAmount' => $mock['orderAmount'],
        ];
        $mock[$this->signField] = $this->getSignature($signParams, $order['merchant_key']);

        return response()->json($mock);
    }
}
