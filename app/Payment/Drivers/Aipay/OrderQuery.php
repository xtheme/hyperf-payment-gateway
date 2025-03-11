<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Aipay;

use App\Common\Response;
use App\Constants\ErrorCode;
use App\Exception\ApiException;
use Carbon\Carbon;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Stringable\Str;
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

        $response = $this->queryOrderInfo($endpointUrl, $order);

        // 三方返回数据校验
        if ('200' !== $response['code']) {
            return Response::error('TP Error!', ErrorCode::ERROR, $response);
        }

        // 取出 data
        $response = $response['data'];

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
        //     "code": "200",
        //     "msg": "请求成功",
        //     "data": {
        //         "mchKey": "10008",
        //         "mchOrderNo": "order0000000000013",
        //         "serialOrderNo": "PAY0WECHAT111565MBJlhjVt",
        //         "product": "wechat",
        //         "amount": 20000,
        //         "realAmount": null,
        //         "payStatus": "PROCESSING",
        //         "url": {
        //             "payUrl": "http://pay.xxx.xyz/api/wechat/PAY0WECHAT111565MBJlhjVt.html",
        //             "expire": 1636978641883,
        //             "expireTime": "2021/11/15 20:17:21"
        //         },
        //         "createTime": "2021/11/15 20:12:21",
        //         "payTime": null
        //     }
        // }

        return [
            'amount' => $this->revertAmount($data['amount']),
            'real_amount' => $this->revertAmount($data['realAmount'] ?? '0'),
            'order_no' => $order['order_no'],
            'trade_no' => $data['serialOrderNo'] ?? $order['trade_no'],
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $order['payment_channel'],
            'status' => $this->transformStatus($data['payStatus']),
            'remark' => '',
            'created_at' => $this->getTimestamp(), // 集成使用 UTC
            'raw' => json_encode($data, JSON_UNESCAPED_SLASHES), // 三方返回的原始资料
        ];
    }

    /**
     * 查詢訂單明細
     */
    protected function queryOrderInfo($endpointUrl, $order): array
    {
        $params = $this->prepareQueryOrder($order);

        try {
            return $this->sendRequest($endpointUrl, $params, $this->config['query_order']);
        } catch (\Exception $e) {
            throw new ApiException($e->getMessage());
        }
    }

    /**
     * 转换三方查询订单字段
     */
    protected function prepareQueryOrder($order): array
    {
        // 產生隨機碼
        $rand = str_replace('-', '', Str::uuid()->toString());

        // 獲取時間戳，精準到毫秒
        $timestampMs = Carbon::now()->getTimestampMs();

        // 依据三方查询订单文档调整以下字段
        $params = [
            'mchKey' => $order['merchant_id'],
            'timestamp' => $timestampMs,
            'mchOrderNo' => $order['order_no'],
            'nonce' => $rand,
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $order['merchant_key']);

        return $params;
    }
}
