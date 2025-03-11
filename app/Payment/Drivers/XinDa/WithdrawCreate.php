<?php

declare(strict_types=1);

namespace App\Payment\Drivers\XinDa;

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

        // 三方接口地址
        $endpointUrl = $request->input('endpoint_url');

        try {
            $response = $this->sendRequest($endpointUrl, $params, $this->config['create_order']);
        } catch (\Exception $e) {
            return Response::error($e->getMessage());
        }

        if ('0' !== $response['retcode']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 创建订单
        $this->createOrder($orderNo, $input, 'withdraw');

        $data = [
            'order_no' => $orderNo, // 订单号
            'trade_no' => $response['rockTradeNo'] ?? '', // 信达支付交易订单号
        ];

        // 更新訂單 trade_no
        $update = [
            'trade_no' => $data['trade_no'],
            'status' => $this->transformWithdrawStatus($response['status']),
        ];
        $this->updateOrder($orderNo, $update, 'withdraw');

        return Response::success($data);
    }

    /**
     * 转换三方创建订单字段
     */
    protected function prepareOrder(string $orderNo, array $data = []): array
    {
        $params = [
            'version' => '1.0',
            'cid' => $data['merchant_id'], // 商戶號
            'tradeNo' => $orderNo, // 商户订单号
            'amount' => $this->convertAmount($data['amount']), // 单笔限额为 **100-50000 **元
            'payType' => $data['payment_channel'], // 1：普通馀额 2:USDT
            'acctName' => $data['user_name'], // 收款人姓名
            'acctNo' => $data['bank_account'], // 收款人账户
            'bankCode' => !empty($data['bank_code']) ? $this->transformWithdrawBankCode($data['bank_code']) : '', // 银行代号
            'notifyUrl' => $this->getWithdrawNotifyUrl($data['payment_platform']), // 交易结果通知地址
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $data['merchant_key']);

        return $params;
    }
}
