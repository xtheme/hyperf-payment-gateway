<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Fourth;

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

        // 四方回調格式: 代付
        // {
        //     orderId: string,
        //     merchantOrderNo: string,
        //     status: string,
        //     merchantCode: string,
        //     applyAmount: string,
        //     remittanceAmount: string,
        //     merchantFee: string,
        //     paymentFee: string,
        //     createdAt: string,
        //     updatedAt: string,
        //     sign: string,
        // }

        $mock = [
            'orderId' => $order['trade_no'] ?? '',
            'merchantOrderNo' => $order['order_no'],
            'status' => '2',
            'merchantCode' => $order['merchant_id'],
            'applyAmount' => $order['amount'],
            'remittanceAmount' => $order['amount'],
            'merchantFee' => '0',
            'paymentFee' => '0',
            'createdAt' => $this->getDateTime(),
            'update_time' => $this->getDateTime(),
        ];

        $mock[$this->signField] = $this->getSignature($mock, $order['merchant_key']);

        return response()->json($mock);
    }
}
