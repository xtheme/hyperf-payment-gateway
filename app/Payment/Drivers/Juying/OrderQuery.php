<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Juying;

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
        if (0 !== $response['code']) {
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
        // {"code": 0,
        // "msg": "success",
        // "data":{
        //     "mch_id":"F010AE73-BD28-451E-B36F-CE86DA807843",
        //     "nonce_str":"59f75cde8bec443895301fcc0bdb7706",
        //     "timeStamp":"1618215619",
        //     "tradeNo":"6142eda8-837e-4280-ad1f-50dae090d6e6",
        //     "sign":"f5a7ea8bfbdf444df1abc67891a8ec98"
        //      }
        // }

        return [
            'amount' => $order['amount'],
            'real_amount' => $this->revertAmount($data['score']),
            'order_no' => $order['order_no'],
            'trade_no' => $data['tradeNo'] ?? $order['trade_no'],
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $order['payment_channel'],
            'status' => $this->transformStatus($data['tradeState']),
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
        // 產生亂碼
        $rand = str_replace('-', '', Str::uuid()->toString());

        // 依据三方查询订单文档调整以下字段
        $params = [
            'mch_id' => $order['merchant_id'],
            'nonce_str' => $rand,
            'timeStamp' => (string) $this->getTimestamp(),
            'tradeNo' => $order['trade_no'],
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $order['merchant_key']);

        return $params;
    }
}
