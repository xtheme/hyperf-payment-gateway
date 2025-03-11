<?php

declare(strict_types=1);

namespace App\Payment\Drivers\CashyPay;

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

        // {"msg":"SUCCESS","code":200,"data":{"merchantId":"3006075","mchOrderNo":"cashypay_93649009","orderNo":"PAYIN8506530188485210112","payCode":"88089999525724","payUrl":null,"payDeskUrl":"http://testpaydesk2.idcashypay.com/views/pay.html?PAYIN8506530188485210112","amount":"1000000","orderFee":"40000"}}
        if (200 !== $response['code']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 创建订单
        $this->createOrder($orderNo, $input);

        $data = [
            'order_no' => $orderNo, // 订单号
            'link' => $response['data']['payDeskUrl'], // 支付网址
            'trade_no' => $response['data']['orderNo'] ?? '', // 三方交易号
            // 收銀台字段
            'real_amount' => $response['data']['amount'] ?? '', // 客户实际支付金额
            'payee_name' => $response['card_name'] ?? '', // 收款人姓名
            'payee_bank_name' => $response['card_bank'] ?? '', // 收款人开户行
            'payee_bank_branch_name' => $response['card_branch'] ?? '', // 收款行分/支行
            'payee_bank_account' => $response['data']['payCode'] ?? '', // 收款人账号
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
            'currency' => 'IDR',
            'payType' => $data['payment_channel'], // 通道類型
            'amount' => intval($this->convertAmount($data['amount'])), // 金額（元）精確到小數點兩位
            'reusableStatus' => false,
            'mchOrderNo' => $orderNo, // 商戶訂單號
            'expireTime' => 3600,
            'notifyUrl' => $this->getNotifyUrl($data['payment_platform']), // 支付成功後三方異步通知網址 (payment-gateway)
            'nonceStr' => $this->getTimestamp(),
            'remark' => $orderNo,
            'realName' => $user_name,
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
