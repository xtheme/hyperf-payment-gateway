<?php

declare(strict_types=1);

namespace App\Payment\Drivers\OfaPay;

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
        if ('1' !== $response['status']) {
            $errorMessage = $this->transformPaymentError($response['respcode']);

            return Response::error('TP Error #' . $orderNo . ' ' . $errorMessage, ErrorCode::ERROR, $response);
        }

        // 验证回傳签名
        if (false === $this->verifySignature($response, $input['merchant_key'])) {
            return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 请依据三方创建支付接口返回的内容调整以下返回给集成网关的字段
        $data = [
            'order_no' => $orderNo, // 订单号
            'link' => $response['url'], // 支付网址
            'trade_no' => $response['orderno'], // 三方交易号
        ];

        // 更新訂單 trade_no
        $update = [
            'trade_no' => $response['orderno'],
            'payment_channel' => $response['paytype'],
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
            'scode' => $data['merchant_id'], // 商戶號
            'orderid' => $orderNo, // 商戶訂單號
            'paytype' => 'IDR', // 通道類型支付方式, 固定值 'IDR'
            'amount' => $this->convertAmount($data['amount']), // 金額（元）精確到小數點兩位
            'productname' => '用戶充值',
            'currency' => 'IDR', // 幣別, 固定值 'IDR'
            'userid' => $data['user_name'], // 用戶ID
            'accountname' => $data['user_name'], // 用戶名稱
            'memo' => $data['payment_channel'], // 備註: 後台配置的通道類型
            'redirectpage' => '0', // 固定值 '0'
            'noticeurl' => $this->getNotifyUrl($data['payment_platform']), // 支付成功後三方異步通知網址 (payment-gateway)
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $data['merchant_key']);

        return $params;
    }
}
