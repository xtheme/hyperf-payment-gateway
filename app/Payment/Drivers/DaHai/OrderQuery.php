<?php

declare(strict_types=1);

namespace App\Payment\Drivers\DaHai;

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

        // {"Success":true,"Orders":[{"TrackingNumber":"7774cfac-8a27-4abc-92d4-66761188f074","ShopOrderId":"dahai_76664393","PaymentChannelId":3,"CurrencyId":1,"Amount":3000,"RealAmount":3000,"AmountPaid":null,"ShopCommissionAmount":null,"OrderStatusId":3,"FailedMessage":null,"OriginalOrderTrackingNumber":null,"ShopRemark":null,"IsTest":false,"PaymentReceivedAt":null,"CreatedAt":"2024-05-10 09:24:46"}]}
        if (!$response['Success']) {
            return Response::error('TP Error!', ErrorCode::ERROR, $response);
        }

        $data = $response['Orders'][0];
        $data = $this->transferOrderInfo($data, $order);

        return Response::success($data);
    }

    /**
     * 返回整理过的订单资讯给集成网关
     */
    public function transferOrderInfo(array $data, array $order): array
    {
        return [
            'amount' => $this->revertAmount($data['Amount']),
            'real_amount' => $this->revertAmount($data['RealAmount']),
            'order_no' => $data['ShopOrderId'],
            'trade_no' => $data['TrackingNumber'],
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $order['payment_channel'],
            'status' => $this->transformStatus($data['OrderStatusId']),
            'remark' => $data['ShopRemark'] ?? '',
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
            'ShopOrderId' => $order['order_no'],
            'ShopUserLongId' => $order['merchant_id'],
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $order['merchant_key']);

        return $params;
    }
}
