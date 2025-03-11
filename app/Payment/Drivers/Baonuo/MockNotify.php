<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Baonuo;

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

        // todo 请依据三方回调字段调整以下内容
        $mock = [
            'merchantId' => $order['merchant_id'],
            'orderId' => $order['order_no'],
            'status' => 'paid',
            'msg' => '已支付',
        ];

        $mock[$this->signField] = $this->getSignature($mock, $order['merchant_key']);

        return response()->json($mock);
    }
}
