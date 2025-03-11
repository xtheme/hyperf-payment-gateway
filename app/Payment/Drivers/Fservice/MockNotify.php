<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Fservice;

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

        $mock = [
            'fxid' => $order['merchant_id'],     // 唯一号，登入商户后台即可查看
            'fxddh' => $order['order_no'],        // 平台返回商户提交的订单号
            'fxorder' => $order['trade_no'],        // 平台内部生成的订单号
            'fxdesc' => $order['site_id'],         // utf-8 编码
            'fxfee' => $order['amount'],          // 支付金额
            'fxattch' => $order['order_no'],        // 原样返回，utf-8 编码
            'fxstatus' => '0',                       // 1 代表支付成功
            'fxtime' => $this->getTimestamp(), // 支付成功时的时间，unix 时间戳
        ];

        $mock[$this->signField] = $this->getNotifySignature($mock, $order['merchant_key']);

        return response()->json($mock);
    }
}
