<?php

declare(strict_types=1);

namespace App\Payment\Drivers\StarPay;

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

        // 檢查金額是否為整數
        if (0 != (int) $input['amount'] % 100) {
            return Response::error('提交金额须为整数', ErrorCode::ERROR, ['amount' => $input['amount']]);
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

        // 三方返回数据校验
        if ('000' !== $response['code']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 创建订单
        $this->createOrder($orderNo, $input);

        // {
        //     "code": "000",
        //     "message": "success",
        //     "paymentUrl": "https://api.starpay.id/Cashier/index.html?orderNo=R5632AD26844FF13B",
        //     "merchantCode": "slots539",
        //     "orderNumber": "R5632AD26844FF13B"
        // }

        // 更新訂單
        $update = [
            'trade_no' => $response['data']['orderNumber'] ?? '', // 三方交易号,
            'payee_name' => $response['data']['userName'] ?? '', // 收款人姓名
            'payee_bank_name' => $response['data']['cardBank'] ?? '', // 收款人开户行
            'payee_bank_branch_name' => $response['data']['cardBranch'] ?? '', // 收款行分/支行
            'payee_bank_account' => $response['data']['walletNumber'] ?? '', // 收款人账号
        ];

        $this->updateOrder($orderNo, $update);

        // 依据三方创建支付接口返回的内容调整以下返回给集成网关的字段
        $data = [
            'order_no' => $orderNo, // 订单号
            'link' => $response['paymentUrl'], // 支付网址
            'trade_no' => $response['orderNumber'] ?? '', // 三方交易号
            'payee_name' => $response['data']['cardName'] ?? '', // 收款人姓名
            'payee_bank_name' => $response['data']['cardBank'] ?? '', // 收款人开户行
            'payee_bank_branch_name' => $response['data']['cardBranch'] ?? '', // 收款行分/支行
            'payee_bank_account' => $response['data']['cardAccount'] ?? '', // 收款人账号
            'payee_nonce' => $response['data']['remark'] ?? '', // 附言
            'cashier_link' => $this->getCashierUrl($orderNo), // 收銀台網址
        ];

        return Response::success($data);
    }

    /**
     * 转换三方创建订单字段
     */
    protected function prepareOrder(string $orderNo, array $data = []): array
    {
        // 去除小數點後的 0
        $amount = sprintf('%g', $this->convertAmount($data['amount']));
        $phoneLength = 9;
        $emailLength = 6;

        // create a name.
        $str1 = self::USERNAME_DEFAULT[rand(0, count(self::USERNAME_DEFAULT) - 1)];
        $str2 = self::USERNAME_DEFAULT[rand(0, count(self::USERNAME_DEFAULT) - 1)];
        $ar1 = explode(' ', $str1);
        $ar2 = explode(' ', $str2);
        $user_name = $ar1[0] . ' ' . $ar2[1];

        $params = [
            'merchantCode' => $data['merchant_id'],
            'orderNumber' => $orderNo,
            'amount' => (string) $amount,
            'userName' => $user_name,
            'phone' => '08' . substr(str_shuffle(str_repeat($x = '0123456789', intval(ceil($phoneLength / strlen($x))))), 1, $phoneLength),
            'email' => substr(str_shuffle(str_repeat($x = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', intval(ceil($emailLength / strlen($x))))), 1, $emailLength) . '@gmail.com',
            'channelCode' => $data['payment_channel'],
            'callbackUrl' => $this->getNotifyUrl($data['payment_platform']),
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $data['merchant_key']);

        return $params;
    }
}
