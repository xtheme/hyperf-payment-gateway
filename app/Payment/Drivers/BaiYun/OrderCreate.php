<?php

declare(strict_types=1);

namespace App\Payment\Drivers\BaiYun;

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

        // 三方返回示例
        // {
        //     "status": 1,
        //     "payurl": "http://39.108.228.150:8089/api/payPage.html?id=P01202303310941461612084"
        // }

        // 三方返回数据校验 状态 1=代表正常 / 0=代表错误
        if (1 !== $response['status']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 请依据三方创建支付接口返回的内容调整以下返回给集成网关的字段
        $data = [
            'order_no' => $orderNo,            // 订单号
            'link' => $response['payurl'], // 支付网址
            'trade_no' => '',                  // 三方交易号
        ];

        return Response::success($data);
    }

    /**
     * 转换三方创建订单字段
     */
    protected function prepareOrder(string $orderNo, array $data = []): array
    {
        $params = [
            'fxid' => $data['merchant_id'],
            'fxddh' => $orderNo,
            'fxdesc' => $data['site_id'], // 商品名称
            'fxfee' => $this->convertAmount($data['amount']),
            'fxnotifyurl' => $this->getNotifyUrl($data['payment_platform']),
            'fxbackurl' => $this->getReturnUrl(),
            'fxpay' => $data['payment_channel'],
            'fxattch' => $orderNo, // 对接USDT的用户请务必写入会员ID或是账号每个会员皆是唯一值(请勿带入中文字符)
            'fxip' => getClientIp(),
        ];

        // 加上签名
        // $params[$this->signField] = $this->getSignature($params, $data['merchant_key']);

        // 加上签名
        $params[$this->signField] = $this->getSignature([
            $params, [
                'fxid',
                'fxddh',
                'fxfee',
                'fxnotifyurl',
            ],
        ], $data['merchant_key']);

        return $params;
    }
}
