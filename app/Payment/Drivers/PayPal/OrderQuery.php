<?php

declare(strict_types=1);

namespace App\Payment\Drivers\PayPal;

use App\Common\Response;
use App\Exception\ApiException;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class OrderQuery extends Driver
{
    /**
     * 查詢訂單
     */
    public function request(RequestInterface $request): ResponseInterface
    {
        $input = $request->all();

        // 商户订单号
        $orderNo = $input['order_id'];

        // 查询订单
        $order = $this->getOrder($orderNo);

        // 三方接口地址
        $endpointUrl = $request->input('endpoint_url');

        // 取得 Access Token
        $accessToken = base64_encode($input['merchant_id'] . ':' . $input['merchant_key']);

        // 創建 PayPal-Request-Id 並保存在訂單資料中
        $input['header_params'] = [
            'Authorization' => 'Basic ' . $accessToken,
        ];
        $this->withHeaders($input['header_params']);

        try {
            $endpointUrl = $endpointUrl . '/' . $order['trade_no'];

            $response = $this->sendRequest($endpointUrl, [], $this->config['query_order']);
        } catch (\Exception $e) {
            throw new ApiException($e->getMessage());
        }

        // 統一狀態後返回集成网关
        $data = $this->transferOrderInfo($response, $order);

        return Response::success($data);
    }

    /**
     * 返回整理过的订单资讯给集成网关
     */
    public function transferOrderInfo(array $data, array $order): array
    {
        // {
        //     'id': '42C3139620101125G',
        //     'intent': 'CAPTURE',
        //     'status': 'CREATED',
        //     'purchase_units': [
        //         {
        //             'reference_id': 'paypal_1717645310095',
        //             'amount': {
        //             'currency_code': 'TWD',
        //                 'value': '100.00'
        //             },
        //             'payee': {
        //             'email_address': 'sb-issxk31072830@business.example.com',
        //                 'merchant_id': '4PJ6TMWHVMVDQ'
        //             },
        //             'description': '用戶充值',
        //             'custom_id': ''
        //         }
        //     ],
        //     'create_time': '2024-06-06T03:41:51Z',
        //     'links': [
        //         {
        //             'href': 'https://api.sandbox.paypal.com/v2/checkout/orde2C3139620101125G',
        //             'rel': 'self',
        //             'method': 'GET'
        //         },
        //         {
        //             'href': 'https://www.sandbox.paypal.com/checkoutnow?token=42C3139620101125G',
        //             'rel': 'approve',
        //             'method': 'GET'
        //         },
        //         {
        //             'href': 'https://api.sandbox.paypal.com/v2/checkout/orders/42C3139620101125G',
        //             'rel': 'update',
        //             'method': 'PATCH'
        //         },
        //         {
        //             'href': 'https://api.sandbox.paypal.com/v2/checkout/orders/42C3139620101125G/capture',
        //             'rel': 'capture',
        //             'method': 'POST'
        //         }
        //     ]
        // }

        // 依据三方逻辑转换字段, $order 是缓存资料, 建议用三方返回的 $data 满足以下条件
        return [
            'amount' => $order['amount'],
            'real_amount' => $order['amount'],
            'order_no' => $order['order_no'],
            'trade_no' => $order['trade_no'],
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $order['payment_channel'],
            'status' => $this->transformStatus($data['status']),
            'remark' => '',
            'created_at' => $this->getTimestamp(), // 集成使用 UTC
            'raw' => json_encode($data, JSON_UNESCAPED_SLASHES), // 三方返回的原始资料
        ];
    }
}
