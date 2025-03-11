<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Fourth;

use App\Common\Response;
use App\Constants\ErrorCode;
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

        // 创建订单
        $this->createOrder($orderNo, $input, 'withdraw');

        // 三方接口地址
        $endpointUrl = $request->input('endpoint_url');

        try {
            $response = $this->sendRequest($endpointUrl, $params, $this->config['create_order']);
        } catch (\Exception $e) {
            return Response::error($e->getMessage());
        }

        // todo 三方返回数据校验
        if ('00' !== $response['code']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // todo 请依据三方创建代付接口返回的内容调整以下返回给集成网关的字段
        $data = [
            'order_no' => $orderNo, // 订单号
            'trade_no' => $response['data']['orderId'] ?? '', // 三方交易号
        ];

        // 更新訂單 trade_no
        $update = [
            'trade_no' => $data['trade_no'],
        ];
        $this->updateOrder($orderNo, $update, 'withdraw');

        return Response::success($data);
    }

    /**
     * 转换三方创建订单字段
     */
    protected function prepareOrder(string $orderNo, array $data = []): array
    {
        // todo 请依据三方创建代付接口的参数调整以下字段
        $params = [
            'merchantCode' => $data['merchant_id'],
            // 'username' => $data['user_name'] ?? '',
            'merchantOrderId' => $orderNo,
            'serviceId' => $data['payment_channel'],
            'applyAmount' => $this->convertAmount($data['amount']),
            'applyUserName' => $data['user_name'],
            'applyAccount' => $data['bank_account'],
            'callbackUrl' => $this->getWithdrawNotifyUrl($data['payment_platform']),
            'returnUrl' => $this->getReturnUrl(),
            'applyBankName' => $data['bank_name'] ?? '',
            'applyBranchName' => $data['bank_branch_name'] ?? '',
            'applyBankCode' => '999',
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $data['merchant_key']);

        return $params;
    }
}
