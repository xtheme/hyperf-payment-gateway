<?php

declare(strict_types=1);

namespace App\Payment\Drivers\BCOTC;

use App\Common\Response;
use App\Constants\ErrorCode;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class OrderCreate extends Driver
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
        if ($this->isOrderExists($orderNo)) {
            return Response::error('商户订单号不允许重复', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 准备三方请求参数
        $params = $this->prepareOrder($orderNo, $input);

        // 三方接口地址
        $endpointUrl = $request->input('endpoint_url');

        try {
            $response = $this->sendRequest($endpointUrl, $params, $this->config['create_order']);
        } catch (\Exception $e) {
            return Response::error($e->getMessage());
        }

        // {"status":1,"msg":"","payment_url":"https://bc-otc.app/checkouts/4a101b5f-ec22-4526-89f2-41a5d7bab398","payment_information":{"bank_name":"","bank_branch":"","account_name":"","account_number":"","alipay_url":"alipays://platformapi/startapp?appId=20000116&actionType=toAccount","alipay_account_id":"4926039@qq.com","alipay_user_full_name":"潘家文"},"order_id":"4a101b5f-ec22-4526-89f2-41a5d7bab398","order_amount":{"crypto_amount":"12.660000","fiat_amount":"200.000000"}}
        if (1 !== $response['status']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 创建订单
        $this->createOrder($orderNo, $input);

        $data = [
            'order_no' => $orderNo, // 订单号
            'link' => $response['payment_information']['alipay_url'] ?? '', // 支付网址
            'trade_no' => $response['order_id'] ?? '', // 三方交易号
            // 例外字段
            'real_amount' => $response['order_amount']['fiat_amount'] ?? '', // 客户实际支付金额
            'payee_name' => $response['payment_information']['alipay_user_full_name'] ?? '', // 收款人姓名
            'payee_bank_name' => $response['payment_information']['bank_name'] ?? '', // 收款人开户行
            'payee_bank_branch_name' => $response['payment_information']['bank_branch'] ?? '', // 收款行分/支行
            'payee_bank_account' => $response['payment_information']['alipay_account_id'] ?? '', // 收款人账号
            'payee_nonce' => $response['nonce'] ?? '', // 附言
            'cashier_link' => $this->getCashierUrl($orderNo), // 收銀台網址
        ];

        // 更新訂單
        $this->updateOrder($orderNo, $data);

        return Response::success($data);
    }

    /**
     * 转换三方创建订单字段
     */
    protected function prepareOrder(string $orderNo, array $data = []): array
    {
        $params = [
            'merchant_id' => $data['merchant_id'], // 商戶號
            'merchant_order_id' => $orderNo, // 商戶訂單號
            'amount' => intval($this->convertAmount($data['amount'])), // 金額（元）精確到小數點兩位
            'notify_url' => $this->getNotifyUrl($data['payment_platform']), // 支付成功後三方異步通知網址 (payment-gateway)
            'payer' => $data['user_name'], // 商⼾提供的付款⼈姓名（⻓度不可超过 4 个字，且不能含有英⽂）
            'payment_method' => intval($data['payment_channel']), // 通道類型
            'apply_timestamp' => $this->getTimestamp(), // 三方非+0時區時需做時區校正
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $data['merchant_key']);

        return $params;
    }
}
