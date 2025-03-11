<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Fourth;

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

        // 請求三方
        try {
            $response = $this->sendRequest($endpointUrl, $params, $this->config['create_order']);
        } catch (\Exception $e) {
            return Response::error($e->getMessage());
        }

        // 三方返回数据校验
        if ('00' !== $response['code']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 创建订单
        $this->createOrder($orderNo, $input);

        // 更新訂單 trade_no
        $update = [
            'trade_no' => $response['orderId'] ?? '', // 三方訂單號
            'payee_name' => $response['data']['payeeName'] ?? '', // 收款人姓名
            'payee_bank_name' => $response['data']['payeeBankName'] ?? '', // 收款人开户行
            'payee_bank_branch_name' => $response['data']['payeeBranchName'] ?? '', // 收款行分/支行
            'payee_bank_account' => $response['data']['payeeAccount'] ?? '', // 收款人账号

        ];
        $this->updateOrder($orderNo, $update);

        // 请依据三方创建支付接口返回的内容调整以下返回给集成网关的字段
        $data = [
            'order_no' => $orderNo, // 订单号
            'link' => $response['data']['paymentLink'], // 支付网址
            'trade_no' => '', // 三方交易号
            'cashier_link' => $this->getCashierUrl($orderNo), // 收銀台網址
        ];

        // 收款人姓名
        if (!empty($response['data']['payeeName'])) {
            $data['payee_name'] = $response['data']['payeeName'];
        }

        // 收款人开户行
        if (!empty($response['data']['payeeBankName'])) {
            $data['payee_bank_name'] = $response['data']['payeeBankName'];
        }

        // 收款行分/支行
        if (!empty($response['data']['payeeBranchName'])) {
            $data['payee_bank_branch_name'] = $response['data']['payeeBranchName'];
        }

        // 收款人帐号
        if (!empty($response['data']['payeeAccount'])) {
            $data['payee_bank_account'] = $response['data']['payeeAccount'];
        }

        return Response::success($data);
    }

    /**
     * 转换三方创建订单字段
     */
    protected function prepareOrder(string $orderNo, array $data = []): array
    {
        // 依据三方创建代收接口规范定义请求参数
        $params = [
            'merchantCode' => $data['merchant_id'],
            'merchantOrderId' => $orderNo,
            'serviceId' => $data['payment_channel'],
            'applyAmount' => $this->convertAmount($data['amount']),
            'applyUserName' => $data['user_name'],
            'returnUrl' => $this->getReturnUrl(),
            'callbackUrl' => $this->getNotifyUrl($data['payment_platform']),
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $data['merchant_key']);

        return $params;
    }
}
