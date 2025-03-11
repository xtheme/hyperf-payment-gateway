<?php

declare(strict_types=1);

namespace App\Payment\Drivers\JiuDing;

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

        if ('200' != $response['code']) {
            return Response::error('TP Error!', ErrorCode::ERROR, $response);
        }

        // 三方返回数据示例
        // {
        //   "code":200,  //指的是查询成功
        //   "msg":"success",//指的是查询成功
        //   "data":{
        //     "amount":300000,   //订单金额
        //     "actualAmount":300000, //实付金额
        //     "applyDate":"1612277527", //时间戳
        //     "channelCode":"YHK", //通道
        //     "currency":"CNY", //币种
        //     "orderId":"D0202225207542390", //系统订单
        //     "merId":"190461832",//商户号
        //     "outTradeId":"E1612277527",//商户订单
        //     "orderStatus":"success",//订单状态
        //     "returnCode":"200", //已支付  400待支付 500已驳回
        //     "msg":"已支付","attach":"123456"}
        // }

        // 統一狀態後返回集成网关
        $data = $this->transferOrderInfo($response['data'], $order);

        return Response::success($data);
    }

    /**
     * 返回整理过的订单资讯给集成网关
     */
    public function transferOrderInfo(array $data, array $order): array
    {
        // 三方返回数据示例
        // {
        //   "code":200,  //指的是查询成功
        //   "msg":"success",//指的是查询成功
        //   "data":{
        //     "amount":300000,   //订单金额
        //     "actualAmount":300000, //实付金额
        //     "applyDate":"1612277527", //时间戳
        //     "channelCode":"YHK", //通道
        //     "currency":"CNY", //币种
        //     "orderId":"D0202225207542390", //系统订单
        //     "merId":"190461832",//商户号
        //     "outTradeId":"E1612277527",//商户订单
        //     "orderStatus":"success",//订单状态
        //     "returnCode":"200", //已支付  400待支付 500已驳回
        //     "msg":"已支付","attach":"123456"}
        // }

        // 请依据三方逻辑转换字段, $order 是缓存资料, 建议用三方返回的 $data 满足以下条件
        return [
            'amount' => $this->revertAmount($data['amount']),
            'real_amount' => $this->revertAmount($data['actualAmount']) ?: $this->revertAmount($data['amount']),
            'order_no' => $data['outTradeId'],
            'trade_no' => $data['orderId'] ?? '',
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $order['payment_channel'],
            'status' => $this->transformStatus($data['returnCode']),
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
        // 請勿調整字段順序造成簽名錯誤
        $params = [
            'applyDate' => $this->getTimestamp(),
            'outTradeId' => $order['order_no'],
            'merId' => $order['merchant_id'], // 商户号
            'ip' => getClientIp(),
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $order['merchant_key']);

        return $params;
    }
}
