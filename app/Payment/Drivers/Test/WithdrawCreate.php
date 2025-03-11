<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Test;

use App\Common\Response;
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

        // 依据三方创建支付接口返回的内容调整以下返回给集成网关的字段
        $data = [
            'order_no' => $orderNo,                             // 订单号
            'trade_no' => base64_encode(random_bytes(10)), // 三方交易号
        ];

        return Response::success($data);
    }

    /**
     * 转换三方创建订单字段
     */
    protected function prepareOrder(string $orderNo, array $data = []): array
    {
        // 依据三方创建代付接口的参数调整以下字段
        $params = [
            'merchantCode' => $data['merchant_id'],
            'merchantOrderId' => $orderNo,
            'amount' => $this->convertAmount($data['amount']),
            'notifyUrl' => $this->getWithdrawNotifyUrl($data['payment_platform']),
            'payeeName' => $data['user_name'],
            'payeeAccount' => $data['bank_account'],
            'payeeBankId' => $data['bank_code'],
            'requestTime' => $this->getTimestamp(),
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $data['merchant_key']);

        return $params;
    }
}
