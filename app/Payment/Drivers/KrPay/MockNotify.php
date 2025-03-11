<?php

declare(strict_types=1);

namespace App\Payment\Drivers\KrPay;

use App\Payment\Contracts\DriverMockInterface;
use Hyperf\Stringable\Str;
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
            'attach' => '',
            'create_time' => $this->getDateTime(),
            'goods_name' => '充值',
            'merchant_id' => $order['merchant_id'],
            'nonce_str' => Str::random(6),
            'out_trade_id' => $order['order_no'],
            'pay_time' => $this->getDateTime(),
            'status' => 2, // 1：未支付；2支付成功
            'transaction_id' => $order['trade_no'],
        ];

        $mock[$this->signField] = $this->getSignature($mock, $order['merchant_key']);

        return response()->json($mock);
    }
}