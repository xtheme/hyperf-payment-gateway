<?php

declare(strict_types=1);

namespace App\Payment\Drivers\MaShang;

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

        // {"result":true,"errorMsg":null,"data":{"gamerOrderId":"D17159168547410621","httpUrl":"http://rrcft.papapay.xyz/scan_test/?q=asdfasdf&m=B_TO_B&a=1000.00","httpsUrl":"https://rrcft.papapay.xyz/scan_test/?q=asdfasdf&m=B_TO_B&a=1000.00","sign":"22b97a054d060c9c7d7833e3b9aacfb3"}}
        // {"result":true,"errorMsg":null,"data":{"bankName":"测试银行","bankAccountNumber":"15347891566632154","bankAccountName":"测试帐号","amount":"2000.00","gamerOrderId":"D17159285217270050","sign":"23c59ace9ae5b156384ea24eddf2572c","bankAccountBranch":""}}
        if (true !== $response['result']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 验证签名
        $signRes = $response['data'];

        if (isset($signRes['bankAccountBranch'])) {
            unset($signRes['bankAccountBranch']);
        }

        if (false === $this->verifySignature($signRes, $input['merchant_key'])) {
            return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 创建订单
        $this->createOrder($orderNo, $input);

        $data = [
            'order_no' => $orderNo, // 订单号
            'link' => $response['data']['httpsUrl'] ?? '', // 支付网址
            'trade_no' => $response['data']['gamerOrderId'] ?? '', // 三方交易号
            // 例外字段
            'real_amount' => $response['data']['amount'] ?? '', // 客户实际支付金额
            'payee_name' => $response['data']['bankAccountName'] ?? '', // 收款人姓名
            'payee_bank_name' => $response['data']['bankName'] ?? '', // 收款人开户行
            'payee_bank_branch_name' => $response['data']['bankAccountBranch'] ?? '', // 收款行分/支行
            'payee_bank_account' => $response['data']['bankAccountNumber'] ?? '', // 收款人账号
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
            'merchantCode' => $data['merchant_id'], // 商戶號
            'merchantOrderId' => $orderNo, // 商戶訂單號
            'paymentTypeCode' => $data['payment_channel'], // 通道類型
            'amount' => $this->convertAmount($data['amount']), // 金額（元）精確到小數點兩位
            'successUrl' => $this->getNotifyUrl($data['payment_platform']), // 支付成功後三方異步通知網址 (payment-gateway)
            'merchantMemberId' => $orderNo,
            'merchantMemberIp' => getClientIp(),
            'payerName' => $data['user_name'],
        ];

        $signParams = [
            'merchantCode' => $params['merchantCode'],
            'merchantOrderId' => $params['merchantOrderId'],
            'paymentTypeCode' => $params['paymentTypeCode'],
            'amount' => $params['amount'],
            'successUrl' => $params['successUrl'],
            'merchantMemberId' => $params['merchantMemberId'],
            'merchantMemberIp' => $params['merchantMemberIp'],
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($signParams, $data['merchant_key']);

        return $params;
    }
}
