<?php

declare(strict_types=1);

namespace App\Payment\Drivers\GOSM;

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

        // {"code":0,"message":"Success","result":{"SystemOrderId":"PA2024053010501246765","amount":0.95,"account":"CAVY80VK","series":2,"bankName":"","subBank":"","CollectAccount":"11223355＠11223","CollectName":"测试1","Url":"www.wdfnjn.com/#/checkoutCounter?key=30e277ebcb234b928d217bd1e97b5c0544a7deb77c67665721c648f2b0e00647","FullUrl":"https://www.wdfnjn.com/#/checkoutCounter?key=30e277ebcb234b928d217bd1e97b5c0544a7deb77c67665721c648f2b0e00647"}}
        if (0 !== $response['code']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 创建订单
        $this->createOrder($orderNo, $input);

        $data = [
            'order_no' => $orderNo, // 订单号
            'link' => $response['result']['FullUrl'], // 支付网址
            'trade_no' => $response['result']['SystemOrderId'] ?? '', // 三方交易号
            // 例外字段
            'real_amount' => $response['result']['amount'] ?? '', // 客户实际支付金额
            'payee_name' => $response['result']['CollectName'] ?? '', // 收款人姓名
            'payee_bank_name' => $response['result']['bankName'] ?? '', // 收款人开户行
            'payee_bank_branch_name' => $response['result']['subBank'] ?? '', // 收款行分/支行
            'payee_bank_account' => $response['result']['CollectAccount'] ?? '', // 收款人账号
            'payee_nonce' => $response['result']['series'] ?? '', // 附言
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
            'account' => $data['merchant_id'], // 商戶號
            'amount' => floatval($this->convertAmount($data['amount'])), // 金額（元）精確到小數點兩位
            'payName' => $data['user_name'],
            'userIp' => getClientIp(),
            'storeOrderCode' => $orderNo, // 商戶訂單號
            'storeUrl' => $this->getNotifyUrl($data['payment_platform']), // 支付成功後三方異步通知網址 (payment-gateway)
            'series' => intval($data['payment_channel']), // 交易类型 1:银⾏卡, 2:⽀付宝, 3:微信, 4:数字⼈⺠币, 5.USDT, 6.云闪付
            'submitTime' => $this->getTimestamp(), // 三方非+0時區時需做時區校正
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $data['merchant_key']);

        return $params;
    }
}
