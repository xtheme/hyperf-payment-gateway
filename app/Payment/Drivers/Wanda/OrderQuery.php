<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Wanda;

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
        //     "retCode": "0",
        //     "sign": "3629B05D0913DC64372F8A6BFAFD8EBA",
        //     "mchId": "20000440",
        //     "appId": "",
        //     "productId": "8056",
        //     "payOrderId": "P01202310031201215758735",
        //     "mchOrderNo": "Test_1002-1",
        //     "amount": "10000",
        //     "currency": "cny",
        //     "status": "1",
        //     "channelUser": "",
        //     "channelOrderNo": "P01202310031201215758735",
        //     "channelAttach": "{\"data\":{\"status\":200,\"channel\":\"alipay_two\",\"order_no\":\"P01202310031201215758735\",\"amount\":10000,\"created\":1696305681,\"extra\":{},\"credential\":{\"pay_url\":\"http://ali.szokmgz.cn/api/gateway/wait/pay/20231003120121696154492D6B\"}},\"state\":\"ok\"}",
        //     "paySuccTime": ""
        // }

        // 验证签名
        // if (false === $this->verifySignature($response, $order['merchant_key'])) {
        //     return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        // }

        // 三方返回码：0=成功，其他失败
        if ('0' != $response['retCode']) {
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
            'reqTime' => $this->getDateTime('YmdHis'),
            'version' => '1.0',
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $order['merchant_key']);

        return $params;
    }
}
