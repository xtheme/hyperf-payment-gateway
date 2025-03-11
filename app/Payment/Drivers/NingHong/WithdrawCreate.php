<?php

declare(strict_types=1);

namespace App\Payment\Drivers\NingHong;

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

        $bankInfo = self::BANK_CODE_MAP[$input['bank_code']] ?? [];

        if (!$bankInfo) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, ['order_no' => $orderNo, 'bank_code' => $input['bank_code']]);
        } else {
            $input['bank_code'] = $bankInfo['code'];
            $input['bank_name'] = $bankInfo['name'];
        }

        // 準備三方請求參數
        $params = $this->prepareOrder($orderNo, $input);

        // 三方接口地址
        $endpointUrl = $request->input('endpoint_url');

        // 取得商戶 Token
        $token = $this->getMerchantToken($input);
        $this->withToken($token);

        try {
            $response = $this->sendRequest($endpointUrl, $params, $this->config['create_order']);
        } catch (\Exception $e) {
            return Response::error($e->getMessage());
        }

        // 三方返回数据校验
        if (true !== $response['success']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 创建订单
        $this->createOrder($orderNo, $input, 'withdraw');

        // 请依据三方创建代付接口返回的内容调整以下返回给集成网关的字段
        $data = [
            'order_no' => $orderNo, // 订单号
            'trade_no' => $response['payout']['txid'] ?? '', // 三方交易号
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

        // 请依据三方创建代付接口的参数调整以下字段
        return [
            'method' => $data['payment_channel'],
            'amount' => $this->convertAmount($data['amount']),
            // 'remarks' => 'optional|string',
            // 'user_id' => 'optional|integer',
            'mer_tx' => $orderNo,
            'username' => $data['user_name'],
            'bankcode' => $data['bank_code'],
            'bankname' => $data['bank_name'],
            'accno' => $data['bank_account'],
            'accname' => $data['user_name'],
            'callback' => $this->getWithdrawNotifyUrl($data['payment_platform']),
        ];
    }
}