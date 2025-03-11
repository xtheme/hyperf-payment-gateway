<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Bochuang;

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
        if (200 !== $response['code']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 请依据三方创建支付接口返回的内容调整以下返回给集成网关的字段
        $data = [
            'order_no' => $orderNo,        // 订单号
            'link' => $response['payurl'], // 支付网址
            'trade_no' => '',              // 三方交易号
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
            'pay_memberid' => $data['merchant_id'],                           // 商户号
            'pay_orderid' => $orderNo,                                       // 订单号, 上送订单号唯一, 字符长度20
            'pay_applydate' => $this->getDateTime(),                          // 提交时间
            'pay_bankcode' => $data['payment_channel'],                       // 银行编码
            'pay_notifyurl' => $this->getNotifyUrl($data['payment_platform']), // 服务端通知
            'pay_callbackurl' => $this->getReturnUrl(),                          // 页面跳转通知
            'pay_amount' => $this->convertAmount($data['amount']),          // 订单金额
            'pay_attach' => $data['order_id'],                              // 附加字段
            'pay_productname' => $data['site_id'],                               // 商品名称
        ];

        // 加上签名
        $params['pay_md5sign'] = $this->getSignature($params, $data['merchant_key']);

        return $params;
    }
}
