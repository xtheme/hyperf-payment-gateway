<?php

declare(strict_types=1);

namespace App\Payment\Drivers\GOSM;

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

        // {"code":0,"message":"Success","result":{"account":"CAVY80VK","amount":1,"newAmount":1,"fee":0.045,"series":2,"status":"TIMEOUT","type":1,"storeOrderCode":"gosm_64979231","SystemOrderId":"PA2024053010501246765","signedMsg":"652a613d0eb538c13fd7032381cf962d89c9ebef64b9378d878745672d176c0d","submitTime":1717037412}}
        if (0 !== $response['code']) {
            return Response::error('TP Error!', ErrorCode::ERROR, $response);
        }

        $signParams = [
            'account' => $response['result']['account'],
            'storeOrderCode' => $response['result']['storeOrderCode'],
            $this->signField => $response['result'][$this->signField],
        ];

        if (false === $this->verifySignature($signParams, $input['merchant_key'])) {
            return Response::error('query 验证签名失败', ErrorCode::ERROR, $response);
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
            'real_amount' => $this->revertAmount($data['newAmount']),
            'order_no' => $data['storeOrderCode'],
            'trade_no' => $data['SystemOrderId'],
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $order['payment_channel'],
            'status' => $this->transformStatus($data['status']),
            'remark' => $data['memo'] ?? '', // todo
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
            'account' => $order['merchant_id'],
            'storeOrderCode' => $order['order_no'],
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $order['merchant_key']);

        return $params;
    }
}
