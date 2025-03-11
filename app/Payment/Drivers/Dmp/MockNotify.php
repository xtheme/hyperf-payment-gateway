<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Dmp;

use App\Payment\Contracts\DriverMockInterface;
use Carbon\Carbon;
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
            'client_id' => $order['merchant_key'],
            'bill_number' => $order['order_no'],
            'status' => '已完成',
            'timestamp' => Carbon::now()->getTimestamp(),
            'amount' => $this->convertAmount($order['amount']),
        ];

        $mock[$this->signField] = $this->getSignature($mock, $order['merchant_key']);

        return response()->json($mock);
    }
}
