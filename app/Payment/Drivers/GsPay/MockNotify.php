<?php

declare(strict_types=1);

namespace App\Payment\Drivers\GsPay;

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
        // 查询订单
        $order = $this->getOrder($orderNo);

        // todo 依据三方回调字段调整以下内容
        $mock = [
            'Amount' => $order['amount'],
            'MemberOrderNo' => $order['order_no'],
            'OrderNo' => $order['trade_no'],
            'Status' => 'success',
            'DueTime' => Carbon::now()->addDays(1)->toDateTimeString(),
            'PaymentInfo' => 'NGS00000000000',
        ];

        $mock[$this->signField] = $this->getSignature($mock, $order['merchant_key']);

        return response()->json($mock);
    }
}
