<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Xusheng;

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
        //     "code": 0,
        //     "message": "ok",
        //     "data": {
        //         "mchId": "M1681128855",
        //         "wayCode": 2,
        //         "tradeNo": "P1645636935016587264",
        //         "outTradeNo": "xusheng_order22448142",
        //         "originTradeNo": "1",
        //         "amount": "80000",
        //         "subject": "stt",
        //         "body": null,
        //         "extParam": null,
        //         "notifyUrl": "http://127.0.0.1:9501/api/v1/payment/notify/xusheng",
        //         "payUrl": "http://pay.hgnewcloud.com/c/api/pay?osn=2023041111563439460879193",
        //         "expiredTime": "1681185694",
        //         "successTime": "1681185395",
        //         "createTime": "1681185395",
        //         "state": 0,
        //         "notifyState": 0
        //     },
        //     "sign": "e53a2b4c608018cd9683c6f93ce4b5f2"
        // }

        // 三方返回码：0=成功，其他失败
        if (0 != $response['code']) {
            return Response::error('TP Error!', ErrorCode::ERROR, $response);
        }

        $data = $response['data'];

        // 签名校验
        if ($response['sign'] != $this->getSignature($data, $order['merchant_key'])) {
            return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 統一狀態後返回集成网关
        $data = $this->transferOrderInfo($data, $order);

        return Response::success($data);
    }

    /**
     * 返回整理过的订单资讯给集成网关
     */
    public function transferOrderInfo(array $data, array $order): array
    {
        // todo 请依据三方逻辑转换字段, $order 是缓存资料, 建议用三方返回的 $data 满足以下条件
        return [
            'amount' => $this->revertAmount($data['amount']),
            'real_amount' => $this->revertAmount($data['amount']),
            'order_no' => $data['outTradeNo'],
            'trade_no' => $data['tradeNo'],
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $order['payment_channel'],
            'status' => $this->transformStatus($data['state']),
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
        $params = [
            'mchId' => $order['merchant_id'],
            'outTradeNo' => $order['order_no'],
            'reqTime' => $this->getTimestamp(),
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $order['merchant_key']);

        return $params;
    }
}
