<?php

declare(strict_types=1);

namespace App\Payment\Drivers\JyuYang;

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

        // {"code":"0000","data":{"systemOrderId":"LHPO00009189","merchantOrderId":"jyuyang_withdraw_26163917","amount":"100.0000","fee":"0.0000","status":2,"channelTypeId":1,"channelId":32,"channelName":"TEST01","payeeBankCode":"CN0003","payeeName":"王陽明","payeeAccount":"000011112222","notifyUrl":"http://127.0.0.1:9503/api/v1/withdraw/notify/jyuyang","notifyStatus":0,"notifyRetryTime":0,"createdAt":"2024-05-07T08:40:59.000Z","updatedAt":"2024-05-07T08:40:59.000Z","placeSource":1,"sign":"e9bc3407d5eded26a7f6fed8189eebfd"}}
        if ('0000' !== $response['code']) {
            return Response::error('TP Error!', ErrorCode::ERROR, $response);
        }

        // 校驗簽名
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
            'fee' => $this->revertAmount($data['fee']),
            'order_no' => $data['merchantOrderId'],
            'trade_no' => $data['systemOrderId'],
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $order['payment_channel'],
            'status' => $this->transformWithdrawStatus($data['status']),
            'remark' => $data['memo'] ?? '',
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
        $params = [
            'merchantOrderId' => $order['order_no'],
            'merchantCode' => $order['merchant_id'],
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $order['merchant_key']);

        return $params;
    }
}
