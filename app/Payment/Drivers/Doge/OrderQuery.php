<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Doge;

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

        // Custom header params
        $this->appendHeaders($order['header_params']);

        $response = $this->queryOrderInfo($endpointUrl, $order);

        // {"data":{"amount":"2000.00","confirmed_at":"","created_at":"2024-04-29T18:05:42+08:00","notify_url":"http://127.0.0.1:9503/api/v1/payment/notify/doge","order_number":"doge_94330792","return_url":"https://www.baidu.com","status":3,"system_order_number":"GX20240429180542121372","username":"yg8888","sign":"5d0981f2fcc6c93277eea9119083654d"},
        // "http_status_code":201,"message":"查询成功"}
        if (200 !== $response['http_status_code'] && 201 !== $response['http_status_code']) {
            return Response::error('TP Error!', ErrorCode::ERROR, $response);
        }

        if (false === $this->verifySignature($response['data'], $order['merchant_key'])) {
            return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        $data = $response['data'];
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
            'real_amount' => $this->revertAmount($data['amount']),
            'order_no' => $data['order_number'], // 商户订单号
            'trade_no' => $data['system_order_number'], // 平台订单号
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $order['payment_channel'],
            'status' => $this->transformStatus($data['status']), // 订单状态
            'remark' => $data['memo'] ?? '',
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
            'username' => $order['merchant_id'],
            'order_number' => $order['order_no'],
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $order['merchant_key']);

        return $params;
    }
}
