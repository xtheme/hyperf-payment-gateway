<?php

declare(strict_types=1);

namespace App\Payment\Drivers\HaoJie;

use App\Common\Response;
use App\Constants\ErrorCode;
use App\Exception\ApiException;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class OrderQuery extends Driver
{
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

        // 三方返回数据示例
        // {
        //     "appId": "78cdf63e983f4de0bcee0e441f0720b8",
        //     "mchOrderNo": "Test_1004-1",
        //     "productId": "8006",
        //     "currency": "CNY",
        //     "status": "0",
        //     "mchId": "40457",
        //     "channelUser": "",
        //     "paySuccTime": "",
        //     "retCode": "SUCCESS",
        //     "sign": "74363318B13DC4B5E53F7F3C1DFA97C9",
        //     "channelOrderNo": "",
        //     "amount": "100000",
        //     "retMsg": "SUCCESS",
        //     "payOrderId": "P01281220231004024053721"
        // }

        // 验证签名
        // if (false === $this->verifySignature($response, $order['merchant_key'])) {
        //     return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        // }

        // 三方返回码：0=成功，其他失败
        if ('SUCCESS' != $response['retCode']) {
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
        return [
            'amount' => $this->revertAmount($data['amount']),
            'real_amount' => $this->revertAmount($data['amount']),
            'order_no' => $data['mchOrderNo'],
            'trade_no' => $data['payOrderId'],
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $data['productId'],
            'status' => $this->transformStatus($data['status']),
            'remark' => '',
            'created_at' => $this->getServerDateTime(), // 集成使用 UTC
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
        // 请依据三方查询订单文档调整以下字段
        $params = [
            'mchId' => $order['merchant_id'],
            'mchOrderNo' => $order['order_no'],
            // 'reqTime' => $this->getDateTime('YmdHis'),
            // 'version' => '1.0',
        ];
        // Custom body params
        $body_params = json_decode($order['body_params']);

        foreach ($body_params as $key => $value) {
            $params[$key] = $value;
        }

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $order['merchant_key']);

        return [
            'params' => json_encode($params),
        ];
    }
}
