<?php

declare(strict_types=1);

namespace App\Payment\Drivers\NingHong;

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

        // 检查金额
        if (($input['currency'] == 'IDR') && $input['amount'] < 10000000) {
            return Response::error('最低金额必须为100000', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 准备三方请求参数
        $params = $this->prepareOrder($orderNo, $input);

        // 三方接口地址
        $endpointUrl = $request->input('endpoint_url');

        // 取得商戶 Token
        $token = $this->getMerchantToken($input);
        $this->withToken($token);

        try {
            $response = $this->sendRequest($endpointUrl, $params, $this->config['create_order']);
        } catch (\Exception $e) {
            return Response::error($e->getMessage());
        }

        // 三方返回数据校验
        if (true !== $response['success']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 创建订单
        $this->createOrder($orderNo, $input);

        // 依据三方创建支付接口返回的内容调整以下返回给集成网关的字段
        $data = [
            'order_no' => $orderNo, // 订单号
            'link' => $response['payin']['remark'], // 支付网址
            'trade_no' => $response['payin']['txid'] ?? '', // 三方交易号
            // 收銀台字段
            // 'real_amount' => $response['actual_amount'] ?? '', // 客户实际支付金额
            // 'payee_name' => $response['card_name'] ?? '', // 收款人姓名
            // 'payee_bank_name' => $response['card_bank'] ?? '', // 收款人开户行
            // 'payee_bank_branch_name' => $response['card_branch'] ?? '', // 收款行分/支行
            // 'payee_bank_account' => $response['card_number'] ?? '', // 收款人账号
            // 'payee_nonce' => $response['nonce'] ?? '', // 附言
            // 'cashier_link' => $this->getCashierUrl($orderNo), // 收銀台網址
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
        // 依据三方创建代收接口规范定义请求参数
        return [
            'method' => $data['payment_channel'], // 通道類型
            'amount' => $this->convertAmount($data['amount']), // 金額（元）精確到小數點兩位
            'currency' => 'IDR', // 幣別
            'mer_tx' => $orderNo, // 商戶訂單號
            'username' => $data['user_name'] ?? '', // 用戶名
            'client_ip' => getClientIp(),
            'callback' => $this->getNotifyUrl($data['payment_platform']), // 支付成功後三方異步通知網址 (payment-gateway)
        ];
    }

}