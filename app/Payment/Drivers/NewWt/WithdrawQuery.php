<?php

declare(strict_types=1);

namespace App\Payment\Drivers\NewWt;

use App\Common\Response;
use App\Constants\ErrorCode;
use App\Exception\ApiException;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class WithdrawQuery extends Driver
{
    public function request(RequestInterface $request): ResponseInterface
    {
        $input = $request->all();

        // 商户订单号
        $orderNo = $input['order_id'];

        // 查询订单
        $order = $this->getOrder($orderNo, 'withdraw');

        // 三方接口地址
        $endpointUrl = $input['endpoint_url'];

        // Custom header params
        $this->appendHeaders($order['header_params']);

        $response = $this->queryOrderInfo($endpointUrl, $order);

        // 三方返回数据示例, data 為陣列
        // {
        //     "code": "0000",
        //     "message": "",
        //     "data": {
        //         "systemOrderId": "LHPO00000068",
        //         "merchantOrderId": "R20231113046",
        //         "amount": 1000,
        //         "fee": 10,
        //         "status": 5,
        //         "channelTypeId": 2,
        //         "channelId": 24,
        //         "channelName": "ch0031",
        //         "payeeBankCode": null,
        //         "payeeName": "张三",
        //         "payeeAccount": "6214673140001183721", "notifyUrl": "http://test.com/payfornotice", "notifyStatus": 0,
        //         "notifyRetryTime": 0,
        //         "create_time": "2023-11-21T06:51:08.000Z",
        //         "update_time": "2023-11-21T06:53:47.000Z",
        //         "placeSource": 1,
        //         "sign": "08be2c33ac7e6cfaad839d350a013738"
        //     }
        // }

        // 网关返回码：0000=成功，其他失败
        if ('0000' != $response['code']) {
            return Response::error('TP Error!', ErrorCode::ERROR, $response);
        }

        // 校驗簽名
        // if (false === $this->verifySignature($response['data'], $order['merchant_key'])) {
        //     return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        // }

        // 統一狀態後返回集成网关
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
            'fee' => $this->revertAmount($data['fee']),
            'order_no' => $data['merchantOrderId'],
            'trade_no' => $data['systemOrderId'],
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $order['payment_channel'],
            'status' => $this->transformWithdrawStatus($data['status']),
            'remark' => $data['memo'] ?? '',
            'created_at' => $this->getServerDateTime(), // 返回集成使用 UTC
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
        $params = [
            'merchantOrderId' => $order['order_no'],
            'merchantCode' => $order['merchant_id'],
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $order['merchant_key']);

        return $params;
    }
}
