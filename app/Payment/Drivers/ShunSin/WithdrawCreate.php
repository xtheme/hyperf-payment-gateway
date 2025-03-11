<?php

declare(strict_types=1);

namespace App\Payment\Drivers\ShunSin;

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

        // {"ErrorCode":null,"ErrorMessage":null,"OrderAmount":1000.0,"MerchantOrderId":"shunsin_withdraw_41285933","Sign":"aef660d8b243686a39faf7cb1f8c2f6a"}
        if (isset($response['ErrorCode'])) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 创建订单
        $this->createOrder($orderNo, $input, 'withdraw');

        $data = [
            'order_no' => $orderNo, // 订单号
            'trade_no' => $response['payout_id'] ?? '', // 三方交易号
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
            'merchantId' => $data['merchant_id'],
            'merchantOrderId' => $orderNo,
            'orderAmount' => floatval($this->convertAmount($data['amount'])),
            'payType' => 1, // 付款方式 固定值:1
            'accountHolderName' => $data['user_name'],
            'accountNumber' => $data['bank_account'],
            'bankType' => self::BANK_CODE_MAP[$data['bank_code']], // 银行编号
            'notifyUrl' => $this->getWithdrawNotifyUrl($data['payment_platform']),
            'reverseUrl' => $this->getWithdrawNotifyUrl($data['payment_platform']),
            'submitIp' => $data['body_params']['white_ip'], // 请求的白名单ip地址
            'subBranch' => $data['branch_name'],
        ];

        $signParams = [
            'merchantId' => $params['merchantId'],
            'merchantOrderId' => $params['merchantOrderId'],
            'orderAmount' => $params['orderAmount'],
            'payType' => $params['payType'],
            'accountHolderName' => $params['accountHolderName'],
            'accountNumber' => $params['accountNumber'],
            'bankType' => $params['bankType'],
            'notifyUrl' => $params['notifyUrl'],
            'reverseUrl' => $params['reverseUrl'],
            'submitIp' => $params['submitIp'],
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($signParams, $data['merchant_key']);

        return $params;
    }
}
