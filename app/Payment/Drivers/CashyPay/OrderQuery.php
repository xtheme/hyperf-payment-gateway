<?php

declare(strict_types=1);

namespace App\Payment\Drivers\CashyPay;

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

        // {"msg":"SUCCESS","code":200,"data":{"merchantId":"3006075","mchOrderNo":"cashypay_93649009","orderNo":"PAYIN8506530188485210112","payType":"26","payCode":"88089999525724","payUrl":null,"payDeskUrl":"http://testpaydesk2.idcashypay.com/views/pay.html?PAYIN8506530188485210112","amount":"1000000","fee":"40000","orderStatus":"PAYING","completionTime":null,"nonceStr":null}}
        if (200 !== $response['code']) {
            return Response::error('TP Error!', ErrorCode::ERROR, $response);
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
        return [
            'amount' => $this->revertAmount($data['amount']), // 三方返回的金額須轉換為 "分" 返回集成网关
            'real_amount' => $this->revertAmount($data['amount']), // 三方返回的金額須轉換為 "分" 返回集成网关
            'order_no' => $order['order_no'],
            'trade_no' => $data['orderNo'],
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $order['payment_channel'],
            'status' => $this->transformStatus($data['orderStatus']),
            'remark' => $data['completionTime'] ?? '',
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
            'mchOrderNo' => $order['order_no'],
            'nonceStr' => $this->getTimestamp(),
        ];

        // header 參數
        $head = [
            'MerchantId' => $order['merchant_id'],
            $this->signField => $this->getSignature($params, $order['merchant_key']),
        ];
        $this->appendHeaders($head);

        return $params;
    }
}
