<?php

declare(strict_types=1);

namespace App\Payment\Drivers\SuperPay;

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
            // 交易類型
            'TYPE' => 'PA001',
            // 交易单状态 [success，error]
            'STATUS' => 'ACCEPTED',
            // 平台交易单号
            'SYS_CODE' => $order['trade_no'],
            // 商户交易单号
            'TRAN_CODE' => $order['order_no'],
            // 币别 (CNY、USDT)
            'currency_type' => 'CNY',
            // 提单金额
            'ORIG_AMT' => $this->convertAmount($order['amount']),
            // 交易金额
            'TRAN_AMT' => $this->convertAmount($order['amount']),

        ];

        $mock[$this->signField] = $this->getSignature($mock, $order['merchant_key']);

        return response()->json($mock);
    }
}
