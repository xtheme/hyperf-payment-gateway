<?php

declare(strict_types=1);

namespace App\Payment\Drivers\S2O;

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
            // 交易单状态 [success，error]
            'status' => 'success',
            // 交易单状态描述
            'message' => '交易成功',
            // 平台交易单号
            'order_sn' => $order['trade_no'],
            // 商户交易单号
            'cus_order_sn' => $order['order_no'],
            // 币别 (CNY、USDT)
            'currency_type' => 'CNY',
            // 提单金额
            'original_amount' => $this->convertAmount($order['amount']),
            // 交易金额
            'order_amount' => $this->convertAmount($order['amount']),
            // 实收金额
            'receive_amount' => $this->convertAmount($order['amount']),
            // 转换系统预设币值金额
            'exchange_amount' => $this->convertAmount($order['amount']),
        ];

        $mock[$this->signField] = $this->getSignature($mock, $order['merchant_key']);

        return response()->json($mock);
    }
}
