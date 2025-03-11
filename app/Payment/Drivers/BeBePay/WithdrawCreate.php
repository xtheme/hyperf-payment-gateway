<?php

declare(strict_types=1);

namespace App\Payment\Drivers\BeBePay;

use App\Common\Response;
use App\Constants\ErrorCode;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class WithdrawCreate extends Driver
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
        if ($this->isOrderExists($orderNo, 'withdraw')) {
            return Response::error('商户订单号不允许重复', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 準備三方請求參數
        $params = $this->prepareOrder($orderNo, $input);

        // 三方接口地址
        $endpointUrl = $request->input('endpoint_url');

        try {
            $response = $this->sendRequest($endpointUrl, $params, $this->config['create_order']);
        } catch (\Exception $e) {
            return Response::error($e->getMessage());
        }

        // {"message":"Success","success":true}
        if (!$response['success']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 创建订单
        $this->createOrder($orderNo, $input, 'withdraw');

        $data = [
            'order_no' => $orderNo, // 订单号
            'trade_no' => $response['payout_id'] ?? '', // 三方交易号
        ];

        // 更新訂單
        $this->updateOrder($orderNo, $data, 'withdraw');

        return Response::success($data);
    }

    /**
     * 转换三方创建订单字段
     */
    protected function prepareOrder(string $orderNo, array $data = []): array
    {
        $params = [
            'timestamp' => strval($this->getTimestamp()),
            'amount' => $this->convertAmount($data['amount']),
            'appKey' => $data['merchant_id'],
            'bankName' => $data['bank_name'], // 银行名称(支付宝请送 ‘支付宝’)(数字人民币请送’数字人民币’)(微信请送’微信’’)
            'accountName' => $data['user_name'], // 银行账户名
            'accountNumber' => $data['bank_account'], // 银行卡卡号
            'orderID' => $orderNo,
            'callback_url' => $this->getWithdrawNotifyUrl($data['payment_platform']),
        ];

        $signParams = [
            'timestamp' => $params['timestamp'],
            'amount' => $params['amount'],
            'appKey' => $params['appKey'],
            'bankName' => $params['bankName'],
            'accountName' => $params['accountName'],
            'accountNumber' => $params['accountNumber'],
            'orderID' => $params['orderID'],
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($signParams, $data['merchant_key']);

        return $params;
    }
}
