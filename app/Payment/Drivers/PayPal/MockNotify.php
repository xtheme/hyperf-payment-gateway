<?php

declare(strict_types=1);

namespace App\Payment\Drivers\PayPal;

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
            'id' => $order['trade_no'],
            'create_time' => Carbon::now()->toIso8601String(),
            'resource_type' => 'capture',
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'summary' => sprintf('Payment completed for $ %s %s', $this->convertAmount($order['amount']), $order['currency']),
        ];

        return response()->json($mock);
    }
}
