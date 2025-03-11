<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Lvbu;

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
            'payOrderId' => $order['trade_no'],        // 三方交易號
            'mchId' => $order['merchant_id'],     // 商戶號
            'productId' => $order['payment_channel'], // 支付通道
            'mchOrderNo' => $order['order_no'],        // 商戶訂單號
            'amount' => $order['amount'],          // 商戶訂單號
            'status' => 1,                         // 支付状态,0-订单生成,1-支付中,2-支付成功,3-业务处理完成
            'paySuccTime' => $this->getTimestamp(), // 支付成功时间: 精确到毫秒
        ];

        $mock[$this->signField] = $this->getSignature($mock, $order['merchant_key']);

        return response()->json($mock);
    }
}
