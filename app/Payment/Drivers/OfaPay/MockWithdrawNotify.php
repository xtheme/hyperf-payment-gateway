<?php

declare(strict_types=1);

namespace App\Payment\Drivers\OfaPay;

use App\Payment\Contracts\DriverMockInterface;
use Psr\Http\Message\ResponseInterface;

class MockWithdrawNotify extends Driver implements DriverMockInterface
{
    /**
     * [Mock] 返回三方渠道回调参数
     */
    public function request(string $orderNo): ResponseInterface
    {
        // 查询订单
        $order = $this->getOrder($orderNo, 'withdraw');

        // 请依据三方回调字段调整以下内容
        $mock = [
            'scode' => $order['merchant_id'],
            'orderid' => $order['order_no'],
            'orderno' => $order['trade_no'],
            'money' => $order['amount'],
            'status' => 'S', // 代付成功
            'respcode' => '1',
            'resptime' => $this->getDateTime(),
        ];

        $mock[$this->signField] = $this->getSignature($mock, $order['merchant_key']);

        return response()->json($mock);
    }
}
