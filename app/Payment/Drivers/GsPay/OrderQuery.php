<?php

declare(strict_types=1);

namespace App\Payment\Drivers\GsPay;

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
        if (200 !== $response['code']) {
            return Response::error('TP Error!', ErrorCode::ERROR, $response);
        }

        // 三方验证签名
        if (false === $this->verifySignature($response['data'], $order['merchant_key'])) {
            return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 統一狀態後返回集成网关
        $data = $this->transferOrderInfo($response['data'], $order);

        return Response::success($data);
    }

    /**
     * 返回整理过的订单资讯给集成网关
     */
    public function transferOrderInfo(array $data, array $order): array
    {
        // todo 请依据三方逻辑转换字段, $order 是缓存资料, 建议用三方返回的 $data 满足以下条件
        return [
            'amount' => $this->revertAmount($data['Amount']), // 三方返回的金額須轉換為 "分" 返回集成网关
            'real_amount' => $this->revertAmount($data['Amount']), // 三方返回的金額須轉換為 "分" 返回集成网关
            'order_no' => $order['order_no'],
            'trade_no' => $data['OrderNo'],
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $order['payment_channel'],
            'status' => $this->transformStatus($data['Status']),
            'remark' => $data['PaymentInfo'] ?? '',
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
        // todo 依据三方查询订单文档调整以下字段
        $params = [
            'Account' => $order['merchant_id'],
            'MemberOrderNo' => $order['order_no'],
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $order['merchant_key']);

        return $params;
    }
}
