<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Zcpay;

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
            'appid' => '000000000039',
            // 交易单状态 [0,1,2,3]
            'status' => '0',
            // 平台交易单号
            'plat_no' => $order['trade_no'],
            // 商户交易单号
            'order_no' => $order['order_no'],
            // 支付類型
            'channel_id' => 1,
            // 提单金额
            'money' => $this->convertAmount($order['amount']),
            // 交易金额
            'rec_money' => $this->convertAmount($order['amount']),

        ];

        $mock[$this->signField] = $this->getSignature($mock, $order['merchant_key']);

        return response()->json($mock);
    }
}
