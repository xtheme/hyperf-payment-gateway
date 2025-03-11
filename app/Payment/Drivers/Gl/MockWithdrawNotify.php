<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Gl;

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

        $mock = [
            'merchant_id' => $order['merchant_id'],
            'payee_name' => $order['user_name'],
            'payee_bank' => $order['bank_name'],
            'payee_number' => $order['bank_account'],
            'payee_amount' => $order['amount'],
            'client_order_id' => $order['order_no'],
            'state' => '2009', // 2001=待处理, 2101=失败, 2008=代付驳回, 2009=代付完成
        ];

        $mock[$this->signField] = $this->getSignature($mock, $order['merchant_key']);

        return response()->json($mock);
    }
}
