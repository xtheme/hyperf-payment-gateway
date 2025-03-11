<?php

declare(strict_types=1);

namespace App\Payment\Drivers\S2O;

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

        // 三方返回数据校验
        if (200 !== $response['status']) {
            $errorMessage = $response['message'];

            return Response::error('TP Error #' . $orderNo . ' ' . $errorMessage, ErrorCode::ERROR, $response);
        }

        // todo 判断三方是否有签名需要校验

        // 統一狀態後返回集成网关
        $data = $response['order_info'];

        $data = $this->transferOrderInfo($data, $order);

        return Response::success($data);
    }

    /**
     * 返回整理过的订单资讯给集成网关
     */
    public function transferOrderInfo(array $data, array $order): array
    {
        // 请依据三方逻辑转换字段, $order 是缓存资料, 建议用三方返回的 $data 满足以下条件
        return [
            'amount' => $this->revertAmount($data['original_amount']),
            'fee' => 0,
            'order_no' => $data['cus_order_sn'],
            'trade_no' => $data['order_sn'],
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $order['payment_channel'],
            // 查詢=order_status, 回調=status
            'status' => $this->transformWithdrawStatus($data['order_status'] ?? $data['status']),
            'remark' => $data['note'] ?? '',
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
        // 请依据三方查询订单文档调整以下字段
        $params = [
            'cus_code' => $order['merchant_id'],
            'order_sn' => $order['order_no'],
        ];
        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $order['merchant_key']);

        return $params;
    }
}
