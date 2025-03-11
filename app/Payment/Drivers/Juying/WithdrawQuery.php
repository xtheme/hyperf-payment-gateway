<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Juying;

use App\Common\Response;
use App\Constants\ErrorCode;
use App\Exception\ApiException;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Stringable\Str;
use Psr\Http\Message\ResponseInterface;

class WithdrawQuery extends Driver
{
    public function request(RequestInterface $request): ResponseInterface
    {
        $input = $request->all();

        // 商户订单号
        $orderNo = $input['order_id'];

        // 查询订单
        $order = $this->getOrder($orderNo, 'withdraw');

        // 三方接口地址
        $endpointUrl = $input['endpoint_url'];

        $response = $this->queryOrderInfo($endpointUrl, $order);

        // 三方返回数据示例, data 為陣列
        // {"code": 0,
        //     "msg": "success",
        //     "data":{
        // }}

        // 网关返回码：0000=成功，其他失败
        if (0 != $response['code']) {
            return Response::error('TP Error!', ErrorCode::ERROR, $response);
        }

        // 統一狀態後返回集成网关
        $data = $response['data'];
        $data = $this->transferOrderInfo($data, $order);

        return Response::success($data);
    }

    /**
     * 返回整理过的订单资讯给集成网关
     */
    public function transferOrderInfo(array $data, array $order): array
    {
        return [
            'amount' => $this->revertAmount($data['score']),
            'fee' => '0', // 無此參數
            'order_no' => $data['orderNo'],
            'trade_no' => $order['trade_no'],
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $order['payment_channel'],
            'status' => $this->transformWithdrawStatus($data['tradeState']),
            'remark' => $data['message'] ?? '',
            'created_at' => $this->getServerDateTime(), // 返回集成使用 UTC
            'raw' => json_encode($data, JSON_UNESCAPED_SLASHES), // 三方返回的原始资料
        ];
    }

    /**
     * 查詢訂單明細
     */
    public function queryOrderInfo($endpointUrl, $order): array
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

        // 取得日期
        $getTime = $this->getTimestamp();

        // params
        $params = [
            'mch_id' => $order['merchant_id'],
            'nonce_str' => $rand,
            'timeStamp' => (string) $getTime,
            'tradeNo' => $order['trade_no'],
        ];

        $params[$this->signField] = $this->getSignature($params, $order['merchant_key']);

        return $params;
    }
}
