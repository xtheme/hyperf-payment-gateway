<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Lelipay;

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
        if ('0000' !== $response['respCode']) {
            return Response::error('TP Error!', ErrorCode::ERROR, $response);
        }

        // 統一狀態後返回集成网关
        $data = $this->transferOrderInfo($response, $order);

        return Response::success($data);
    }

    /**
     * 返回整理过的订单资讯给集成网关
     */
    public function transferOrderInfo(array $data, array $order): array
    {
        // {
        //     "Code": "0",
        //     "Message": "OK",
        //     "Amount": "100.00",
        //     "RealAmount": "99.98",
        //     "CommissionAmount": "1.88",
        //     "PayOrderStatus": "0",
        //     "PayOrderId": "D1234567890123456789",
        //     "MerchantUniqueOrderId": "guid123456789",
        //     "Remark":""
        // }

        return [
            'amount' => $this->revertAmount($order['amount']),
            'real_amount' => $this->revertAmount($data['txnAmt'] ?? 0),
            'order_no' => $order['order_no'],
            'trade_no' => $data['txnId'] ?? $order['trade_no'],
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $order['payment_channel'],
            'status' => $this->transformStatus($data['txnStatus']),
            'remark' => '',
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
        $params = [
            'txnType' => '00',
            'txnSubType' => '10',
            'secpVer' => 'icp3-1.1',
            'secpMode' => 'perm',
            'macKeyId' => $order['merchant_id'],
            'merId' => $order['merchant_id'],
            'orderId' => $order['order_no'],
            'orderDate' => $order['other_params']['orderDate'],
            'timeStamp' => $this->getDateTime('YmdHis'),
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $order['merchant_key']);

        return $params;
    }
}
