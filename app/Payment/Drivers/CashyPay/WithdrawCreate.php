<?php

declare(strict_types=1);

namespace App\Payment\Drivers\CashyPay;

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

        // {"msg":"SUCCESS","code":200,"data":{"merchantId":"3006075","mchOrderNo":"cashypay_withdraw_95054077","orderNo":"PAYOUT8506533805053194240","amount":"1000000","fee":"0","orderStatus":"PROCESSING"}}
        if (200 !== $response['code']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 创建订单
        $this->createOrder($orderNo, $input, 'withdraw');

        $data = [
            'order_no' => $orderNo, // 订单号
            'trade_no' => $response['data']['orderNo'] ?? '', // 三方交易号
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
        // 有填銀行代碼的話再檢查
        if (!isset(self::BANK_CODE_MAP[$data['bank_code']])) {
            throw new ApiException('渠道不支持此家银行代付 ' . $data['bank_code']);
        }

        $params = [
            'accountName' => $data['user_name'],
            'accountNo' => $data['bank_account'],
            'accountType' => $data['payment_channel'],
            'currency' => 'IDR',
            'amount' => floatval($this->convertAmount($data['amount'])),
            'bankId' => self::BANK_CODE_MAP[$data['bank_code']],
            'mchOrderNo' => $orderNo,
            'notifyUrl' => $this->getWithdrawNotifyUrl($data['payment_platform']),
            'nonceStr' => $this->getTimestamp(),
            'remark' => $orderNo,
        ];

        // header 參數
        $head = [
            'MerchantId' => $data['merchant_id'],
            $this->signField => $this->getSignature($params, $data['merchant_key']),
        ];
        $this->appendHeaders($head);

        return $params;
    }
}
