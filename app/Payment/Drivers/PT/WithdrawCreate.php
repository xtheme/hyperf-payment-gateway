<?php

declare(strict_types=1);

namespace App\Payment\Drivers\PT;

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

        // {"code":100,"msg":"Success","data":{"orderNo":"pt_withdraw_58491880","tradeAmt":2000.0}}
        if (100 !== $response['code']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 创建订单
        $this->createOrder($orderNo, $input, 'withdraw');

        $data = [
            'order_no' => $orderNo, // 订单号
            'trade_no' => $response['data']['payout_id'] ?? '', // 三方交易号
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
        // 有填銀行代碼的話再檢查
        if (!isset(self::BANK_CODE_MAP[$data['bank_code']])) {
            throw new ApiException('渠道不支持此家银行代付 ' . $data['bank_code']);
        }

        $params = [
            'merchantID' => $data['merchant_id'], // 商戶號
            'orderNo' => $orderNo, // 商戶訂單號
            'bankCode' => self::BANK_CODE_MAP[$data['bank_code']], // $this->transformWithdrawBankCode($data['bank_code']),
            'branch' => '1',
            'province' => '1',
            'city' => '1',
            'cardNo' => $data['bank_account'],
            'cardUser' => $data['user_name'],
            'cardUserID' => '1',
            'mobile' => '1',
            'tradeAmt' => $this->convertAmount($data['amount']),
            'notifyUrl' => $this->getWithdrawNotifyUrl($data['payment_platform']),
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $data['body_params']['secret']);

        return $params;
    }
}
