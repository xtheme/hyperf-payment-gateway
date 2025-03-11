<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Mayi;

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
        //     "retCode": "0",
        //     "sign": "6EF084954CEC1549B928A96C9B61CE38",
        //     "secK": "",
        //     "payParams": {
        //         "payUrl": "http://cashier.ssl.woaifu123.com/yy/RDSJump.php?outTradeNo=w2023033015062855311"
        //     }
        // }

        // 三方返回数据校验
        if ('0' !== $response['retCode']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 验证签名
        if ($response['sign'] === $this->getSignature($response['payParams'], $input['merchant_key'])) {
            return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 请依据三方创建支付接口返回的内容调整以下返回给集成网关的字段
        $data = [
            'order_no' => $orderNo,                         // 订单号
            'link' => $response['payParams']['payUrl'], // 支付网址
            'trade_no' => '',                               // 三方交易号
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
            'mchId' => $data['merchant_id'],                  // 商户号
            'productId' => $data['payment_channel'],              // 支付类型
            'mchOrderNo' => $orderNo,                              // 商户订单号
            'amount' => $this->convertAmount($data['amount']), // 支付金额 单位分
            'currency' => $data['currency'] ?? 'CNY',
            'notifyUrl' => $this->getNotifyUrl($data['payment_platform']), // 支付结果异步回调URL
            'subject' => $data['site_id'],                               // 商品主题
            'body' => $data['site_id'] . ' Product',                  // 商品描述信息
            'reqTime' => $this->getDateTime('YmdHis'),
            'version' => '1.0',
            // 'payPassAccountId' => '', // 支付通道子账户ID
            // 'extra' => '', // 特定渠道发起时额外参数 部分卡转卡通道需要传真实姓名参数 {"realName":"张三"} usdt通道必传{"language":"en_US"} 中文zh_CN,英文 en_US 详情咨询商务对接
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $data['merchant_key']);

        return $params;
    }
}
