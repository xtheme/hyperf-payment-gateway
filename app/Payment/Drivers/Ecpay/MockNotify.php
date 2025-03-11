<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Ecpay;

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

        // todo 请依据三方回调字段调整以下内容
        $mock = [
            'MerchantID' => $order['merchant_id'],
            'MerchantTradeNo' => $orderNo,
            'StoreID' => '',
            'RtnCode' => 1,
            'RtnMsg' => '交易成功',
            'TradeNo' => '', // 綠界交易號
            'TradeAmt' => '',
            'PaymentDate' => Carbon::now('Asia/Taipei')->toDateTimeString(),
            'PaymentType' => '',
            'PaymentTypeChargeFee' => '',
            'TradeDate' => '',
            'SimulatePaid' => 1,
        ];

        $mock[$this->signField] = $this->getSignature($mock, $order['merchant_key']);

        return response()->json($mock);
    }
}
