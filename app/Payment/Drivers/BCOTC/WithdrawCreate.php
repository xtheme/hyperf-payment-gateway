<?php

declare(strict_types=1);

namespace App\Payment\Drivers\BCOTC;

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

        // {"status":1,"msg":"","request_id":null,"order_amount":{"crypto_amount":"13.864000","fiat_amount":"100.000000"},"order_id":"111836f2-0eb2-46a3-a1ef-1347f54db3b4"}
        if (1 !== $response['status']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 创建订单
        $this->createOrder($orderNo, $input, 'withdraw');

        $data = [
            'order_no' => $orderNo, // 订单号
            'trade_no' => $response['order_id'] ?? '', // 三方交易号
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
            'merchant_id' => $data['merchant_id'],
            'merchant_order_id' => $orderNo,
            'amount' => intval($this->convertAmount($data['amount'])),
            'notify_url' => $this->getWithdrawNotifyUrl($data['payment_platform']),
            'alipay_account_name' => $data['user_name'],
            'alipay_account_number' => $data['bank_account'],
            'payment_method' => intval($data['payment_channel']),
            'apply_timestamp' => $this->getTimestamp(),
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $data['merchant_key']);

        return $params;
    }
}
