<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Jbp;

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
            'amount' => $this->convertAmount($order['amount']),
            'bank_id' => $order['payment_channel'],
            'company_order_num' => $order['order_no'],
            'deposit_mode' => '2',
            'fee' => '0.00', // 服务费：支付平台收取单笔订单的服务费
            'mownecum_order_num' => $order['trade_no'] ?? '',
            'operating_time' => $this->getDateTime('YmdHis'),
            'pay_time' => $this->getDateTime('YmdHis'),
            'transaction_charge' => '18.00', // 服务费：支付平台收取单笔订单的服务费
            'type' => 'addTransfer',
        ];

        $check_params = [
            'pay_time' => $mock['pay_time'],
            'bank_id' => $mock['bank_id'],
            'amount' => sprintf('%.2f', $mock['amount']), // 必須小數兩位
            'company_order_num' => $mock['company_order_num'],
            'mownecum_order_num' => $mock['mownecum_order_num'],
            'fee' => sprintf('%.2f', $mock['fee']), // 必須小數兩位
            'transaction_charge' => sprintf('%.2f', $mock['transaction_charge']), // 必須小數兩位
            'deposit_mode' => $mock['deposit_mode'],
        ];
        $check_sign = $this->getSignature($check_params, $order['merchant_key']);

        $mock[$this->signField] = $check_sign;

        return response()->json($mock);
    }
}
