<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Ecpay;

use App\Common\Response;
use App\Constants\ErrorCode;
use Carbon\Carbon;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class OrderCreate extends Driver
{
    /**
     * 创建代收订单, 返回支付网址
     */
    public function request(RequestInterface $request): ResponseInterface
    {
        $input = $request->all();

        // 商户订单号
        $orderNo = $input['order_id'];

        // 檢查訂單號
        if ($this->isOrderExists($orderNo)) {
            return Response::error('商户订单号不允许重复', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 准备三方请求参数
        $params = $this->prepareOrder($orderNo, $input);

        // 緩存表單資料
        $this->createFormData($orderNo, $params);

        // 创建订单
        $this->createOrder($orderNo, $input);

        // 返回给集成网关的字段
        $data = [
            'order_no' => $orderNo, // 订单号
            'link' => $this->getRedirectUrl($orderNo),  // 支付网址
            'trade_no' => '',       // 三方交易号
        ];

        return Response::success($data);
    }

    /**
     * 转换三方创建订单字段
     */
    protected function prepareOrder(string $orderNo, array $data = []): array
    {
        // @ref https://developers.ecpay.com.tw/?p=2866
        // [$driverName, $merchantTradeNo] = explode('_', $orderNo);

        $params = [
            'MerchantID' => $data['merchant_id'], // 特店編號
            'MerchantTradeNo' => $orderNo, // 特店訂單編號均為唯一值，不可重複使用。
            'MerchantTradeDate' => Carbon::now()->format('Y/m/d H:i:s'), // 交易時間
            'PaymentType' => 'aio',
            'TotalAmount' => (int) $this->convertAmount($data['amount']), // 請帶整數，不可有小數點，僅限新台幣
            'TradeDesc' => '用戶交易', // 交易描述，必填
            'ItemName' => 'VIP會員', // 商品名稱，必填
            'ReturnURL' => $this->getNotifyUrl($data['payment_platform']), // 回調通知網址，必填
            'ClientBackURL' => $this->getReturnUrl(), // 消費者點選此按鈕後，會將頁面導回到此設定的網址
            'ChoosePayment' => $data['payment_channel'],
            'EncryptType' => '1', // 請固定填入1，使用SHA256加密。
            'UnionPay' => '2', // 不可使用銀聯卡
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $data['merchant_key']);

        return $params;
    }
}
