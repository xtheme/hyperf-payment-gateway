<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Qg;

use App\Common\Response;
use App\Constants\ErrorCode;
use App\Exception\ApiException;
use Hyperf\HttpServer\Contract\RequestInterface;
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

        // Custom header params
        $this->appendHeaders($order['header_params']);

        $response = $this->queryOrderInfo($endpointUrl, $order);

        // 三方返回数据示例, data 為陣列
        // {
        //     "rows": 1,
        //     "start": 0,
        //     "data": [
        //     {
        //     "order_sid": 202302242300001,
        //     "order_id": "a10016",
        //     "payer": "玩家",
        //     "type": 2,
        //     "amount": "100.00",
        //     "init_time": "2023-02-24 23:04:55",
        //     "status": 0,
        //     "is_finish": false,
        //     "finish_time": null,
        //     "message": "test order",
        //     "callback": "https://test.callback.url"
        //     },
        //
        //     ],
        //     "code": 200
        // }

        // 网关返回码：0000=成功，其他失败
        if (200 != $response['code']) {
            return Response::error('TP Error!', ErrorCode::ERROR, $response);
        }

        // 查無訂單數據
        if (0 == $response['rows']) {
            return Response::error('TP 查無訂單號 ' . $orderNo . '!', ErrorCode::ERROR, $response);
        }

        // 校驗簽名
        // if (false === $this->verifySignature($response['data'][0], $order['merchant_key'])) {
        //     return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        // }

        // 統一狀態後返回集成网关
        $data = $response['data'][0];
        $data = $this->transferOrderInfo($data, $order);

        return Response::success($data);
    }

    /**
     * 返回整理过的订单资讯给集成网关
     */
    public function transferOrderInfo(array $data, array $order): array
    {
        return [
            'amount' => $this->revertAmount($data['amount']),
            'fee' => $this->revertAmount($data['fee']) ?? '0', // 待確認
            'order_no' => $data['order_id'],
            'trade_no' => $data['order_sid'],
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $order['payment_channel'],
            'status' => $this->transformWithdrawStatus($data['status']),
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
        // 不用签名
        return [
            'order_id' => $order['order_no'],
            //  'limit' => 1,
        ];
    }
}
