<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Jxpay;

use App\Common\Response;
use App\Constants\ErrorCode;
use App\Exception\ApiException;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class WithdrawCreate extends Driver
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
        if ($this->isOrderExists($orderNo, 'withdraw')) {
            return Response::error('商户订单号不允许重复', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 準備三方請求參數
        $params = $this->prepareOrder($orderNo, $input);

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

        // 创建订单
        $this->createOrder($orderNo, $input, 'withdraw');

        // 依据三方创建代付接口返回的内容调整以下返回给集成网关的字段
        $data = [
            'order_no' => $orderNo, // 订单号
            'trade_no' => $response['tradeNo'] ?? '', // 三方交易号
        ];

        // 更新訂單
        $this->updateOrder($orderNo, $data, 'withdraw');

        return Response::success($data);
    }

    /**
     * 转换三方创建订单字段
     */
    protected function prepareOrder(string $orderNo, array $data = []): array
    {
        if (!isset($data['body_params']['app_secret'])) {
            throw new ApiException('缺少請求參數 body_params.app_secret');
        }

        // 依据三方创建代付接口的参数调整以下字段
        $params = [
            'merchantNo' => $data['merchant_id'],
            'orderNo' => $orderNo,
            'amount' => $this->convertAmount($data['amount']),
            'name' => $data['user_name'],
            'bankName' => $data['bank_name'],
            'bankAccount' => $data['bank_account'],
            'bankBranch' => $data['bank_branch_name'] ?? '',
            'memo' => '', // 收款附言 (可选)
            'mobile' => '', // 收款通知手机号 (可选；如果收款银行支持，则会发送手机短信转账通知。)
            'datetime' => $this->getDateTime(),
            'notifyUrl' => $this->getWithdrawNotifyUrl($data['payment_platform']),
            'reverseUrl' => '',
            'extra' => '',
            'time' => $this->getTimestamp(),
            'appSecret' => $data['body_params']['app_secret'],
        ];

        // 加上签名
        $params[$this->signField] = $this->getWithdrawSignature($params, $data['merchant_key']);

        return $params;
    }
}
