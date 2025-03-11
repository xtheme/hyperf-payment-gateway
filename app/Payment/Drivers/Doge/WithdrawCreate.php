<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Doge;

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

        // {"data":{"amount":"2000.00","bank_card_holder_name":"測試","bank_card_number":"833696030002","bank_city":"","bank_name":"001","bank_province":"","confirmed_at":"","created_at":"2024-04-30T11:20:32+08:00","fee":"3.00","notify_url":"http://127.0.0.1:9503/api/v1/withdraw/notify/doge","order_number":"doge_withdraw_13912931","status":3,"system_order_number":"GX20240430112032121745","username":"yg8888","sign":"30a34e89e8ee4414f924d43366f16c3c"},"http_status_code":201,"message":"提交成功"}
        if (200 !== $response['http_status_code'] && 201 !== $response['http_status_code']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 创建订单
        $this->createOrder($orderNo, $input, 'withdraw');

        $data = [
            'order_no' => $orderNo, // 订单号
            'trade_no' => $response['data']['system_order_number'] ?? '', // 三方交易号
        ];

        // 更新訂單 trade_no
        $update = [
            'trade_no' => $data['trade_no'],
            'fee' => !empty($response['data']['fee']) ? $this->revertAmount($response['data']['fee']) : '0',
        ];
        $this->updateOrder($orderNo, $update, 'withdraw');

        return Response::success($data);
    }

    /**
     * 转换三方创建订单字段
     */
    protected function prepareOrder(string $orderNo, array $data = []): array
    {
        $params = [
            'username' => $data['merchant_id'],
            'amount' => $this->convertAmount($data['amount']),
            'order_number' => $orderNo,
            'notify_url' => $this->getWithdrawNotifyUrl($data['payment_platform']),
            'bank_card_holder_name' => $data['user_name'],
            'bank_card_number' => $data['bank_account'],
            'bank_name' => $data['bank_name'],
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $data['merchant_key']);

        return $params;
    }
}
