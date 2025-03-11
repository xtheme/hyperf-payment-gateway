<?php

declare(strict_types=1);

namespace App\Payment\Drivers\JinPay;

use App\Common\Response;
use App\Constants\ErrorCode;
use App\Exception\ApiException;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class OrderQuery extends Driver
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
        $order = $this->getOrder($orderNo);

        // 三方接口地址
        $endpointUrl = $request->input('endpoint_url');

        $response = $this->queryOrderInfo($endpointUrl, $order);

        // {"code":0,"msg":"success","data":{"orderId":"202407301459000086","orderNo":"jinpay_19861367","orderStatus":"1","amount":"10000.00","userId":"IDS88","sign":"c50bed25b255513f67b55e9a2fdaa358","currency":"IDR"}}
        if (0 !== $response['code']) {
            return Response::error('TP Error!', ErrorCode::ERROR, $response);
        }

        if (false === $this->verifySignature($response['data'], $input['merchant_key'])) {
            return Response::error('验证签名失败', ErrorCode::ERROR, $response);
        }

        // 統一狀態後返回集成网关
        $data = $this->transferOrderInfo($response['data'], $order);

        return Response::success($data);
    }

    /**
     * 返回整理过的订单资讯给集成网关
     */
    public function transferOrderInfo(array $data, array $order): array
    {
        return [
            'amount' => $this->revertAmount($data['amount']), // 三方返回的金額須轉換為 "分" 返回集成网关
            'real_amount' => $this->revertAmount($data['amount']), // 三方返回的金額須轉換為 "分" 返回集成网关
            'order_no' => $order['order_no'],
            'trade_no' => $data['orderId'],
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $order['payment_channel'],
            'status' => $this->transformStatus($data['orderStatus']),
            'remark' => $data['currency'] ?? '',
            'created_at' => $this->getTimestamp(), // 集成使用 UTC
            'raw' => json_encode($data, JSON_UNESCAPED_SLASHES), // 三方返回的原始资料
        ];
    }

    /**
     * 查詢訂單明細
     */
    protected function queryOrderInfo($endpointUrl, $order): array
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
            'userId' => $order['merchant_id'],
            'orderId' => $order['order_no'],
            'currency' => 'IDR',
        ];

        $signParams = $params;
        unset($signParams['currency']);

        // 加上签名
        $params[$this->signField] = $this->getSignature($signParams, $order['merchant_key']);

        return $params;
    }
}
