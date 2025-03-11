<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Qg;

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

        // 從四方請求中拉出商户订单号
        $orderNo = $input['order_id'];

        // 檢查 redis 中訂單號是否唯一
        if ($this->isOrderExists($orderNo)) {
            return Response::error('商户订单号不允许重复', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 准备三方请求参数，params 轉換在最下方
        $params = $this->prepareOrder($orderNo, $input);

        // 三方接口地址
        $endpointUrl = $request->input('endpoint_url');

        // 組合 Header
        $this->withHeaders($input['header_params']);

        // 對三方發起請求
        try {
            $response = $this->sendRequest($endpointUrl, $params, $this->config['create_order']);
        } catch (\Exception $e) {
            return Response::error($e->getMessage());
        }

        // 三方返回数据校验
        if (200 !== $response['code']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 创建订单
        $this->createOrder($orderNo, $input);

        // QG 三方返回示例
        // "data": {
        //      "order_sid": "202302242200003",
        //      "order_id": "a10015",
        //      "bank_title": "测试会员",
        //      "bank_name": "测试银行",
        //      "bank_no": "001001001001",
        //      "amount": 100,
        //      "init_time": "2023-02-24 22:55:23",
        //      “fronttable_url": "https://somewhere.com/abcdefg"
        // },
        // "code": 200
        // }

        // 返回给集成网关的字段
        $data = [
            'order_no' => $orderNo,                                              // 订单号
            'link' => $response['data']['fronttable_url'],                       // 支付网址
            'trade_no' => $response['data']['order_sid'] ?? '',                  // 三方交易号
        ];

        // 收款人姓名
        if (!empty($response['data']['bank_title'])) {
            $data['payee_name'] = $response['data']['bank_title'];
        }

        // 收款人开户行
        if (!empty($response['data']['bank_name'])) {
            $data['payee_bank_name'] = $response['data']['bank_name'];
        }

        // 收款行分/支行
        if (!empty($response['data']['bank_branch'])) {
            $data['payee_bank_branch_name'] = $response['data']['bank_branch'];
        }

        // 收款人帐号
        if (!empty($response['data']['bank_no'])) {
            $data['payee_bank_account'] = $response['data']['bank_no'];
        }

        // 更新訂單 trade_no
        $update = [
            'trade_no' => $data['trade_no'],
            'payee_name' => $response['data']['bank_title'] ?? '', // 收款人姓名
            'payee_bank_name' => $response['data']['bank_name'] ?? '', // 收款人开户行
            'payee_bank_branch_name' => $response['data']['bank_branch'] ?? '', // 收款行分/支行
            'payee_bank_account' => $response['data']['bank_no'] ?? '', // 收款人账号
        ];
        $this->updateOrder($orderNo, $update);

        return Response::success($data);
    }

    /**
     * 转换三方创建订单字段
     */
    protected function prepareOrder(string $orderNo, array $data = []): array
    {
        // 依据三方创建代收接口规范定义请求参数
        $params = [
            'payer' => $data['user_name'],
            'order_id' => $data['order_id'],
            'amount' => $this->convertAmount($data['amount']),
            'callback' => $this->getNotifyUrl($data['payment_platform']),
        ];

        // 加上签名，此支付創建訂單不須加簽
        // $params[$this->signField] = $this->getSignature($params, $data['merchant_key']);

        return $params;
    }
}
