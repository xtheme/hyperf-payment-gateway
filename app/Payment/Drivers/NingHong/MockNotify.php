<?php

declare(strict_types=1);

namespace App\Payment\Drivers\NingHong;

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
        // {
        //     'payload': {
        //         'rate': '0.00',
        //         'txid': 'txin_S8tP2ViI',
        //         'amount': '1008.00',
        //         'mer_tx': 'trans0002',
        //         'remark': 'https://s.shopmaket.xyz/api/Pay/pay.html?bankname=建设银行&name=测试&bankcard=1231321&subbank=衡水支行&ordernum=txin_S8tP2ViI&amount=1008.00&fkrname=Mer_4968',
        //         'status': 'success',
        //         'currency': 'rmb',
        //         'commission': '0.00',
        //         'approved_at': '2024-09-16T05:36:51.173283Z'
        //   },
        //   'responseurl': 'https://app.ninghong.org/response'
        // }

        // 查询订单
        $order = $this->getOrder($orderNo);

        // 依据三方回调字段调整以下内容
        $mock = [
            'payload' => [
                'rate' => '0.00',
                'txid' => $order['trade_no'],
                'amount' => $this->convertAmount($order['amount']),
                'mer_tx' => $order['order_no'],
                'remark' => $order['remark'],
                'status' => 'success',
                'currency' => $order['currency'],
                'commission' => '0.00',
                'approved_at' => Carbon::now()->toDateTimeString(),
            ],
            'responseurl' => 'https://app.ninghong.org/response',
        ];

        return response()->json($mock);
    }
}