<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Huiying;

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

        // 三方返回状态码校验
        if ('SUCCESS' !== $response['retCode']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 验证签名
        if (false === $this->verifySignature($response, $input['merchant_key'])) {
            return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 返回给集成网关的字段
        $data = [
            'order_no' => $orderNo,                                                                 // 订单号
            'link' => $response['payParams']['codeUrl'] ?? $response['payParams']['payPayUrl'], // 支付网址
            'qrcode' => $response['payParams']['codeImgUrl'] ?? '',                               // 二维码图片
            'trade_no' => $response['payOrderId'] ?? '',                                            // 三方交易号
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
        $params = [
            'mchId' => $data['merchant_id'],                                             // 商戶號
            // 'appId'      => $data['app_id'], // 应用ID
            'productId' => $data['payment_channel'],                                         // 支付产品ID
            'mchOrderNo' => $orderNo,                                                         // 商户订单号
            'currency' => isset($data['currency']) ? strtolower($data['currency']) : 'CNY', // 三位货币代码
            'amount' => $this->convertAmount($data['amount']),                            // 支付金额, 单位分
            'notifyUrl' => $this->getNotifyUrl($data['payment_platform']),                   // 支付结果回调 URL
            'subject' => $data['site_id'],                                                 // 商品主题 String(64)
            'body' => $data['order_id'],                                                // 商品描述信息 String(256)
        ];

        // Custom body params
        foreach ($data['body_params'] as $key => $value) {
            $params[$key] = $value;
        }

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $data['merchant_key']);

        return $params;
    }
}
