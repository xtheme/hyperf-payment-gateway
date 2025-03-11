<?php

declare(strict_types=1);

namespace App\Payment\Drivers\WanFu;

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
        if ('0000' !== $response['code']) {
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
        // "code": "0000",
        // "message": "",
        // "data": {
        //      "systemOrderId": "LHPL00000105",
        //      "merchantOrderId": "20231113021",
        //      "amount": 3810,
        //      "realAmount": 3810,
        //      "fee": 10,
        //      "status": 2,
        //      "channelTypeId": 2,
        //      "channelId": 19,
        //      "channelName": "vx0009",
        //      "payerName": "王阳明",
        //      "notifyUrl": "http://test.com/payfornotice",
        //      "notifyStatus": 0,
        //      "notifyRetryTime": 0,
        //      "create_time": "2023-11-13T13:03:57.000Z",
        //      "update_time": "2023-11-13T13:06:26.000Z",
        //      "placeSource": 1,
        //      "sign": "6ad30010e89152b0f704a44ea813df06"
        //      }
        // }

        // 處理 real_amount null 情況
        if (empty($data['data']['real_amount'])) {
            $data['data']['real_amount'] = '0';
        }

        return [
            'amount' => $this->revertAmount($data['data']['amount']),
            'real_amount' => $this->revertAmount($data['data']['realAmount']),
            'order_no' => $order['order_no'],
            'trade_no' => $data['data']['systemOrderId'] ?? $order['trade_no'],
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $order['payment_channel'],
            'status' => $this->transformStatus($data['data']['status']),
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
            'merchantOrderId' => $order['order_no'],
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $order['merchant_key']);

        return $params;
    }
}
