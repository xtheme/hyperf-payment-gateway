<?php

declare(strict_types=1);

namespace App\Payment\Drivers\NewWt;

use App\Common\Response;
use App\Constants\ErrorCode;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class OrderCreate extends Driver
{
    /**
     * 创建代收订单, 返回支付网址
     */
    public function request(RequestInterface $request): ResponseInterface
    {
        $input = $request->all();

        // 商户订单号
        $orderNo = $input['order_id'];

        // 檢查訂單號
        if ($this->isOrderExists($orderNo)) {
            return Response::error('商户订单号不允许重复', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 准备三方请求参数
        $params = $this->prepareOrder($orderNo, $input);

        // 创建订单
        $this->createOrder($orderNo, $input);

        // 三方接口地址
        $endpointUrl = $request->input('endpoint_url');

        try {
            $response = $this->sendRequest($endpointUrl, $params, $this->config['create_order']);
        } catch (\Exception $e) {
            return Response::error($e->getMessage());
        }

        // 三方返回数据校验
        if ('0000' !== $response['code']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 三方返回示例
        // {

        //     "code": "0000",
        //     "message": "",
        //     "data": {
        //           "systemOrderId": "33a3be0a-f80d-11ea-adc1-0242ac120002",
        //           "upstreamOrderId": "33a3be0a-f80d-11ea-adc1-0242ac120002",
        //           "upstreamLink": "http://test.com/payment",
        //           "amount": "1000",
        //           "displayAmount": "1000",
        //           "cardName": "王吉祥",
        //           "cardAccount": "9837591274626467",
        //           "cardBank": "村镇银行",
        //           "cardBranch": "",
        //     }
        //   }

        // 返回给集成网关的字段
        $data = [
            'order_no' => $orderNo,                                              // 订单号
            'link' => $response['data']['upstreamLink'],                             // 支付网址
            'trade_no' => $response['data']['systemOrderId'] ?? '', // 三方交易号
        ];

        // 更新訂單 trade_no
        $update = [
            'trade_no' => $data['trade_no'],
        ];
        $this->updateOrder($orderNo, $update);

        return Response::success($data);
    }

    /**
     * 转换三方创建订单字段
     */
    protected function prepareOrder(string $orderNo, array $data = []): array
    {
        // 依据三方创建代收接口规范定义请求参数
        $params = [
            'merchantCode' => $data['merchant_id'],
            'merchantOrderId' => $orderNo,
            'amount' => $this->convertAmount($data['amount']),
            'notifyUrl' => $this->getNotifyUrl($data['payment_platform']),
            'payerName' => $data['user_name'],
            'request_time' => $this->getTimestamp(),
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $data['merchant_key']);

        return $params;
    }
}
