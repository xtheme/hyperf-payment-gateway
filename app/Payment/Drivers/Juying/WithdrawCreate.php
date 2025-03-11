<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Juying;

use App\Common\Response;
use App\Constants\ErrorCode;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Stringable\Str;
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

        // 檢查金額是否為整數
        if (0 != (int) $input['amount'] % 100) {
            return Response::error('提交金额须为整数', ErrorCode::ERROR, ['amount' => $input['amount']]);
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
        // {

        // }

        // 三方返回数据校验
        if (0 !== $response['code']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 创建订单
        $this->createOrder($orderNo, $input, 'withdraw');

        // 依据三方创建支付接口返回的内容调整以下返回给集成网关的字段
        $data = [
            'order_no' => $orderNo,                             // 订单号
            'trade_no' => $response['data']['tradeNo'] ?? '',   // 三方交易号
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
        // 產生亂碼
        $rand = str_replace('-', '', Str::uuid()->toString());

        // 取得日期
        $getTime = $this->getTimestamp();

        // 依据三方创建代付接口的参数调整以下字段
        $params = [
            'mch_id' => $data['merchant_id'],
            'nonce_str' => $rand,
            'timeStamp' => (string) $getTime,
            'orderNo' => $data['order_id'],
            'userName' => $data['user_name'],
            'score' => $data['amount'],
            'bankName' => '支付寶',
            'subName' => '',
            'cardId' => $data['bank_account'], // 支付寶帳號，確認是否只接支付寶
            'notify_url' => $this->getWithdrawNotifyUrl($data['payment_platform']),
        ];

        // 參與簽名參數
        $sign_params = [
            'mch_id' => $data['merchant_id'],
            'nonce_str' => $rand,
            'orderNo' => $data['order_id'],
            'timeStamp' => $getTime,
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($sign_params, $data['merchant_key']);

        return $params;
    }
}
