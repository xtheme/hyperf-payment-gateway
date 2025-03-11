<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Stpay;

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
            'memberid' => $order['merchant_id'],         // 商户编号
            'orderid' => $order['order_no'],            // 订单号
            'transaction_id' => $order['trade_no'],            // 交易流水号
            'amount' => $order['amount'],              // 订单金额
            'datetime' => $this->getDateTime('YmdHis'), // 交易时间
            'returncode' => '00',                          // 交易状态, 00 为成功
        ];

        $mock['sign'] = $this->getSignature($mock, $order['merchant_key']);
        $mock['attach'] = ''; // 商户附加数据返回, 不参与签名

        return response()->json($mock);
    }
}
