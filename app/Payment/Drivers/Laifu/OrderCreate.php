<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Laifu;

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
        if (0 !== $response['code']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 请依据三方创建支付接口返回的内容调整以下返回给集成网关的字段
        $data = [
            'order_no' => $orderNo,                         // 订单号
            'link' => $response['data']['request_url'], // 支付网址
            'trade_no' => '',                               // 三方交易号
        ];

        return Response::success($data);
    }

    /**
     * 转换三方创建订单字段
     */
    protected function prepareOrder(string $orderNo, array $data = []): array
    {
        $params = [
            'mchid' => $data['merchant_id'],                           // 商户号
            'out_trade_no' => $orderNo,                                       // 商户订单号
            'amount' => $this->convertAmount($data['amount']),          // 支付金额 (单位元)
            'channel' => $data['payment_channel'],                       // 支付产品通道
            'return_url' => $this->getReturnUrl(),                          // 同步返回URL
            'notify_url' => $this->getNotifyUrl($data['payment_platform']), // 支付结果回调URL
            'time_stamp' => $this->getDateTime('YmdHis'),                  // 下单时间
            'body' => $data['order_id'],                              // 商品描述信息 (原始訂單號)
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $data['merchant_key']);

        return $params;
    }
}
