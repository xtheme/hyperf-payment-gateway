<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Perfect;

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
        // {
        //     'agent': 'T_aisle168',
        //     'system_sn': 'D4uqczpzz1zbi9ufeph5unvfq0dkle',
        //     'order_sn': 'Perfect_2405151857njxzdu',
        //     'amount': '500',
        //     'total_commission': '43',
        //     'status': '01',
        //     'type': '0',
        //     'created_at': '2024-05-15 18:57:53',
        //     'completed_at': '2024-05-15 19:00:57',
        //     'overtime_at': '2024-05-16 18:57:53',
        //     'sign': '0a7835098ddee34d6ee3d6718635b5c7'
        // }

        $mock = [
            'agent' => $order['merchant_id'],
            'system_sn' => $order['trade_no'],
            'order_sn' => $order['order_no'],
            'amount' => $this->convertAmount($order['amount']),
            'total_commission' => '0',
            'status' => '01',
            'type' => $order['payment_channel'],
            'created_at' => $this->getServerDateTime(),
            'completed_at' => $this->getServerDateTime(),
            'overtime_at' => $this->getServerDateTime(),
        ];

        $mock[$this->signField] = $this->getSignature($mock, $order['merchant_key']);

        return response()->json($mock);
    }
}
