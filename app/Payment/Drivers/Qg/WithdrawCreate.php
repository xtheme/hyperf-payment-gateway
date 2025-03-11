<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Qg;

use App\Common\Response;
use App\Constants\ErrorCode;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class WithdrawCreate extends Driver
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
        if ($this->isOrderExists($orderNo, 'withdraw')) {
            return Response::error('商户订单号不允许重复', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 組合 Header
        $this->withHeaders(json_decode(json_encode($input['header_params']), true));

        // 準備三方請求參數
        $params = $this->prepareOrder($orderNo, $input);

        // 三方接口地址
        $endpointUrl = $request->input('endpoint_url');

        try {
            $response = $this->sendRequest($endpointUrl, $params, $this->config['create_order']);
        } catch (\Exception $e) {
            return Response::error($e->getMessage());
        }

        // 三方返回数据示例
        // {
        // "data": {
        //     8
        //     "order_sid": "202302242300001",
        //     "order_id": "a10016",
        //     "bank_title": "王小明",
        //     "bank_name": "建设银行",
        //     "bank_no": "1234567890",
        //     "amount": 100,
        //     "init_time": "2023-02-24 23:04:55"
        //     },
        //     "code": 200
        // }

        // 三方返回数据校验
        if (200 !== $response['code']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 创建订单
        $this->createOrder($orderNo, $input, 'withdraw');

        // 依据三方创建支付接口返回的内容调整以下返回给集成网关的字段
        $data = [
            'order_no' => $orderNo,                             // 订单号
            'trade_no' => $response['data']['order_sid'] ?? '', // 三方交易号
        ];

        // 更新訂單 trade_no
        $update = [
            'trade_no' => $data['trade_no'],
        ];
        $this->updateOrder($orderNo, $update, 'withdraw');

        return Response::success($data);
    }

    /**
     * 转换三方创建订单字段
     */
    protected function prepareOrder(string $orderNo, array $data = []): array
    {
        // 依据三方创建代付接口的参数调整以下字段
        $params = [
            'payer' => $data['user_name'],
            'order_id' => $data['order_id'],
            'amount' => $this->convertAmount($data['amount']),
            'bank_title' => $data['user_name'], // 確認參數
            'bank_name' => $data['bank_name'],
            'bank_no' => $data['bank_account'],
            'callback' => $this->getWithdrawNotifyUrl($data['payment_platform']),
            'message' => '',
        ];

        // 加上签名
        // $params[$this->signField] = $this->getSignature($params, $data['merchant_key']);

        return $params;
    }
}
