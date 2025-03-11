<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Test;

use App\Common\Response;
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
        $fakeTradeNo = base64_encode(random_bytes(10));

        $this->createOrder($orderNo, $input);

        // 请依据三方创建支付接口返回的内容调整以下返回给集成网关的字段
        $data = [
            'orderNo' => $orderNo,                           // 订单号
            'link' => 'https://yccccca.github.io/?orderId=' . $orderNo,        // 支付网址
            'trade_no' => $fakeTradeNo,
            'payee_name' => '王小明',
            'payee_bank_name' => '中國銀行',
            'payee_bank_branch_name' => '上海分行',
            'payee_bank_account' => '1234578900000',
            'cashier_link' => $this->getCashierUrl($orderNo),
        ];

        $this->updateOrder($orderNo, [
            'orderNo' => $orderNo,                           // 订单号
            'link' => 'https://yccccca.github.io/?orderId=' . $orderNo,        // 支付网址
            'trade_no' => $fakeTradeNo,
            'real_amount' => (float) $this->convertAmount($input['amount']),
            'payee_name' => '王小明',
            'payee_bank_name' => '中國銀行',
            'payee_bank_branch_name' => '上海分行',
            'payee_bank_account' => '1234578900000',
            'payee_nonce' => '收錢',
            'cashier_link' => $this->getCashierUrl($orderNo), // 收銀台網址
        ]);

        return Response::success($data);
    }

    /**
     * 转换三方创建订单字段
     */
    protected function prepareOrder(string $orderNo, array $data = []): array
    {
        // 依据三方创建代收接口规范定义请求参数
        $params = [// 三方非+0時區時需做時區校正
        ];

        return $params;
    }
}
