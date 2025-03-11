<?php

declare(strict_types=1);

namespace App\Payment\Drivers\WeiFu;

use App\Common\Response;
use App\Constants\ErrorCode;
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

        // 三方接口地址
        $endpointUrl = $request->input('endpoint_url');

        try {
            $response = $this->sendRequest($endpointUrl, $params, $this->config['create_order']);
        } catch (\Exception $e) {
            return Response::error($e->getMessage());
        }

        // {"id":"7750cff2-8ca7-431f-9190-3e1d3eb5fde3","subject":"deposit","total_amount":"1000.0","notify_url":"http://127.0.0.1:9503/api/v1/payment/notify/weifu","return_url":null,"merchant_order_id":"weifu_39239400","status":"init","payment_url":"https://mwifuswzv.com/orders/7750cff2-8ca7-431f-9190-3e1d3eb5fde3?token=eyJhbGciOiJSUzI1NiJ9.eyJvcmRlcl9pZCI6Ijc3NTBjZmYyLThjYTctNDMxZi05MTkwLTNlMWQzZWI1ZmRlMyJ9.IJM8dnL4vfn28EmnFl-FhY_A6wnlKeBkcHNQ6ZQP3QxIeO4bF_DFyJYy837TNRd3NibxT4Sc3xJHQGDvyKHWWhAY6rkLntmAtWBDjf52EPWVP8IM8Hi9CL10nOnJeJLLRMdh4zJOqAm_WyoyqKDGUMJXKqEg7XnbRsojYKHxSI62ZM8h7GD2ltwM_9mOtZRCjAzI5VA93qMEl8kz4OdwEzdgjMjk97g7XydsiT0RHJRLD125KZJ84HAkWO5VYRQFjRzGKxNQo_zrvFVHafwmCYKJUP-qHELrtD3uToZpe2I9vTZSjFbIiy4rAiXsZsk8EghhV7jCrVHp5x0yYdTauQ","merchant_fee":null,"confirmation_code":null,"remark":null,"bank_account":null,"qrcode_image_url":null,"qrcode_url":null,"payment_image_url":null,"supplement_orders":[],"supplement_orders_status":"none","payment_info":null,"completed_at":null,"created_at":"2024-05-31T10:20:09+08:00"}

        // 创建订单
        $this->createOrder($orderNo, $input);

        $data = [
            'order_no' => $orderNo, // 订单号
            'link' => $response['payment_url'], // 支付网址
            'trade_no' => $response['id'] ?? '', // 三方交易号
            // 收銀台字段
            'real_amount' => $response['total_amount'] ?? '', // 客户实际支付金额
            'payee_name' => $response['card_name'] ?? '', // 收款人姓名
            'payee_bank_name' => $response['card_bank'] ?? '', // 收款人开户行
            'payee_bank_branch_name' => $response['card_branch'] ?? '', // 收款行分/支行
            'payee_bank_account' => $response['card_number'] ?? '', // 收款人账号
            'payee_nonce' => $response['nonce'] ?? '', // 附言
            'cashier_link' => $this->getCashierUrl($orderNo), // 收銀台網址
        ];

        // 更新訂單
        $this->updateOrder($orderNo, $data);

        return Response::success($data);
    }

    /**
     * 转换三方创建订单字段
     */
    protected function prepareOrder(string $orderNo, array $data = []): array
    {
        $signParams = [
            'account_name' => $data['merchant_id'], // 商戶號
            'merchant_order_id' => $orderNo, // 商戶訂單號
            'total_amount' => $this->convertAmount($data['amount']), // 金額（元）精確到小數點兩位
            'timestamp' => $this->getServerDateTime8601(), // 三方非+0時區時需做時區校正
            'notify_url' => $this->getNotifyUrl($data['payment_platform']), // 支付成功後三方異步通知網址 (payment-gateway)
            'subject' => 'deposit',
            'payment_method' => $data['payment_channel'], // 通道類型
            'guest_real_name' => $data['user_name'],
        ];

        return [
            'data' => json_encode($signParams),
            $this->signField => $this->getSignature($signParams, $data['merchant_key']),
        ];
    }
}
