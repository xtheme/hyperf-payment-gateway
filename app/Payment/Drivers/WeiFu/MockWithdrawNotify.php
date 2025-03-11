<?php

declare(strict_types=1);

namespace App\Payment\Drivers\WeiFu;

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

        // todo 依据三方回调字段调整以下内容
        $mock = [
            'merchant_id' => $order['merchant_id'],
            'trade_no' => $order['trade_no'],
            'order_no' => $order['order_no'],
            'amount' => $order['amount'],
            'status' => 3,
            'notify_time' => $this->getTimestamp(),
        ];

        $mock[$this->signField] = $this->getSignature($mock, $order['merchant_key']);

        return response()->json($mock);
    }
}
