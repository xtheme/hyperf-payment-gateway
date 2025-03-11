<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Aipay;

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

            // 商戶 id
            'mchKey' => $order['merchant_id'],
            // 商戶訂單號
            'mchOrderNo' => $order['order_no'],
            // 系統訂單號
            'serialOrderNo' => $order['trade_no'],
            // 隨機字符串
            'nonce' => '123123123213123',
            // 狀態
            'payStatus' => ' PROCESSING',
            // 提單金額
            'amount' => $this->convertAmount($order['amount']),
            // 交易金額
            'realAmount' => $this->convertAmount($order['amount']),

        ];

        $mock[$this->signField] = $this->getSignature($mock, $order['merchant_key']);

        return response()->json($mock);
    }
}
