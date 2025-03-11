<?php

declare(strict_types=1);

namespace App\Payment\Drivers\BeBePay;

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

        // todo 请依据三方回调字段调整以下内容
        $mock = [
            'money' => $order['amount'],
            'subject' => $order['site_id'],
            'body' => null,
            'status' => 2,
            'mch_id' => $order['merchant_id'],
            'trade_no' => $order['trade_no'],
            'out_trade_no' => $order['order_no'],
            'original_trade_no' => '0',
            'notify_time' => $this->getTimestamp(),
        ];

        $mock[$this->signField] = $this->getSignature($mock, $order['merchant_key']);

        return response()->json($mock);
    }
}
