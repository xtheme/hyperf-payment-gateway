<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Sulifu;

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

        $response = $this->queryOrderInfo($endpointUrl, $order);

        // 三方返回数据校验
        if (1 !== $response['Success']) {
            return Response::error('TP Error!', ErrorCode::ERROR, $response);
        }

        // 統一狀態後返回集成网关
        $data = $this->transferOrderInfo($response, $order);

        return Response::success($data);
    }

    /**
     * 返回整理过的订单资讯给集成网关
     */
    public function transferOrderInfo(array $data, array $order): array
    {
        // {
        //     'Success': 1,
        //     'Message': '尚未完成',
        //     'oid': '2024051317355529399',
        //     'orderAmount': 500,
        //     'status': 0,
        //     'topupAmount': 0,
        //     'topupTime': ''
        // }

        // 依据三方逻辑转换字段, $order 是缓存资料, 建议用三方返回的 $data 满足以下条件
        return [
            'amount' => $order['amount'],
            'fee' => '0',
            'order_no' => $order['order_no'],
            'trade_no' => $data['oid'] ?? $order['trade_no'],
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $order['payment_channel'],
            'status' => $this->transformWithdrawStatus($data['status'] ?? $data['tradeStatus']),
            'remark' => '',
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
        // 依据三方查询订单文档调整以下字段
        $params = [
            'merNo' => $order['merchant_id'],
            'tradeNo' => $order['order_no'],
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $order['merchant_key']);

        return $params;
    }
}
