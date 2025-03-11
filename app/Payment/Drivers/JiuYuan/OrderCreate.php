<?php

declare(strict_types=1);

namespace App\Payment\Drivers\JiuYuan;

use App\Common\Response;
use App\Constants\ErrorCode;
use Hyperf\Collection\Arr;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class OrderCreate extends Driver
{
    protected string $signField = 'fxsign';

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
        if (1 != $response['status']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 请依据三方创建支付接口返回的内容调整以下返回给集成网关的字段
        $data = [
            'order_no' => $orderNo,                              // 订单号
            'link' => $response['payurl'],                // 支付网址, 跳转此链结，直接前往支付页面，仅只用一次
            'trade_no' => '', // 三方交易号
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
            'fxid' => $data['merchant_id'],                           // 商戶號
            'fxddh' => $orderNo,                                       // 商户订单号：唯一性的字符串
            'fxdesc' => $data['site_id'],                               // 商品名称
            'fxfee' => $this->convertAmount($data['amount']),          // 金額（元）精確到小數點兩位
            'fxnotifyurl' => $this->getNotifyUrl($data['payment_platform']),
            'fxbackurl' => $this->getReturnUrl(),                          // 支付成功後轉跳網址,
            'fxpay' => $data['payment_channel'],                           // 支付类型
            'fxip' => $data['client_ip'] ?? getClientIp(),
            'fxuserid' => $orderNo,
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $data['merchant_key']);

        return $params;
    }

    protected function getSignature(array $data, string $signatureKey): string
    {
        if (isset($data[$this->signField])) {
            unset($data[$this->signField]);
        }

        // 1. 依據組合傳參拼接字串
        $tempStr = urldecode(http_build_query(Arr::only($data, ['fxid', 'fxddh', 'fxfee', 'fxnotifyurl'])));

        // 2. $tempStr 拼接 key
        $tempStr = $tempStr . '&' . $signatureKey;

        // 3. md5
        return md5($tempStr);
    }
}
