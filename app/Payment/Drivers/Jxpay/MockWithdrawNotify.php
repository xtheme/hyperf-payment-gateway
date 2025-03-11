<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Jxpay;

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

        // todo 请依据三方回调字段调整以下内容
        $mock = [
            'payout_id' => $order['trade_no'],
            'payout_cl_id' => $order['order_no'],
            'platform_id' => $order['merchant_id'],
            'amount' => $order['amount'],
            'fee' => 100,
            'status' => 3,
            'create_time' => $this->getTimestamp(),
            'update_time' => $this->getTimestamp(),
        ];

        $mock[$this->signField] = $this->getSignature($mock, $order['merchant_key']);

        return response()->json($mock);
    }
}
