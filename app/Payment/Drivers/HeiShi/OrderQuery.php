<?php

declare(strict_types=1);

namespace App\Payment\Drivers\HeiShi;

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
        //     "code": 200,
        //     "msg": "success",
        //     "data": {
        //         "thoroughfare": "1000",
        //         "account_name": "MX8888",
        //         "pay_time": 0,
        //         "pay_status": 3,
        //         "status": "success",
        //         "amount": "10.00",
        //         "pay_amount": "10.00",
        //         "out_trade_no": "Test_1002-1",
        //         "trade_no": "4515120231002975457",
        //         "timestamp": 1696214194,
        //         "fees": "0.1100"
        //     }
        // }

        // 三方返回码：0=成功，其他失败
        if (200 != $response['code']) {
            return Response::error('TP Error!', ErrorCode::ERROR, $response);
        }

        $data = $response['data'];

        // 签名校验
        // if ($response['sign'] != $this->getSignature($data, $order['merchant_key'])) {
        //     return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        // }

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
            'real_amount' => $this->revertAmount($data['pay_amount']),
            'order_no' => $data['out_trade_no'],
            'trade_no' => $data['trade_no'],
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $order['payment_channel'],
            'status' => $this->transformStatus($data['pay_status']),
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
            'account_id' => $order['merchant_id'],
            'out_trade_no' => $order['order_no'],
            'timestamp' => $this->getTimestamp(),
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $order['merchant_key']);

        return $params;
    }
}
