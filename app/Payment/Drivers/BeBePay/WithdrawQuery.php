<?php

declare(strict_types=1);

namespace App\Payment\Drivers\BeBePay;

use App\Common\Response;
use App\Constants\ErrorCode;
use App\Exception\ApiException;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class WithdrawQuery extends Driver
{
    /**
     * 查詢訂單
     */
    public function request(RequestInterface $request): ResponseInterface
    {
        $input = $request->all();

        // 商户订单号
        $orderNo = $input['order_id'];

        // 查询订单
        $order = $this->getOrder($orderNo, 'withdraw');

        // 三方接口地址
        $endpointUrl = $request->input('endpoint_url');

        // Custom header params
        $this->appendHeaders($order['header_params']);

        $response = $this->queryOrderInfo($endpointUrl, $order);

        // {"message":"Success","result":{"amount":1000.0,"orderID":"bebepay_withdraw_06174840","queryTime":"2024/05/27, 18:36:48","real_amount":1000.0,"sign":"ffb3fdd4c2ea1a73c43f4c6b35993c7f","status":0},"success":true}
        if (!$response['success']) {
            return Response::error('TP Error!', ErrorCode::ERROR, $response);
        }

        // 校驗簽名
        $signParams = [
            'appKey' => $order['merchant_id'],
            'orderID' => $orderNo,
            'status' => $response['result']['status'],
            'amount' => number_format($response['result']['amount'], 1, '.', ''),
            'real_amount' => number_format($response['result']['real_amount'], 1, '.', ''),
            $this->signField => $response['result'][$this->signField],
        ];

        if (false === $this->verifySignature($signParams, $order['merchant_key'])) {
            return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        $data = $response['result'];
        $data = $this->transferOrderInfo($data, $order);

        return Response::success($data);
    }

    /**
     * 返回整理过的订单资讯给集成网关
     */
    public function transferOrderInfo(array $data, array $order): array
    {
        return [
            'amount' => $this->revertAmount($data['amount']),
            'fee' => '0',
            'order_no' => $data['orderID'],
            'trade_no' => $data['payout_id'] ?? '',
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $order['payment_channel'],
            'status' => $this->transformWithdrawStatus($data['status']),
            'remark' => $data['memo'] ?? '',
            'created_at' => $this->getTimestamp(), // 返回集成使用 UTC
            'raw' => json_encode($data, JSON_UNESCAPED_SLASHES), // 三方返回的原始资料
        ];
    }

    /**
     * 查詢訂單明細
     */
    public function queryOrderInfo($endpointUrl, $order): array
    {
        $params = $this->prepareQueryOrder($order);

        try {
            return $this->sendRequest($endpointUrl, $params, $this->config['query_order']);
        } catch (\Exception $e) {
            throw new ApiException($e->getMessage());
        }
    }

    /**
     * 转换三方查询订单字段
     */
    protected function prepareQueryOrder($order): array
    {
        $params = [
            'appKey' => $order['merchant_id'],
            'orderID' => $order['order_no'],
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $order['merchant_key']);

        return $params;
    }
}
