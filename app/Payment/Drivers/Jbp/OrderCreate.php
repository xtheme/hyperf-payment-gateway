<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Jbp;

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
        if (1 !== $response['status']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 请依据三方创建支付接口返回的内容调整以下返回给集成网关的字段
        $data = [
            'order_no' => $orderNo,                              // 订单号
            'link' => $response['break_url'],                // 支付网址, 跳转此链结，直接前往支付页面，仅只用一次
            'trade_no' => $response['mownecum_order_num'] ?? '', // 三方交易号
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
            'company_id' => $data['merchant_id'],                           // 商戶號
            'bank_id' => $data['payment_channel'],                       // 银行编码，详细编码请见 8.银行编码
            'amount' => $this->convertAmount($data['amount']),          // 金額（元）精確到小數點兩位
            'company_order_num' => $orderNo,                                       // 商户订单号：唯一性的字符串
            'company_user' => $data['order_id'] ?? '',                        // 提交订单用户昵称；建议加密后传入
            'estimated_payment_bank' => $data['payment_channel'],                       // 用户预计使用银行：与bank_id一致
            'deposit_mode' => '2',                                            // 默认值
            'group_id' => '0',                                            // 默认值
            'web_url' => $this->getNotifyUrl($data['payment_platform']), // 商户回调地址,用于接收充值订单回调通知
            'memo' => '',                                             // 备用字段，可为空（实名制商户可传姓名）
            'note' => '',                                             // 可为空
            'note_model' => '',                                             // 可为空
            'terminal' => $data['terminal'] ?? '2',                       // 使用终端：1=电脑端, 2=手机端
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $data['merchant_key']);

        return $params;
    }
}
