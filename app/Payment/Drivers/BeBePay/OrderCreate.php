<?php

declare(strict_types=1);

namespace App\Payment\Drivers\BeBePay;

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

        // {"deadline":"1716800567","message":"Success","pay_url":"https://hash720.com/HgZcuWkYKne9A0Yy/pay?id=056824052731369","success":true}
        if (!$response['success']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 创建订单
        $this->createOrder($orderNo, $input);

        $data = [
            'order_no' => $orderNo, // 订单号
            'link' => $response['pay_url'] ?? '', // 支付网址
            'trade_no' => $response['data']['trade_no'] ?? '', // 三方交易号
            // 例外字段
            'real_amount' => $response['actual_amount'] ?? '', // 客户实际支付金额
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
        $params = [
            'timestamp' => strval($this->getTimestamp()), // 三方非+0時區時需做時區校正
            'amount' => $this->convertAmount($data['amount']), // 金額（元）精確到小數點兩位
            'appKey' => $data['merchant_id'], // 商戶號
            'payType' => $data['payment_channel'], // 通道類型
            'orderID' => $orderNo, // 商戶訂單號
            'real_name' => $data['user_name'],
            'callback_url' => $this->getNotifyUrl($data['payment_platform']), // 支付成功後三方異步通知網址 (payment-gateway)
        ];

        $signParams = [
            'timestamp' => $params['timestamp'],
            'amount' => $params['amount'],
            'appKey' => $params['appKey'],
            'payType' => $params['payType'],
            'orderID' => $params['orderID'],
            'real_name' => $params['real_name'],
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($signParams, $data['merchant_key']);

        return $params;
    }
}
