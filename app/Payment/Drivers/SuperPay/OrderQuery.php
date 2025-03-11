<?php

declare(strict_types=1);

namespace App\Payment\Drivers\SuperPay;

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
        if ('G_00001' !== $response['code']) {
            return Response::error('TP Error!', ErrorCode::ERROR, $response);
        }

        // 取出 data
        $response = $response['data'];

        // 存取 trade_no
        $update = [
            'trade_no' => $response['SYS_ORDER'],
        ];

        $this->updateOrder($orderNo, $update);

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
        //  "code": 0,
        //  "message": "success",
        //  "data": {
        //      "customer_id": 50003,
        //      "order_id": "9952341111",
        //      "transaction_id": "T033002340805958871111",
        //      "status": 2,
        //      "order_amount": "500.00000000",
        //      "real_amount": "499.90000000",
        //      "created": "2021-03-30 02:34:08",
        //      "expired": "2021-03-30 02:44:09",
        //      "notify_url": "http://test.api.test:8077/api/testCustomerCallback",
        //      "customer_callback": "OK",
        //      "extra": {
        //          "user_name": "",
        //          "pay_product_name": null
        //      },
        //      "rc_feedback": {
        //          "rate": null,
        //          "display_price": null
        //  }
        //  }
        // }

        return [
            'amount' => $this->revertAmount($data['TRAN_AMT']),
            'real_amount' => $this->revertAmount($data['TRAN_AMT']),
            'order_no' => $order['order_no'],
            'trade_no' => $data['SYS_ORDER'] ?? $order['trade_no'],
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $order['payment_channel'],
            'status' => $this->transformStatus($data['STATUS']),
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
            'MERCHANT_ID' => $order['merchant_id'],
            'VERSION' => '1',
            'TRAN_CODE' => $order['order_no'],
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $order['merchant_key']);

        return $params;
    }
}
