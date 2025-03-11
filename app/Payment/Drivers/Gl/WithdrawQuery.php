<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Gl;

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

        $response = $this->queryOrderInfo($endpointUrl, $order);

        // 三方返回数据校验
        if (0 != $response['err_code']) {
            return Response::error('TP Error!', ErrorCode::ERROR, $response);
        }

        // todo 判断三方是否有签名需要校验

        // 統一狀態後返回集成网关
        $data = $this->transferOrderInfo($response, $order);

        return Response::success($data);
    }

    /**
     * 返回整理过的订单资讯给集成网关
     */
    public function transferOrderInfo(array $data, array $order): array
    {
        return [
            'amount' => $this->revertAmount($data['payee_amount']),
            'order_no' => $data['client_order_id'],
            'trade_no' => $data['order_id'],
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $order['payment_channel'],
            'status' => $this->transformWithdrawStatus($data['state']),
            'remark' => $data['err_msg'] ?? '',
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
        return [
            'merchant_id' => $order['merchant_id'],
            'payee_amount' => $this->convertAmount($order['amount']),
            'client_order_id' => $order['order_no'],
        ];
    }
}
