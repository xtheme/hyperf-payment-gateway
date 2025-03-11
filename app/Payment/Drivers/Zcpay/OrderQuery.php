<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Zcpay;

use App\Common\Response;
use App\Constants\ErrorCode;
use App\Exception\ApiException;
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
        if (200 !== $response['code']) {
            return Response::error('TP Error!', ErrorCode::ERROR, $response);
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
        //     “code”: 200,
        //     “message”: "success",
        //     "data": {
        //     "plat_no": “9ab9d758363a4499ae4412248802b3e4”
        //     "status": 1
        //     “time”: “1625370056",
        //     }

        // 判斷回傳值 amount 是否存在
        $amount = $order['amount'];

        if (!empty($data['data']['money'])) {
            $amount = $this->revertAmount($data['data']['money']);
        }

        return [
            'amount' => $amount,
            'real_amount' => $amount ?? '',
            'order_no' => $order['order_no'],
            'trade_no' => $data['data']['plat_no'] ?? $order['trade_no'],
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $order['payment_channel'],
            'status' => $this->transformStatus($data['data']['status']),
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
        // 依据三方查询订单文档调整以下字段

        // 產生亂碼
        $rand = str_replace('-', '', Str::uuid()->toString());

        $params = [
            'appid' => $order['merchant_id'],
            'order_no' => $order['order_no'],
            'timestamp' => $this->getTimestamp(),
            'nonce_str' => $rand,
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $order['merchant_key']);

        return $params;
    }
}
