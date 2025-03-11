<?php

declare(strict_types=1);

namespace App\Payment\Drivers\ShunSin;

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

        // jsonResult=1 => {"Success":true,"ChannelType":"BANK_PAY","PayAmount":"999.52","BankName":"华仔","BankAccount":"621412020100000","BankType":"中国建设银行","ErrorMessage":null}
        // jsonResult=0 => {"Success":true,"ChannelType":"BANK_PAY","Qrcode":"http://16.162.237.232/Order/BankTransferToBank/18f795981f11493497c43116e72480e0","GatewayUrl":null,"ErrorMessage":null}
        if (isset($response['ErrorCode'])) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        if (!$response['Success']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 创建订单
        $this->createOrder($orderNo, $input);

        $data = [
            'order_no' => $orderNo, // 订单号
            'link' => $response['Qrcode'] ?? '', // 支付网址
            'trade_no' => $response['trade_no'] ?? '', // 三方交易号
            // 例外字段
            'real_amount' => $response['PayAmount'] ?? '', // 客户实际支付金额
            'payee_name' => $response['BankName'] ?? '', // 收款人姓名
            'payee_bank_name' => $response['BankType'] ?? '', // 收款人开户行
            'payee_bank_branch_name' => $response['card_branch'] ?? '', // 收款行分/支行
            'payee_bank_account' => $response['BankAccount'] ?? '', // 收款人账号
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
        $ct = explode('-', $data['payment_channel']);
        $channelType = $ct[0];
        $jsonResult = $ct[1] ?? '1';

        $params = [
            'merchantId' => $data['merchant_id'], // 商戶號
            'merchantOrderId' => $orderNo, // 商戶訂單號
            'orderAmount' => floatval($this->convertAmount($data['amount'])), // 金額（元）精確到小數點兩位
            'channelType' => $channelType, // 通道類型
            'notifyUrl' => $this->getNotifyUrl($data['payment_platform']), // 支付成功後三方異步通知網址 (payment-gateway)
            'returnUrl' => $this->getReturnUrl(), // 支付成功後轉跳網址
            'ip' => getClientIp(),
            'remark' => $data['user_name'], // 需要传存款人姓名
            'jsonResult' => $jsonResult,
        ];

        $signParams = [
            'merchantId' => $params['merchantId'],
            'merchantOrderId' => $params['merchantOrderId'],
            'orderAmount' => $params['orderAmount'],
            'notifyUrl' => $params['notifyUrl'],
            'channelType' => $params['channelType'],
            'remark' => $params['remark'],
            'ip' => $params['ip'],
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($signParams, $data['merchant_key']);

        return $params;
    }
}
