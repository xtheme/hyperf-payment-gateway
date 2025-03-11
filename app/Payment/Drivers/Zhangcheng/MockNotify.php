<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Zhangcheng;

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
            'payOrderId' => $order['trade_no'],
            // 商戶 id
            'mchId' => $order['merchant_id'],
            // 应用 id
            'appId' => '91fcc37226104ca38956040effb5c792',
            // 產品 id
            'productId' => $order['payment_channel'],
            // 商戶訂單號
            'mchOrderNo' => $order['order_no'],
            // 支付金額
            'amount' => $order['amount'],
            // 支付状态,0-订单生成,1-支付中,2- 支付成功,3-业务处理完成(成功),5-支付失败
            'status' => '1',
            // 支付成功时间
            'paySuccTime' => $this->getTimestamp(),
            // 通知类型
            'backType' => '1',
        ];

        // 串接 sign_key
        $sign_key = $order['merchant_key'] . json_decode($order['body_params'], true)['customer_service_key'];

        $mock[$this->signField] = $this->getSignature($mock, $sign_key);

        return response()->json($mock);
    }
}
