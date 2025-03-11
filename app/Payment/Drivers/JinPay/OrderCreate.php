<?php

declare(strict_types=1);

namespace App\Payment\Drivers\JinPay;

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

        // {"code":0,"msg":"success","data":{"orderId":"202407291736140000","orderNo":"jinpay_69640033","amount":"10000","userId":"IDS88","sign":"9db14dbcd2cf6be5c4a33d12a9a8350e","info":{"payUrl":"https://api.starpay.id/Cashier/index.html?orderNo=K4C7A5B10F6B2F1BE","account":null,"accountName":null,"accountNo":null}}}
        if (0 !== $response['code']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        /*
        if (false === $this->verifySignature($response['data'], $input['merchant_key'])) {
            return Response::error('验证签名失败', ErrorCode::ERROR, $response);
        }
        */

        // 创建订单
        $this->createOrder($orderNo, $input);

        $data = [
            'order_no' => $orderNo, // 订单号
            'link' => $response['data']['info']['payUrl'], // 支付网址
            'trade_no' => $response['data']['orderId'] ?? '', // 三方交易号
            // 收銀台字段
            'real_amount' => $response['data']['amount'] ?? '', // 客户实际支付金额
            'payee_name' => $response['data']['info']['accountName'] ?? '', // 收款人姓名
            'payee_bank_name' => $response['card_bank'] ?? '', // 收款人开户行
            'payee_bank_branch_name' => $response['card_branch'] ?? '', // 收款行分/支行
            'payee_bank_account' => $response['data']['info']['accountNo'] ?? '', // 收款人账号
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
        // create a name.
        $str1 = self::USERNAME_DEFAULT[rand(0, count(self::USERNAME_DEFAULT) - 1)];
        $str2 = self::USERNAME_DEFAULT[rand(0, count(self::USERNAME_DEFAULT) - 1)];
        $ar1 = explode(' ', $str1);
        $ar2 = explode(' ', $str2);
        $user_name = $ar1[0] . ' ' . $ar2[1];

        $params = [
            'appId' => $data['merchant_id'], // 商戶號
            'orderId' => $orderNo, // 商戶訂單號
            'notifyUrl' => $this->getNotifyUrl($data['payment_platform']), // 支付成功後三方異步通知網址 (payment-gateway)
            'pageUrl' => $this->getReturnUrl(), // 支付成功後轉跳網址
            'amount' => strval(intval($this->convertAmount($data['amount']))), // 金額（元）精確到小數點兩位
            'applyDate' => $this->getDateTime('YmdHis'), // 三方非+0時區時需做時區校正
            'passCode' => $data['payment_channel'], // 通道類型
            'currency' => 'IDR',
            'mcPayName' => $user_name,
            'accountEmail' => substr(md5($this->getDateTime()), 0, 7) . '@' . substr(md5(strval($this->getTimestamp())), 0, 3) . '.com',
            'accountPhone' => '0812' . substr(strval($this->getTimestamp()), 3, 7),
        ];

        $signParams = $params;
        unset($signParams['currency'], $signParams['accountEmail'], $signParams['accountPhone'], $signParams['customer']);

        // 加上签名
        $params[$this->signField] = $this->getSignature($signParams, $data['merchant_key']);

        return $params;
    }
}
