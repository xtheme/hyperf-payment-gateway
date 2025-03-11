<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Ecpay;

use App\Common\Response;
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

        // 三方返回码：0=成功，其他失败   綠界無返回碼
        // if (0 != $response['code']) {
        //     return Response::error('TP Error!', ErrorCode::ERROR, $response);
        // }

        // 判断三方是否有签名需要校验 無

        // 統一狀態後返回集成网关
        $data = $response;
        $data = $this->transferOrderInfo($data, $order);

        return Response::success($data);
    }

    /**
     * 返回整理过的订单资讯给集成网关
     */
    public function transferOrderInfo(array $data, array $order): array
    {
        // 回調時是RtnCode, 查詢時是TradeStatus
        $status = (string) ($data['RtnCode'] ?? $data['TradeStatus'] ?? '');
        // 回調時有RtnMsg, 查詢無msg
        $remark = $data['RtnMsg '] ?? '';

        // 请依据三方逻辑转换字段, $order 是缓存资料, 建议用三方返回的 $data 满足以下条件
        return [
            'amount' => $this->revertAmount($data['TradeAmt']),
            'real_amount' => $this->revertAmount($data['TradeAmt']),
            'order_no' => $data['MerchantTradeNo'],
            'trade_no' => $data['TradeNo'],
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $order['payment_channel'],
            'status' => $this->transformStatus($status),
            'remark' => $remark, // Error Message
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
        } catch (\Throwable $e) {
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
            'MerchantID' => $order['merchant_id'],
            'MerchantTradeNo' => $order['order_no'],
            'TimeStamp' => $this->getTimestamp(),
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $order['merchant_key']);

        return $params;
    }
}
