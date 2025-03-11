<?php

declare(strict_types=1);

namespace App\Payment\Drivers\BaiYun;

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

        $orderNo = str_replace('baiyun_', 'baiyun', $order['order_no']);

        // todo 请依据三方回调字段调整以下内容
        $mock = [
            'fxid' => $order['merchant_id'],
            'fxddh' => $orderNo,
            'fxfee' => $order['amount'],
            'fxstatus' => 1, // 【1代表支付成功】【0代表支付失败】其他状态不处理
            'fxtime' => $this->getTimestamp(),
        ];

        $mock[$this->signField] = $this->getSignature([
            $mock, [
                'fxstatus',
                'fxid',
                'fxddh',
                'fxfee',
            ],
        ], $order['merchant_key']);

        return response()->json($mock);
    }
}
