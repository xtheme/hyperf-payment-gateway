<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Jxpay;

use App\Common\Response;
use App\Constants\ErrorCode;
use App\Exception\ApiException;
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

        // 创建订单
        $this->createOrder($orderNo, $input);

        // 三方接口地址
        $endpointUrl = $request->input('endpoint_url');

        try {
            $response = $this->sendRequest($endpointUrl, $params, $this->config['create_order']);
        } catch (\Exception $e) {
            return Response::error($e->getMessage());
        }

        // 三方返回数据校验
        if (0 !== $response['code']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 依据三方创建支付接口返回的内容调整以下返回给集成网关的字段
        $data = [
            'order_no' => $orderNo, // 订单号
            'link' => $response['targetUrl'], // 支付网址
            'trade_no' => $response['tradeNo'] ?? '', // 三方交易号
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
        if (!isset($data['body_params']['app_secret'])) {
            throw new ApiException('缺少請求參數 body_params.app_secret');
        }

        $params = [
            'merchantNo' => $data['merchant_id'], // 商戶號
            'orderNo' => $orderNo, // 商戶訂單號
            'userNo' => $data['user_id'] ?? '', // 商户客户号
            'userName' => $data['user_name'] ?? '', // 商户客户名
            'channelNo' => $data['payment_channel'], // 通道類型
            'amount' => $this->convertAmount($data['amount']), // 金額（元）
            'discount' => '', // 金額（元）
            'payeeName' => '', // 付款人姓名 (可选;付款人的银行账号或银行账号末五码)
            'bankName' => '',
            'extra' => '',
            'datetime' => $this->getDateTime(), // 日期时间 (格式:2018-01-01 23:59:59)
            'time' => $this->getTimestamp(), // Linux timestamp
            'notifyUrl' => $this->getNotifyUrl($data['payment_platform']), // 支付成功後三方異步通知網址 (payment-gateway)
            // 'returnUrl' => $this->getReturnUrl(), // 支付成功後轉跳網址
            'appSecret' => $data['body_params']['app_secret'],
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $data['merchant_key']);

        return $params;
    }
}
