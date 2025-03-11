<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Lelipay;

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

        // todo 依据三方回调字段调整以下内容
        $mock = [
            'respCode' => '0000',
            'respMsg' => '123123',
            'secpVer' => 'icp3-1.1',
            'secpMode' => 'perm',
            'macKeyId' => $order['merchant_id'],
            'orderDate' => 123123,
            'orderTime' => 123123,
            'merId' => $order['merchant_id'],
            'extInfo' => '',
            'orderId' => $order['order_no'],
            'txnId' => $order['trade_no'],
            'txnAmt' => $order['amount'],
            'currencyCode' => '156',
            'txnStatus' => '01',
            'txnStatusDesc' => '123123',
            'timeStamp' => 123123123123,
        ];

        $mock[$this->signField] = $this->getSignature($mock, $order['merchant_key']);

        return response()->json($mock);
    }
}
