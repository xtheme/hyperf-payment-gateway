<?php

declare(strict_types=1);

namespace App\Payment\Drivers\HeiShi;

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
            'account_name' => $order['user_name'],
            'status' => $order['status'], // 返回支付系统订单号
            'pay_time' => $order['pay_time'], // 返回商户传入的订单号
            'pay_status' => $order['pay_status'], // 返回商户传入的订单号
            'amount' => $order['amount'], // 返回商户传入的订单号
            'pay_amount' => $order['pay_amount'], // 返回商户传入的订单号
            'out_trade_no' => $order['out_trade_no'], // 返回商户传入的订单号
            'trade_no' => $order['trade_no'], // 返回商户传入的订单号
            'fees' => $order['fees'], // 返回商户传入的订单号
            'timestamp' => $this->getTimestamp(),
            'thoroughfare' => '1000',
        ];

        $mock[$this->signField] = $this->getSignature($mock, $order['merchant_key']);

        return response()->json($mock);
    }
}
