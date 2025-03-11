<?php

declare(strict_types=1);

namespace App\Payment\Drivers\OfaPay;

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
            'scode' => $order['merchant_id'],
            'orderid' => $order['order_no'],
            'orderno' => $order['trade_no'],
            'paytype' => 'IDR',
            'amount' => $order['amount'],
            'productname' => '用戶充值',
            'currency' => 'IDR',
            'memo' => '',
            'resptime' => $this->getDateTime(),
            'status' => '1',
            'txid' => '',
        ];

        $mock[$this->signField] = $this->getSignature($mock, $order['merchant_key']);

        return response()->json($mock);
    }
}
