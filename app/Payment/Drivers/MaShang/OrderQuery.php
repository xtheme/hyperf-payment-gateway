<?php

declare(strict_types=1);

namespace App\Payment\Drivers\MaShang;

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

        // {"result":true,"errorMsg":null,"data":{"gamerOrderId":"D17159184509810879","merchantOrderId":"mashang_72771987","sign":"2a48211d14baf369c293556e427e3dc4","currencyCode":"CNY","paymentTypeCode":"ALIPAY","amount":"1000.00","remark":"mashang_72771987","status":"ReadyToPay"}}
        if (true !== $response['result']) {
            return Response::error('TP Error!', ErrorCode::ERROR, $response);
        }

        // 验证签名
        if (false === $this->verifySignature($response['data'], $input['merchant_key'])) {
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
            'order_no' => $data['merchantOrderId'],
            'trade_no' => $data['gamerOrderId'],
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $order['payment_channel'],
            'status' => $this->transformStatus($data['status']),
            'remark' => $data['remark'] ?? '',
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
            'merchantCode' => $order['merchant_id'],
            'merchantOrderId' => $order['order_no'],
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $order['merchant_key']);

        return $params;
    }
}
