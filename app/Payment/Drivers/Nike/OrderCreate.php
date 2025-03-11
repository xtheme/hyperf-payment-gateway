<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Nike;

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
            return Response::error('商户订单号不允许重复', ErrorCode::ERROR, ['orderNo' => $orderNo]);
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
        //     'code': '200',
        //     'msg': '下单成功',
        //     'data': {
        //         'payUrl': 'http://black.pinkd.xyz:80/api/payPage.html?224971'
        //     }
        // }

        // 三方返回数据校验
        if (200 != $response['code']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 请依据三方创建支付接口返回的内容调整以下返回给集成网关的字段
        $data = [
            'orderNo' => $orderNo, // 订单号
            'link' => $response['data']['payUrl'], // 支付网址
            'trade_no' => '', // 三方交易号
        ];

        return Response::success($data);
    }

    /**
     * 转换三方创建订单字段
     */
    protected function prepareOrder(string $orderNo, array $data = []): array
    {
        // 依据三方创建代收接口规范定义请求参数
        $params = [
            'merchantId' => $data['merchant_id'], // 商户号:商户后台查看
            'orderId' => $orderNo, // 商户订单号:订单长度10-50位;可传字母或数字;应确保订单号唯一性
            'orderAmount' => $this->convertAmount($data['amount']), // 订单金额:单位元,可为整数,也可最多保留2位小数
            'channelType' => $data['payment_channel'], // 通道编号:商户后台查看
            'notifyUrl' => $this->getNotifyUrl($data['payment_platform']), // 支付成功後三方異步通知網址 (payment-gateway)
            // 可忽略参数
            'payer_ip' => getClientIp(), // 终端会员ip
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $data['merchant_key']);

        return $params;
    }
}
