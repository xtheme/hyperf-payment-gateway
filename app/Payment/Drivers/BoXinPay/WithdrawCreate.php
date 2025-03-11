<?php

declare(strict_types=1);

namespace App\Payment\Drivers\BoXinPay;

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

        // {"ErrorCode":"0","Message":"Success","TradingNumber":"240613693173"}
        if ('0' !== $response['ErrorCode']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 创建订单
        $this->createOrder($orderNo, $input, 'withdraw');

        $data = [
            'order_no' => $orderNo, // 订单号
            'trade_no' => $response['TradingNumber'] ?? '', // 三方交易号
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
            'HashKey' => $data['merchant_id'],
            'HashIV' => $data['merchant_key'],
            'Password' => $data['body_params']['PayPass'],
            'MerchantNumber' => $orderNo,
            'BankName' => $data['bank_name'],
            'BankAccount' => $data['bank_account'],
            'AccountName' => $data['user_name'],
            'Withdraw' => intval($this->convertAmount($data['amount'])),
        ];

        // 加上签名
        $signParams = [
            'HashKey' => $params['HashKey'],
            'ValidateKey' => $data['body_params']['ValidateKey'],
            'BankAccount' => $params['BankAccount'],
        ];
        $params[$this->signField] = $this->getWithdrawSignature($signParams, '');

        return $params;
    }
}
