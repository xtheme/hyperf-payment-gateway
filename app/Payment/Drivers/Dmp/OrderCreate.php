<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Dmp;

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

        // 三方返回数据校验
        if (0 !== $response['code']) {
            $errorMessage = $response['message'];

            return Response::error('TP Error #' . $orderNo . ' ' . $errorMessage, ErrorCode::ERROR, $response);
        }

        // 创建订单
        $this->createOrder($orderNo, $input);

        $data = [
            'order_no' => $orderNo, // 订单号
            'link' => $response['url'] ?? '', // 支付链结
            'trade_no' => $response['bill_number'] ?? '', // 支付交易订单号
            'payee_name' => $response['bank_username'] ?? '', // 收款人姓名
            'payee_bank_name' => $response['bank_name'] ?? '', // 收款人开户行
            'payee_bank_branch_name' => $response['bank_branch'] ?? '', // 收款行分/支行
            'payee_bank_account' => $response['bank_account'] ?? '', // 收款人账号
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
        // payment_channel由type跟card组成, -分隔
        $paymentArr = explode('-', $data['payment_channel']);
        $type = $paymentArr[0];
        $card = $paymentArr[1] ?? false;

        // 依据三方创建代收接口规范定义请求参数
        $params = [
            'client_id' => $data['merchant_id'], // 商戶號
            'bill_number' => $orderNo, // 商戶訂單號
            'amount' => (int) $this->convertAmount($data['amount']), // 金額（元）
            'type' => $type, // 通道類型
            'depositor_name' => $data['user_name'],
            'notify_url' => $this->getNotifyUrl($data['payment_platform']), // 支付成功後三方異步通知網址 (payment-gateway)
        ];

        if ('true' == $card) {
            $params['card'] = $card; // 是否使用我方收銀台
        }

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $data['merchant_key']);

        return $params;
    }
}
