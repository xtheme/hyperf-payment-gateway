<?php

declare(strict_types=1);

namespace App\Payment\Drivers\ShuiHu;

use App\Common\Response;
use App\Constants\ErrorCode;
use Carbon\Carbon;
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

        // 三方返回数据示例
        // {
        //     "code": 0,
        //     "message": "ok",
        //     "data": {
        //         "mchId": "M1681128855",
        //         "tradeNo": "P1645632755396845568",
        //         "outTradeNo": "xusheng_order47755944",
        //         "originTradeNo": "1",
        //         "amount": "80000",
        //         "payUrl": "http://pay.hgnewcloud.com/c/api/pay?osn=2023041111400440421112344",
        //         "expiredTime": "1681184704",
        //         "sdkData": null
        //     },
        //     "sign": "f67a3ba32d42549785273a6f44f84ded"
        // }

        // $sample   = '{"code":0,"message":"ok","data":{"mchId":"M1681128855","tradeNo":"P1645632755396845568","outTradeNo":"xusheng_order47755944","originTradeNo":"1","amount":"80000","payUrl":"http://pay.hgnewcloud.com/c/api/pay?osn=2023041111400440421112344","expiredTime":"1681184704","sdkData":null},"sign":"f67a3ba32d42549785273a6f44f84ded"}';
        // $response = json_decode($sample, true);

        // 三方返回数据校验
        if (0 !== $response['code']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 创建订单
        $this->createOrder($orderNo, $input);

        // 请依据三方创建支付接口返回的内容调整以下返回给集成网关的字段
        $data = [
            'orderNo' => $orderNo,                           // 订单号
            'link' => $response['data']['payUrl'],        // 支付网址
            'trade_no' => $response['data']['tradeNo'] ?? '', // 三方交易号
            'payee_name' => $response['data']['bank_userame'] ?? '', // 收款人姓名
            'payee_bank_name' => $response['data']['bank_name'] ?? '', // 收款人开户行
            'payee_bank_branch_name' => $response['data']['bank_branch'] ?? '', // 收款行分/支行
            'payee_bank_account' => $response['data']['bank_account'] ?? '', // 收款人账号
            'cashier_link' => $this->getCashierUrl($orderNo), // 收銀台網址
        ];

        // 更新訂單 trade_no
        $update = [
            'trade_no' => $data['trade_no'],
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
            'mchId' => $data['merchant_id'],
            'wayCode' => $data['payment_channel'],
            'subject' => $data['site_id'], // 訂單標題 (會員ID)
            'outTradeNo' => $orderNo,
            'amount' => $this->convertAmount($data['amount']), // 金額（元）精確到小數點兩位
            'clientIp' => getClientIp(),
            'notifyUrl' => $this->getNotifyUrl($data['payment_platform']), // 支付成功後三方異步通知網址 (payment-gateway)
            'reqTime' => Carbon::now()->getTimestampMs(),                      // 三方非+0時區時需做時區校正
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $data['merchant_key']);

        return $params;
    }
}
