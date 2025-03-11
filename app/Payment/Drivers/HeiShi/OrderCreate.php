<?php

declare(strict_types=1);

namespace App\Payment\Drivers\HeiShi;

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

        // 三方返回数据示例
        // {
        //     "code": 200,
        //     "msg": "success",
        //     "data": {
        //         "order_id": "5946336",
        //         "trade_no": "4515120231002975457",
        //         "qrcode": "http://8.222.244.19:201/gateway/selfpay/paycode.do?id=5946336",
        //         "qrcode_url": "http://8.222.244.19:201/gateway/selfpay/paycode.do?id=5946336",
        //         "pay_url": "http://8.222.244.19:201/gateway/selfpay/paycode.do?id=5946336",
        //         "pc_pay_url": "http://8.222.244.19:201/gateway/selfpay/paycode.do?id=5946336",
        //         "sdk": ""
        //     }
        // }

        // 三方返回数据校验
        if (200 !== $response['code']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 签名校验
        // if ($response['sign'] != $this->getSignature($response['data'], $input['merchant_key'])) {
        //     return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        // }

        // 请依据三方创建支付接口返回的内容调整以下返回给集成网关的字段
        $data = [
            'orderNo' => $orderNo,                           // 订单号
            'link' => $response['data']['pay_url'],        // 支付网址
            'trade_no' => $response['data']['trade_no'] ?? '', // 三方交易号
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
            'account_id' => $data['merchant_id'],
            'content_type' => 'json',
            'thoroughfare' => $data['payment_channel'],
            'out_trade_no' => $orderNo,
            'amount' => $this->convertAmount($data['amount']), // 金額（元）精確到小數點兩位
            'callback_url' => $this->getNotifyUrl($data['payment_platform']), // 支付成功後三方異步通知網址 (payment-gateway)
            'success_url' => $this->getReturnUrl(),
            'error_url' => $this->getReturnUrl(),
            'timestamp' => $this->getTimestamp(), // 三方非+0時區時需做時區校正
            'ip' => getClientIp(),
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $data['merchant_key']);

        return $params;
    }
}
