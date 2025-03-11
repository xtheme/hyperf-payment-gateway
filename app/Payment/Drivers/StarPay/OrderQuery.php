<?php

declare(strict_types=1);

namespace App\Payment\Drivers\StarPay;

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

        // 三方返回数据校验
        if ('000' !== $response['code']) {
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
        //     "code": "000",
        //     "message": "",
        //     "userOrderNumber": "starpay_202407230002",
        //     "orderNumber": "L58F938173FE53163",
        //     "merchantCode": "slots539",
        //     "amount": 40000,
        //     "phone": "00",
        //     "email": "00",
        //     "userName": "XXX",
        //     "channelCode": "CASH",
        //     "status": 0,
        //     "createTime": "2024-07-23 10:16:39"
        // }

        return [
            'amount' => $this->revertAmount($data['amount']),
            'real_amount' => $this->revertAmount($data['amount']),
            'order_no' => $order['order_no'],
            'trade_no' => $data['orderNumber'] ?? $order['trade_no'],
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $order['payment_channel'],
            'status' => $this->transformStatus($data['status']),
            'remark' => '',
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
        // 依据三方查询订单文档调整以下字段
        $params = [
            'merchantCode' => $order['merchant_id'],
            'orderNumber' => $order['order_no'],
        ];

        $tempStr = $params['merchantCode'] . $params['orderNumber'] . $order['merchant_key'];

        // 加上签名
        $params[$this->signField] = md5($tempStr);

        return $params;
    }
}
