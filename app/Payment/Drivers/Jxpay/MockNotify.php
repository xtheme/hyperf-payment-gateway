<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Jxpay;

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
            'status' => 'PAID',
            'tradeNo' => $order['trade_no'],
            'orderNo' => $order['order_no'],
            'userNo' => $order['user_id'],
            'userName' => $order['user_name'],
            'channelNo' => $order['payment_channel'],
            'storeId' => '',
            'storeType' => '',
            'tradeCode' => '',
            'payeeName' => '',
            'amount' => $this->convertAmount($order['amount']),
            'amountBeforeFixed' => $this->convertAmount($order['amount']),
            'discount' => 0,
            'lucky' => 0,
            'paid' => $this->convertAmount($order['amount']),
            'extra' => '',
        ];

        $mock[$this->signField] = $this->getSignature($mock, $order['merchant_key']);

        return response()->json($mock);
    }
}
