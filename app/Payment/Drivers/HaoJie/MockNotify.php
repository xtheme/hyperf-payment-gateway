<?php

declare(strict_types=1);

namespace App\Payment\Drivers\HaoJie;

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
            'payOrderId' => $order['order_no'], // 三方交易号
            'mchId' => $order['merchant_id'], // 商户号
            'productId' => $order['payment_channel'], // 支付通道
            'mchOrderNo' => $order['order_no'], // 商户订单号
            'amount' => $order['amount'], // 请求支付下单时金额, 单位分
            'income' => $order['amount'], // 用户实际付款的金额, 单位分
            'status' => 1, // 支付状态: -2=订单已关闭, 0=订单生成, 1=支付中, 2=支付成功, 3=业务处理完成, 4=已退款
            'paySuccTime' => '', // 支付成功时间, 精确到毫秒
            'backType' => 1, // 通知类型: 1=前台通知, 2=后台通知
            'reqTime' => $this->getDateTime('YmdHis'), // 通知请求时间 yyyyMMddHHmmss 格式
        ];

        $mock[$this->signField] = $this->getSignature($mock, $order['merchant_key']);

        return response()->json($mock);
    }
}
