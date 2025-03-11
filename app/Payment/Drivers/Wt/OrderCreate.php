<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Wt;

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

        // 创建订单
        $this->createOrder($orderNo, $input);

        // 三方接口地址
        $endpointUrl = $request->input('endpoint_url');

        try {
            $response = $this->sendRequest($endpointUrl, $params, $this->config['create_order']);
        } catch (\Exception $e) {
            return Response::error($e->getMessage());
        }

        // 三方返回数据校验
        if ('0000' !== $response['error_code']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 三方返回示例
        // {
        //     "error_code": "0000",
        //     "data": {
        //         "link": "https://hbj168.club/payment/index.html?token=eyJhbGciOiJIUzI1NiJ9.eyJ1c2VySWQiOjAsInBsYXRmb3JtSWQiOjI1MywiYWdlbnRJZCI6MCwidmVyc2lvbiI6MSwicGF5bWVudElkIjozMTUwOTgsImlhdCI6MTY3OTQ2MTQxNiwiZXhwIjoxNjc5NDYyMDE2fQ.CyS0cGQ2WnyvB8UlAt54Jr3DGYj9-6kNfsPB_zkH_Gs&lang=cn",
        //         "payment_info": {
        //             "amount": 100000,
        //             "display_amount": 100000,
        //             "payment_id": "WUTONGPM00315098",
        //             "payment_cl_id": "wt_order19517494",
        //             "receiver": {
        //                 "card_name": "冯晓鹏",
        //                 "card_number": "621452001008373668",
        //                 "bank_name": "天津银行",
        //                 "bank_branch": "天津津财支行",
        //                 "bank_logo": "https://apimg.alipay.com/combo.png?d=cashier&t=TCCB"
        //             },
        //             "sender": {
        //                 "card_name": "王大拿",
        //                 "card_number": "*************",
        //                 "bank_id": "BK0000",
        //                 "bank_code": "DEFAULT",
        //                 "bank_name": "",
        //                 "bank_logo": "https://apimg.alipay.com/combo.png?d=cashier&t=DEFAULT",
        //                 "bank_link": ""
        //             },
        //             "token": "无须附言"
        //         }
        //     }
        // }

        // 返回给集成网关的字段
        $data = [
            'order_no' => $orderNo,                                              // 订单号
            'link' => $response['data']['link'],                             // 支付网址
            'trade_no' => $response['data']['payment_info']['payment_id'] ?? '', // 三方交易号
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
            'platform_id' => $data['merchant_id'],
            'service_id' => $data['payment_channel'],
            'payment_cl_id' => $orderNo,
            'name' => $data['user_name'],
            'amount' => $this->convertAmount($data['amount']),
            'notify_url' => $this->getNotifyUrl($data['payment_platform']),
            'request_time' => strval(round(microtime(true))),
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $data['merchant_key']);

        return $params;
    }
}
