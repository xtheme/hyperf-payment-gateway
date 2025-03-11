<?php

declare(strict_types=1);

namespace App\Payment\Drivers\ZauZiEi;

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

        // {"userid":"2024043256","status":1,"content":{"orderno":"zauziei_withdraw_51811075","orderstatus":"0","money":"1000.00","code":"申请提现中"},"msg":"Successful"}
        if (1 !== $response['status']) {
            return Response::error('TP Error!', ErrorCode::ERROR, $response);
        }

        $data = $response;
        $data = $this->transferOrderInfo($data, $order);

        return Response::success($data);
    }

    /**
     * 返回整理过的订单资讯给集成网关
     */
    public function transferOrderInfo(array $data, array $order): array
    {
        return [
            'amount' => $this->revertAmount($data['content']['money']),
            'fee' => $this->revertAmount($order['fee']) ?? '0.00',
            'order_no' => $data['content']['orderno'],
            'trade_no' => $order['trade_no'] ?? '',
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $order['payment_channel'],
            'status' => $this->transformWithdrawStatus($data['content']['orderstatus']), // 【0】申请提现中 【1】已支付 【2】冻结 【3】已驳回
            'remark' => $data['content']['code'] ?? '', // 请求状态描述
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
            'userid' => $order['merchant_id'],
            'action' => 'withdrawquery',
            'orderno' => $order['order_no'],
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $order['merchant_key']);

        return $params;
    }
}
