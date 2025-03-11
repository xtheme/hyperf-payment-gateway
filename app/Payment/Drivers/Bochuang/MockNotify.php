<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Bochuang;

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
            'memberid' => $order['merchant_id'], // 商户编号
            'orderid' => $order['order_no'],    // 订单号
            'amount' => $order['amount'],      // 订单金额
            'transaction_id' => $order['trade_no'],    // 交易流水号
            'datetime' => $this->getDateTime(), // 交易时间
            'returncode' => '00',                  // 交易状态 00 为成功
            'attach' => '',                    // 商户附加数据返回
        ];

        $mock[$this->signField] = $this->getSignature($mock, $order['merchant_key']);

        return response()->json($mock);
    }
}
