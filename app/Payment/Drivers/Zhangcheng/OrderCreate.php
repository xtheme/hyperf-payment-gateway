<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Zhangcheng;

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
        if ('SUCCESS' !== $response['retCode']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 创建订单
        $this->createOrder($orderNo, $input);

        // {
        //     "code": 0,
        //  "message": "success",
        //  "data": {
        //  "order_id": "11627721",
        //  "transaction_id": "T03300222211627721",
        //  "view_url": "https://today/order_T03300222211627721.html",
        //  "qr_url": "https://api.qrserver.com/v1/create-qr-code/?data=https://today/
        // order_T033",
        //  "expired": "2021-03-30 02:32:21",
        //  "user_name": "安安",
        //  "bill_price": 199,
        //  "real_price": 199,
        //  "bank_no": "12345678901234567890",
        //  "bank_name": "中国⼯商银⾏",
        //  "bank_from": "",
        //  "bank_owner": "张三三",
        //  "remark": ""
        //         }
        // }

        // 更新訂單
        $update = [
            'trade_no' => $response['payOrderId'], // 三方交易号,建單無三方訂單編號
            'payee_name' => $response['payParams']['bankOwner'] ?? '', // 收款人姓名
            'payee_bank_name' => $response['payParams']['bankName'] ?? '', // 收款人开户行
            'payee_bank_branch_name' => $response['payParams']['bankFrom'] ?? '', // 收款行分/支行
            'payee_bank_account' => $response['payParams']['bankNo'] ?? '', // 收款人账号
        ];

        $this->updateOrder($orderNo, $update);

        // 依据三方创建支付接口返回的内容调整以下返回给集成网关的字段
        $data = [
            'order_no' => $orderNo, // 订单号
            'link' => $response['payParams']['payUrl'], // 支付网址
            'trade_no' => $response['payOrderId'], // 三方交易号
            'payee_name' => $response['payParams']['bankOwner'] ?? '', // 收款人姓名
            'payee_bank_name' => $response['payParams']['bankName'] ?? '', // 收款人开户行
            'payee_bank_branch_name' => $response['payParams']['bankFrom'] ?? '', // 收款行分/支行
            'payee_bank_account' => $response['payParams']['bankNo'] ?? '', // 收款人账号
            'payee_nonce' => $response['payParams']['memoId'] ?? '', // 附言
            'cashier_link' => $this->getCashierUrl($orderNo), // 收銀台網址
        ];

        return Response::success($data);
    }

    /**
     * 转换三方创建订单字段
     */
    protected function prepareOrder(string $orderNo, array $data = []): array
    {
        // 準備三方所需參數

        $params1 = [
            'mchId' => (int) $data['merchant_id'],
            'appId' => $data['body_params']['appId'], // 待確認 要填入應用 ID 需要欄位
            'productId' => (int) $data['payment_channel'],
            'mchOrderNo' => $data['order_id'],
            'currency' => 'CNY',
            'amount' => (int) $this->convertAmount($data['amount']),
            'notifyUrl' => $this->getNotifyUrl($data['payment_platform']),
            'subject' => '支付宝', // 隨意填
            'body' => '代收支付宝', // 隨意填
            'param1' => $data['user_name'],
        ];

        $sign_key = $data['merchant_key'] . $data['body_params']['customer_service_key'];

        // 加上签名
        $params1[$this->signField] = $this->getSignature($params1, $sign_key);

        $params = [
            'params' => json_encode($params1),
        ];

        return $params;
    }
}
