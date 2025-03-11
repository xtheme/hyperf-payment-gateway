<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Perfect;

use App\Common\Response;
use App\Constants\ErrorCode;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Stringable\Str;
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

        // 创建订单
        $this->createOrder($orderNo, $input);

        // 三方接口地址
        $endpointUrl = $request->input('endpoint_url');

        try {
            $response = $this->sendRequest($endpointUrl, $params, $this->config['create_order']);
        } catch (\Exception $e) {
            return Response::error($e->getMessage());
        }

        // 三方返回数据校验
        if (1 !== $response['code']) {
            $errorMessage = $this->transformPaymentError($response['code']);

            return Response::error('TP Error #' . $orderNo . ' ' . $errorMessage, ErrorCode::ERROR, $response);
        }

        // 銀行轉帳
        if ('0' == $params['type']) {
            $data = [
                'order_no' => $orderNo, // 订单号
                'trade_no' => $response['data']['system_sn'] ?? '', // 三方交易号
                'link' => '',
                'payee_name' => '',
                'payee_bank_name' => $response['data']['payment_code'], // 收款銀行代碼
                'payee_bank_branch_name' => '',
                'payee_bank_account' => $response['data']['payment_account'] ?? '', // 收款銀行帳號
                'payee_nonce' => '',
                'cashier_link' => $this->getCashierUrl($orderNo), // 收銀台網址
            ];
        } else {
            $data = [
                'order_no' => $orderNo, // 订单号
                'trade_no' => $response['data']['system_sn'] ?? '', // 三方交易号
                'link' => $response['data']['payment_account'], // 繳費網址
            ];
        }

        // 更新訂單
        $this->updateOrder($orderNo, $data);

        return Response::success($data);
    }

    /**
     * 转换三方创建订单字段
     */
    protected function prepareOrder(string $orderNo, array $data = []): array
    {
        $pay_content = $cvs_payment = '';

        if (Str::startsWith($data['payment_channel'], '1-')) {
            // 1-711 => 711
            // 1-Family => Family
            // 1-HiLife => HiLife
            // 1-OK => OK
            $cvs_payment = Str::replace('1-', '', $data['payment_channel']);
            $data['payment_channel'] = '1';
        }

        if ('1' == $data['payment_channel']) {
            $pay_content = '點數卡';
        }

        // 依据三方创建代收接口规范定义请求参数
        $params = [
            'agent' => $data['merchant_id'], // 商戶號
            'order_sn' => $orderNo, // 商戶訂單號
            'amount' => (int) $this->convertAmount($data['amount']), // 金額（元）精確到小數點兩位
            'type' => $data['payment_channel'], // 通道類型: 0=銀行轉帳(預設), 1=超商代碼, 2=虛擬帳號轉帳, 3=信用卡
            'bank_name' => $data['bank_name'] ?? '', // 持有人銀銀行名稱, 銀行轉帳(type:0)時該欄必填
            'bank_code' => $data['bank_code'] ?? '', // 持有人銀行代碼, 銀行轉帳(type:0)時該欄必填
            'bank_account' => $data['bank_account'] ?? '', // 持有人銀行帳號, 銀行轉帳(type:0)時該欄必填
            'user_name' => $data['user_name'] ?? '', // 付款人姓名, 超商代碼(type:1)時該欄必填
            'user_phone' => $data['user_phone'] ?? '', // 付款人電話, 超商代碼(type:3)時該欄必填
            'pay_content' => $pay_content, // 付款人姓名, 超商代碼(type:1)時該欄必填
            'cvs_payment' => $cvs_payment, // 繳費超商代碼, 7-11=711, 全家=Family, 萊爾富=HiLife, OK=OK
            'notify_url' => $this->getNotifyUrl($data['payment_platform']), // 支付成功後三方異步通知網址 (payment-gateway)
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $data['merchant_key']);

        return $params;
    }
}
