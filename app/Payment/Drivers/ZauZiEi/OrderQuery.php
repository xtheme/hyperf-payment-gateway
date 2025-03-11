<?php

declare(strict_types=1);

namespace App\Payment\Drivers\ZauZiEi;

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

        // Custom header params
        $this->appendHeaders($order['header_params']);

        $response = $this->queryOrderInfo($endpointUrl, $order);

        // {"userid":"2024043256","status":"0","currency":"CNY","orderno":"zauziei_80219314","outorder":"73ba3eda0536e1fa","desc":"zauziei_80219314","amount":"1000.00","realamount":null,"paytime":"0"} {"request-id":"018f2dd5-2c78-727a-8814-174718ed5dc7"}
        if (!empty($response['err']) && '' != $response['err']) {
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
            'amount' => $this->revertAmount($data['amount']), // 订单金额
            'real_amount' => !empty($data['realamount']) ? $this->revertAmount($data['realamount']) : $this->revertAmount($data['amount']), // 实付金额
            'order_no' => $data['orderno'], // 用户订单号
            'trade_no' => $data['outorder'], // 系统订单号
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $order['payment_channel'],
            'status' => $this->transformStatus($data['status']), // 状态=1 支付成功 状态=0 未支付
            'remark' => $data['desc'] ?? '', // 订单说明 原样返回
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
        $params = [
            'userid' => $order['merchant_id'],
            'orderno' => $order['order_no'],
            'action' => 'orderquery',
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $order['merchant_key']);

        return $params;
    }
}
