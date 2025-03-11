<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Laifu;

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
            'amount' => $order['amount'],
            'channel' => $order['payment_channel'],
            'trade_no' => $order['trade_no'],
            'out_trade_no' => $order['order_no'],
            'order_status' => 1, // 支付状态,1 成功 其他失败
            'remark' => '',
        ];

        $mock[$this->signField] = $this->getSignature($mock, $order['merchant_key']);

        return response()->json($mock);
    }
}
