<?php

declare(strict_types=1);

namespace App\Payment\Drivers\DaHai;

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

        // {"Success":true,"PayUrl":"http://119.23.50.131:5888/pay?trackingNumber=7774cfac-8a27-4abc-92d4-66761188f074","TrackingNumber":"7774cfac-8a27-4abc-92d4-66761188f074","Amount":3000,"RealAmount":3000,"BankName":"工商银行","BankRegion":"","AccountName":"陈七","AccountNumber":"55555555","IFSCCode":null,"QrCodeData":null,"FileName":null,"ExpiredAt":"2024-05-10 09:34:45","CreatedAt":"2024-05-10 09:24:45"}
        if (!$response['Success']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 创建订单
        $this->createOrder($orderNo, $input);

        $data = [
            'order_no' => $orderNo, // 订单号
            'link' => $response['PayUrl'], // 支付网址
            'trade_no' => $response['TrackingNumber'] ?? '', // 三方交易号
            'payee_name' => $response['AccountName'] ?? '', // 收款人姓名
            'payee_bank_name' => $response['BankName'] ?? '', // 收款人开户行
            'payee_bank_branch_name' => $response['BankRegion'] ?? '', // 收款行分/支行
            'payee_bank_account' => $response['AccountNumber'] ?? '', // 收款人账号
            'cashier_link' => $this->getCashierUrl($orderNo), // 收銀台網址
        ];

        $this->updateOrder($orderNo, $data);

        return Response::success($data);
    }

    /**
     * 转换三方创建订单字段
     */
    protected function prepareOrder(string $orderNo, array $data = []): array
    {
        $params = [
            'Amount' => intval($this->convertAmount($data['amount'])), // 金額（元）
            'CurrencyId' => 1,
            'IsTest' => false,
            'PayerKey' => $data['user_name'],
            'PayerName' => $data['user_name'], // 付款人姓名
            'PaymentChannelId' => intval($data['payment_channel']), // 通道類型
            'ShopInformUrl' => $this->getNotifyUrl($data['payment_platform']), // 支付成功後三方異步通知網址 (payment-gateway)
            'ShopOrderId' => $orderNo, // 商戶訂單號
            'ShopReturnUrl' => $this->getReturnUrl(), // 支付成功後轉跳網址
            'ShopUserLongId' => $data['merchant_id'], // 商戶號
        ];

        if ('11' == $data['payment_channel']) {
            $params['PayerAccountNumber'] = $data['bank_account']; // 付款人帐号
        }

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $data['merchant_key']);

        return $params;
    }
}
