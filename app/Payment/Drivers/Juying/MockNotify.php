<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Juying;

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
            // 商戶 id
            'mch_id' => $order['merchant_id'],
            // 隨機碼
            'nonce_str' => '0123123123123123',
            // 時間戳
            'timeStamp' => (string) $this->getTimestamp(),
            // 商户交易单号
            'orderNo' => $order['order_no'],
            // 商戶姓名
            'userName' => '',
            // 付款人姓名
            'payUserName' => $order['user_name'],
            // 平台單號
            'tradeNo' => $order['trade_no'],
            // 訂單狀態
            'tradeState' => 'WAIT',
            // 訂單類型
            'type' => 'COLLECTION',
            // 金額
            'score' => $this->convertAmount($order['amount']),

        ];

        // 參與簽名參數

        $mock_sign = [
            'mch_id' => $order['merchant_id'],
            'nonce_str' => '0123123123123123',
            'orderNo' => $order['order_no'],
            'score' => $this->convertAmount($order['amount']),
            'timeStamp' => $mock['timeStamp'],
        ];

        $mock[$this->signField] = $this->getSignature($mock_sign, $order['merchant_key']);

        return response()->json($mock);
    }
}
