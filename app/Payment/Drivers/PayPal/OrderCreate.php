<?php

declare(strict_types=1);

namespace App\Payment\Drivers\PayPal;

use App\Common\Response;
use App\Constants\ErrorCode;
use App\Payment\OrderDrivers\CacheOrder;
use FriendsOfHyperf\Cache\Facade\Cache;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Stringable\Str;
use Psr\Http\Message\ResponseInterface;

use function Hyperf\Config\config;

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

        // 取得 Access Token
        $accessToken = base64_encode($input['merchant_id'] . ':' . $input['merchant_key']);

        // 創建 PayPal-Request-Id 並保存在訂單資料中
        $input['header_params'] = [
            'PayPal-Request-Id' => Str::orderedUuid()->toString(),
            'Authorization' => 'Basic ' . $accessToken,
        ];
        $this->withHeaders($input['header_params']);

        try {
            $response = $this->sendRequest($endpointUrl, $params, $this->config['create_order']);
        } catch (\Exception $e) {
            return Response::error($e->getMessage());
        }

        // 创建订单
        $this->createOrder($orderNo, $input);

        // PayPal 三方訂單號
        $tradeNo = $response['id'];

        if (CacheOrder::class === config('payment.order_driver')) {
            // PayPal 使用三方訂單號回調而非 PG 訂單號, 在 CacheOrder 必須建立緩存關聯來轉換回 PG 訂單號
            Cache::put('paypal:' . $tradeNo, $orderNo, 86400 * 90);
        }

        // 依据三方创建支付接口返回的内容调整以下返回给集成网关的字段
        $data = [
            'order_no' => $orderNo, // 订单号
            'link' => $this->getApproveLink($response['links']), // 支付网址
            'trade_no' => $tradeNo ?? '', // 三方交易号
            // 收銀台字段
            'payee_name' => '', // 收款人姓名
            'payee_bank_name' => '', // 收款人开户行
            'payee_bank_branch_name' => '', // 收款行分/支行
            'payee_bank_account' => '', // 收款人账号
            'payee_nonce' => '', // 附言
            'cashier_link' => '', // 收銀台網址
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
        return [
            'intent' => $data['payment_channel'], // CAPTURE=即时支付, AUTHORIZE=授权支付
            'purchase_units' => [
                [
                    'reference_id' => $orderNo,
                    'description' => '用戶充值',
                    'custom_id' => $data['user_id'] ?? '',
                    'amount' => [
                        'currency_code' => $data['currency'] ?? 'TWD',
                        'value' => $this->convertAmount($data['amount']),
                    ],
                ],
            ],
            'payer' => [
                'name' => [
                    'given_name' => $data['user_name'] ?? '',
                ],
            ],
        ];
    }

    /**
     * 返回付款網址
     */
    protected function getApproveLink(array $links): string
    {
        foreach ($links as $link) {
            if ('approve' === $link['rel']) {
                return $link['href'];
            }
        }

        return '';
    }
}
