<?php

declare(strict_types=1);

namespace App\Payment\Drivers\StarPay;

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

        // 三方返回数据示例

        // 三方返回数据校验
        if ('000' !== $response['code']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 创建订单
        $this->createOrder($orderNo, $input, 'withdraw');

        // 依据三方创建支付接口返回的内容调整以下返回给集成网关的字段
        $data = [
            'order_no' => $orderNo,                             // 订单号
            'trade_no' => $response['id'] ?? '', // 三方交易号
        ];

        // 更新訂單 trade_no
        // $update = [
        //     'trade_no' => $data['trade_no'],
        // ];
        // $this->updateOrder($orderNo, $update, 'withdraw');

        return Response::success($data);
    }

    /**
     * 转换三方创建订单字段
     */
    protected function prepareOrder(string $orderNo, array $data = []): array
    {
        // $data === req input
        // 有填銀行代碼的話再檢查
        if (!isset(self::BANK_CODE_MAP[$data['bank_code']])) {
            throw new ApiException('渠道不支持此家银行代付 ' . $data['bank_code']);
        }
        $phoneLength = 9;
        $emailLength = 6;

        $params = [
            'merchantCode' => $data['merchant_id'],
            'orderNumber' => $orderNo,
            'amount' => intval(floatval($this->convertAmount($data['amount']))),
            'bankAccount' => $data['bank_account'],
            'bankCode' => self::BANK_CODE_MAP[$data['bank_code']],
            'phone' => '08' . substr(str_shuffle(str_repeat($x = '0123456789', intval(ceil($phoneLength / strlen($x))))), 1, $phoneLength),
            'email' => substr(str_shuffle(str_repeat($x = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', intval(ceil($emailLength / strlen($x))))), 1, $emailLength) . '@gmail.com',
            'userName' => $data['user_name'],
            'channelCode' => $data['payment_channel'],
            'callbackUrl' => $this->getWithdrawNotifyUrl($data['payment_platform']),
        ];

        // SHA256(amount + bankAccount + bankCode + callbackUrl + channelCode + merchantCode + orderNumber + sha256_key)
        $sha256Key = $data['body_params']['sha256Key'];
        $tempStr = $params['amount'] . $params['bankAccount'] . $params['bankCode'] . $params['callbackUrl'] . $params['channelCode'] . $params['merchantCode'] . $params['orderNumber'] . $sha256Key;
        $this->logger->info(sprintf('origin sign: %s', $tempStr));
        $sign = hash('sha256', $tempStr);
        $this->logger->info(sprintf('sign: %s', $sign));

        // 加上签名
        $params[$this->signField] = $sign;

        return $params;
    }
}
