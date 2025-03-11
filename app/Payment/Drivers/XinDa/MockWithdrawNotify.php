<?php

declare(strict_types=1);

namespace App\Payment\Drivers\XinDa;

use App\Payment\Contracts\DriverMockInterface;
use Carbon\Carbon;
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

        // 请依据三方回调字段调整以下内容
        $mock = [
            'payType' => '1', // 1：普通馀额；2：结算馀额
            'cid' => $order['merchant_id'], // 商户编号
            'tradeNo' => $order['order_no'], // 商户编号
            'rockTradeNo' => $order['trade_no'], // 信达(聚鑫)支付单号
            'amount' => $this->convertAmount($order['amount']), // 订单金额
            'sysTime' => Carbon::now()->format('YmdHis'), // 系统处理时间，格式为 yyMMddHHmmss
            'status' => '1', // 支付状态 1：成功；2：处理中；3：失败
            'memo' => '', // 备註栏位
        ];

        $mock[$this->signField] = $this->getSignature($mock, $order['merchant_key']);

        return response()->json($mock);
    }
}
