<?php

declare(strict_types=1);

namespace App\Payment\Drivers\JiuDing;

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

        // 请求订单状态：1处理成功-success，0处理失败-failed
        if (200 != $response['code']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 请依据三方创建支付接口返回的内容调整以下返回给集成网关的字段
        $data = [
            'order_no' => $orderNo,                              // 订单号
            'link' => $response['result']['url'],                // 支付网址, 跳转此链结，直接前往支付页面，仅只用一次
            'trade_no' => $response['result']['orderid'], // 三方交易号
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
            'merId' => $data['merchant_id'],                           // 商戶號
            'orderId' => $orderNo,                                       // 商户订单号：唯一性的字符串
            'applyDate' => $this->getDateTime(),                               // 商品名称
            'channelCode' => $data['payment_channel'],                           // 支付类型
            'notifyUrl' => $this->getNotifyUrl($data['payment_platform']),
            'callbackUrl' => $this->getReturnUrl(),                          // 支付成功後轉跳網址,
            'amount' => $this->convertAmount($data['amount']),          // 金額（元）精確到小數點兩位
            'ip' => $data['client_ip'] ?? getClientIp(),
            'currency' => 'CNY',
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $data['merchant_key']);

        return $params;
    }
}
