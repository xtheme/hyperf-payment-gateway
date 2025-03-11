<?php

declare(strict_types=1);

namespace App\Payment\Drivers\NingHong;

use App\Payment\Contracts\DriverMockInterface;
use Carbon\Carbon;
use Psr\Http\Message\ResponseInterface;

class MockWithdrawNotify extends Driver implements DriverMockInterface
{
    /**
     * [Mock] 返回三方渠道回调参数
     */
    public function request(string $orderNo): ResponseInterface
    {
        // {
        //     'responseurl': 'https://merchantdomain/callbackURL',
        //     'payload': {
        //         'status': 'success',
        //         'txid': 'txout_r2A8rxwc',
        //         'mer_tx': 'trans001',
        //         'amount': '101.00',
        //         'currency': 'usd',
        //         'commission': '0.00',
        //         'remark': 'anything here',
        //         'approved_at': '2024-08-28 15:40:50'
        //     }
        // }

        // 查询订单
        $order = $this->getOrder($orderNo, 'withdraw');

        // 依据三方回调字段调整以下内容
        $mock = [
            'payload' => [
                'status' => 'success',
                'txid' => $order['trade_no'],
                'mer_tx' => $order['order_no'],
                'amount' => $this->convertAmount($order['amount']),
                'currency' => $order['currency'],
                'commission' => '0.00',
                'remark' => $order['remark'],
                'approved_at' => Carbon::now()->toDateTimeString(),
            ],
            'responseurl' => 'https://merchantdomain/callbackURL',
        ];

        return response()->json($mock);
    }
}