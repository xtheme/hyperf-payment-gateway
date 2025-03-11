<?php

declare(strict_types=1);

namespace App\Payment\Drivers\PayPal;

use App\Common\Response;
use App\Constants\ErrorCode;
use App\Model\Order;
use App\Payment\OrderDrivers\CacheOrder;
use FriendsOfHyperf\Cache\Facade\Cache;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function Hyperf\Config\config;

class OrderNotify extends Driver
{
    /**
     * 三方回調通知, 更新訂單
     */
    public function request(RequestInterface $request): ResponseInterface
    {
        $callback = $request->all();

        // PayPal 三方訂單號
        $tradeNo = $callback['id'] ?? '';

        $this->logger->info(sprintf('PayPal %s 回調參數', $tradeNo), $callback);

        // $tradeNo => $orderNo
        if (CacheOrder::class === config('payment.order_driver')) {
            $orderNo = Cache::get('paypal:' . $tradeNo);
        } else {
            $orderNo = Order::where('trade_no', $tradeNo)->value('order_no');
        }

        if (!$orderNo) {
            return Response::error('訂單不存在', ErrorCode::ERROR, ['trade_no' => $tradeNo]);
        }

        // 查询订单
        $order = $this->getOrder($orderNo);

        // check resource_type
        if ('PAYMENT.CAPTURE.COMPLETED' !== $callback['event_type']) {
            return Response::error('參數不合法', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 更新订单
        $update = [
            'status' => $this->transformStatus('COMPLETED'),
        ];
        $this->updateOrder($orderNo, $update);

        // 回调集成网址
        $callbackUrl = $order['callback_url'];

        // 返回数据给集成网关更新
        $params = $this->transferOrderInfo($callback, $order);

        // 回调集成网关
        $response = $this->sendCallback($callbackUrl, $orderNo, $params);

        // 通知三方回调结果
        return $this->responsePlatform($response['code'] ?? '01');
    }

    /**
     * 返回整理过的订单资讯给集成网关
     */
    public function transferOrderInfo(array $data, array $order): array
    {
        // {
        //     'id': 'WH-58D329510W468432D-8HN650336L201105X',
        //     'create_time': '2019-02-14T21:50:07.940Z',
        //     'resource_type': 'capture',
        //     'event_type': 'PAYMENT.CAPTURE.COMPLETED',
        //     'summary': 'Payment completed for $ 30.0 USD',
        //     'resource': {
        //             'disbursement_mode': 'INSTANT',
        //         'amount': {
        //                 'currency_code': 'USD',
        //             'value': '30.00'
        //         },
        //         'seller_protection': {
        //                 'status': 'ELIGIBLE',
        //             'dispute_categories': [
        //                     'ITEM_NOT_RECEIVED',
        //                     'UNAUTHORIZED_TRANSACTION'
        //                 ]
        //         },
        //         'supplementary_data': {
        //                 'related_ids': {
        //                     'order_id': '1AB234567A1234567'
        //             }
        //         },
        //         'update_time': '2022-08-23T18:29:50Z',
        //         'create_time': '2022-08-23T18:29:50Z',
        //         'final_capture': true,
        //         'seller_receivable_breakdown': {
        //                 'gross_amount': {
        //                     'currency_code': 'USD',
        //                 'value': '30.00'
        //             },
        //             'paypal_fee': {
        //                     'currency_code': 'USD',
        //                 'value': '1.54'
        //             },
        //             'platform_fees': [
        //                 {
        //                     'amount': {
        //                     'currency_code': 'USD',
        //                         'value': '2.00'
        //                     },
        //                     'payee': {
        //                     'merchant_id': 'ABCDEFGHIJKL1'
        //                     }
        //                 }
        //             ],
        //             'net_amount': {
        //                     'currency_code': 'USD',
        //                 'value': '26.46'
        //             }
        //         },
        //         'invoice_id': '5840243-146',
        //         'links': [
        //             {
        //                 'href': 'https://api.paypal.com/v2/payments/captures/12A34567BC123456S',
        //                 'rel': 'self',
        //                 'method': 'GET'
        //             },
        //             {
        //                 'href': 'https://api.paypal.com/v2/payments/captures/12A34567BC123456S/refund',
        //                 'rel': 'refund',
        //                 'method': 'POST'
        //             },
        //             {
        //                 'href': 'https://api.paypal.com/v2/checkout/orders/1AB234567A1234567',
        //                 'rel': 'up',
        //                 'method': 'GET'
        //             }
        //         ],
        //         'id': '12A34567BC123456S',
        //         'status': 'COMPLETED'
        //     },
        //     'links': [
        //         {
        //             'href': 'https://api.paypal.com/v1/notifications/webhooks-events/WH-58D329510W468432D-8HN650336L201105X',
        //             'rel': 'self',
        //             'method': 'GET',
        //             'encType': 'application/json'
        //         },
        //         {
        //             'href': 'https://api.paypal.com/v1/notifications/webhooks-events/WH-58D329510W468432D-8HN650336L201105X/resend',
        //             'rel': 'resend',
        //             'method': 'POST',
        //             'encType': 'application/json'
        //         }
        //     ],
        //     'event_version': '1.0',
        //     'resource_version': '2.0'
        // }

        // 依据三方逻辑转换字段, $order 是缓存资料, 建议用三方返回的 $data 满足以下条件
        return [
            'amount' => $order['amount'],
            'real_amount' => $order['amount'],
            'order_no' => $order['order_no'],
            'trade_no' => $order['trade_no'],
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $order['payment_channel'],
            'status' => $this->transformStatus('COMPLETED'),
            'remark' => '',
            'created_at' => $this->getTimestamp(), // 集成使用 UTC
            'raw' => json_encode($data, JSON_UNESCAPED_SLASHES), // 三方返回的原始资料
        ];
    }
}
