<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Lelipay;

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

        // 三方接口地址
        $endpointUrl = $input['endpoint_url'];

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

        try {
            $response = $this->sendRequest($endpointUrl, $params, $this->config['create_order']);
        } catch (\Exception $e) {
            return Response::error($e->getMessage());
        }

        // 三方返回数据校验
        if ('0000' !== $response['respCode']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 將orderDate存入redis, 查詢訂單用得到
        $input['other_params']['orderDate'] = $params['orderDate'];

        // 创建订单
        $this->createOrder($orderNo, $input);

        // 创建订单
        // $this->createOrder($orderNo, $input);
        // {
        //     "Code": "0",
        //     "MessageForUser": "OK",
        //     "MessageForSystem": "OK",
        //     "MerchantUniqueOrderId": "guid123456789",
        //     "PayOrderId": "D1234567890123456789",
        //     "Amount": "100.00",
        //     "RealAmount": "99.98",
        //     "Url": "http://www.demo.com/a/b/?d=e123456", //PayTypeIdFormat = URL 时才有本数据，否则为空字符串
        //     "BankCardRealName": "张三", //PayTypeIdFormat = CARD 时才有数据，否则为空字符串，包括下若干行数据
        //     "BankCardNumber": "888888888888888888",
        //     "BankCardBankName": "建设银行",
        //     "BankCardBankBranchName": "",
        //     "ExpiryTime": "2018-06-17 11:26:32",
        // }

        // 更新訂單
        $update = [
            'trade_no' => $response['txnId'] ?? '', // 三方交易号,
            'payee_name' => $response['BankCardRealName'] ?? '', // 收款人姓名
            'payee_bank_name' => $response['BankCardBankName'] ?? '', // 收款人开户行
            'payee_bank_branch_name' => $response['BankCardBankBranchName'] ?? '', // 收款行分/支行
            'payee_bank_account' => $response['BankCardNumber'] ?? '', // 收款人账号
        ];

        $this->updateOrder($orderNo, $update);

        // 依据三方创建支付接口返回的内容调整以下返回给集成网关的字段
        $data = [
            'order_no' => $orderNo, // 订单号
            'link' => $response['codeImgUrl'] ?? $response['codePageUrl'], // 支付网址
            'trade_no' => $response['txnId'] ?? '', // 三方交易号
            'payee_name' => $response['BankCardRealName'] ?? '', // 收款人姓名
            'payee_bank_name' => $response['BankCardBankName'] ?? '', // 收款人开户行
            'payee_bank_branch_name' => $response['BankCardBankBranchName'] ?? '', // 收款行分/支行
            'payee_bank_account' => $response['BankCardNumber'] ?? '', // 收款人账号
            'payee_nonce' => '', // 附言
            'cashier_link' => $this->getCashierUrl($orderNo), // 收銀台網址
        ];

        return Response::success($data);
    }

    /**
     * 转换三方创建订单字段
     */
    protected function prepareOrder(string $orderNo, array $data = []): array
    {
        // 去除小數點後的0
        $amount = sprintf('%g', $this->convertAmount($data['amount']));

        $orderDate = $this->getDateTime('Ymd');
        $orderTime = $this->getDateTime('His');

        $qrcode_params = [
            'accName' => $data['user_name'] ?? '',
            'txnType' => '01',
            'txnSubType' => $data['payment_channel'],
            'secpVer' => 'icp3-1.1',
            'secpMode' => 'perm',
            'macKeyId' => $data['merchant_id'],
            'orderDate' => $orderDate,
            'orderTime' => $orderTime,
            'merId' => $data['merchant_id'],
            'orderId' => $data['order_id'],
            'pageReturnUrl' => 'https://google.com',
            'notifyUrl' => $this->getNotifyUrl($data['payment_platform']),
            'productTitle' => '支付宝扫码支付',
            'txnAmt' => $amount,
            'currencyCode' => '156',
            'timeStamp' => $orderDate . $orderTime,
        ];

        $h5_params = [
            'accName' => $data['user_name'] ?? '',
            'txnType' => '01',
            'txnSubType' => $data['payment_channel'],
            'secpVer' => 'icp3-1.1',
            'secpMode' => 'perm',
            'macKeyId' => $data['merchant_id'],
            'orderDate' => $orderDate,
            'orderTime' => $orderTime,
            'merId' => $data['merchant_id'],
            'orderId' => $data['order_id'],
            'pageReturnUrl' => 'https://google.com',
            'notifyUrl' => $this->getNotifyUrl($data['payment_platform']),
            'productTitle' => '支付宝h5支付',
            'txnAmt' => $amount,
            'currencyCode' => '156',
            'clientIp' => getClientIp(),
            'sceneBizType' => 'WAP',
            'wapUrl' => 'https://wap.example.com',
            'wapName' => 'WAP',
            'timeStamp' => $orderDate . $orderTime,
        ];

        if (empty($qrcode_params['accName'])) {
            unset($qrcode_params['accName']);
        }

        if (empty($h5_params['accName'])) {
            unset($h5_params['accName']);
        }

        if ('37' == $data['payment_channel']) {
            $params = $qrcode_params;
        } else {
            $params = $h5_params;
        }

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $data['merchant_key']);

        return $params;
    }
}
