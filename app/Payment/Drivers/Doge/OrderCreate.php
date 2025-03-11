<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Doge;

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

        // {"data":{"amount":"2000.00","casher_url":"https://api.dogepay168.net/api/v1/cashier/GX20240429180542121372","confirmed_at":"","created_at":"2024-04-29T18:05:42+08:00","note":"","notify_url":"http://127.0.0.1:9503/api/v1/payment/notify/doge","order_number":"doge_94330792","receiver_account":"1688888","receiver_bank_branch":"11","receiver_bank_name":"中国建设银行","receiver_name":"胖 虎","return_url":"https://www.baidu.com","status":3,"system_order_number":"GX20240429180542121372","username":"yg8888","sign":"5992e4a45cdcb7ee5a73ad78c8cb6a92"},
        // "http_status_code":201,"message":"匹配成功"}
        if (200 !== $response['http_status_code'] && 201 !== $response['http_status_code']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 创建订单
        $this->createOrder($orderNo, $input);

        $data = [
            'order_no' => $orderNo, // 订单号
            'link' => $response['data']['casher_url'] ?? '', // 支付链结
            'trade_no' => $response['data']['system_order_number'] ?? '', // 支付交易订单号
            'payee_name' => $response['data']['name'] ?? '', // 收款人姓名
            'payee_bank_name' => $response['data']['bank'] ?? '', // 收款人开户行
            'payee_bank_branch_name' => $response['data']['subbank'] ?? '', // 收款行分/支行
            'payee_bank_account' => $response['data']['bankaccount'] ?? '', // 收款人账号
            'cashier_link' => $this->getCashierUrl($orderNo), // 收銀台網址
        ];
        $this->updateOrder($orderNo, $data);

        return Response::success($data);
    }

    /**
     * 转换三方创建订单字段
     */
    protected function prepareOrder(string $orderNo, array $data = []): array
    {
        $params = [
            'channel_code' => 'BANK_CARD',
            'username' => $data['merchant_id'], // 商戶號
            'amount' => $this->convertAmount($data['amount']), // 金額（元）精確到小數點兩位
            'order_number' => $orderNo, // 商戶訂單號
            'notify_url' => $this->getNotifyUrl($data['payment_platform']), // 支付成功後三方異步通知網址 (payment-gateway)
            'return_url' => $this->getReturnUrl(), // 支付成功後轉跳網址
            'real_name' => $data['user_name'],
            'client_ip' => getClientIp(),
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $data['merchant_key']);

        return $params;
    }
}
