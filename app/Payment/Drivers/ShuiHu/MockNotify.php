<?php

declare(strict_types=1);

namespace App\Payment\Drivers\ShuiHu;

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
            'mchId' => $order['merchant_id'],
            'tradeNo' => $order['trade_no'], // 返回支付系统订单号
            'outTradeNo' => $order['order_no'], // 返回商户传入的订单号
            'originTradeNo' => \Hyperf\Stringable\Str::random(), // 返回支付通道的订单号
            'amount' => $order['amount'], // 订单金额 (单位: 分)
            'subject' => $order['site_id'], // 商品标题
            'body' => '', // 商品描述
            'extParam' => '', // 商户扩展参数，回调时会原样返回
            'state' => 0, // 订单状态：0=待支付，1=支付成功，2=支付失败
            'notifyTime' => Carbon::now()->getTimestampMs(), // 通知时间，13位时间戳
        ];

        $mock[$this->signField] = $this->getSignature($mock, $order['merchant_key']);

        return response()->json($mock);
    }
}
