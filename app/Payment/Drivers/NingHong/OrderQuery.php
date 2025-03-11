<?php

declare(strict_types=1);

namespace App\Payment\Drivers\NingHong;

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

        // 取得商戶 Token
        $token = $this->getMerchantToken($input);
        $this->withToken($token);

        $response = $this->queryOrderInfo($endpointUrl, $order);

        // 三方返回数据校验
        if (true !== $response['success']) {
            return Response::error('TP Error!', ErrorCode::ERROR, $response);
        }

        // 統一狀態後返回集成网关
        $data = $this->transferOrderInfo($response, $order);

        return Response::success($data);
    }

    /**
     * 返回整理过的订单资讯给集成网关
     */
    public function transferOrderInfo(array $response, array $order): array
    {
        $data = $response['payin'] ?? $response;

        // 请依据三方逻辑转换字段, $order 是缓存资料, 建议用三方返回的 $data 满足以下条件
        return [
            'amount' => $this->revertAmount($data['amount']), // 三方返回的金額須轉換為 "分" 返回集成网关
            'real_amount' => $this->revertAmount($data['amount']), // 三方返回的金額須轉換為 "分" 返回集成网关
            'order_no' => $order['order_no'],
            'trade_no' => $data['txid'], // 三方交易号
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $order['payment_channel'],
            'status' => $this->transformStatus($data['status']), // Pending, Success or in Rejected.
            'remark' => $data['remark'] ?? '', // 支付網址
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
        return [
            'txid' => $order['trade_no'],
            'merchant_tx' => $order['order_no'],
        ];
    }
}