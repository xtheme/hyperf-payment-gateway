<?php

declare(strict_types=1);

namespace App\Payment\Drivers\ZhuanQianBao;

use App\Common\Response;
use App\Constants\ErrorCode;
use App\Exception\ApiException;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class OrderQuery extends Driver
{
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

        // 三方返回数据示例
        // "data": [
        //     {
        //         "id": "17546",
        //         "trans_name": "",
        //         "trans_acc": "",
        //         "merchant_id": "7",
        //         "merchant_name": "瓦力遊戲(商戶版)",
        //         "merchant_order_id": "Test_1011-2",
        //         "member_id": "115986124203098115",
        //         "user_name": "16589561369",
        //         "collect_card_id": "47",
        //         "collect_type": 1,
        //         "collect_acc": "167885422",
        //         "collect_bank": "中國光大銀行",
        //         "collect_bank_code": "CEB",
        //         "collect_name": "小孖仔糕",
        //         "coin": "10",
        //         "rebate_coin": "0",
        //         "rebate_rate": "6.35",
        //         "status": 2,
        //         "process": 1,
        //         "created_at": "2023-10-11T14:03:11+08:00",
        //         "ordered_at": "2023-10-11T14:03:11.303+08:00",
        //         "collected_at": null,
        //         "finished_at": null,
        //         "remark": "",
        //         "activity_at": "2023-10-11T15:03:11.304+08:00"
        //     }
        // ],
        // "page_result": {
        //     "total": 1,
        //     "current": 1,
        //     "pageSize": 10,
        //     "base_offset": 0
        // }

        // 验证签名
        // if (false === $this->verifySignature($response, $order['merchant_key'])) {
        //     return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        // }

        // 三方返回码：0=成功，其他失败
        if (array_key_exists('error', $response)) {
            return Response::error('TP Error!', ErrorCode::ERROR, $response);
        }

        // 統一狀態後返回集成网关
        $data = $this->transferOrderInfo(reset($response['data']), $order);

        return Response::success($data);
    }

    /**
     * 返回整理过的订单资讯给集成网关
     */
    public function transferOrderInfo(array $data, array $order): array
    {
        return [
            'amount' => $this->revertAmount($data['coin']),
            'real_amount' => $this->revertAmount($data['coin']),
            'order_no' => $data['merchant_order_id'],
            'trade_no' => $data['id'],
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $order['payment_channel'],
            'status' => $this->transformStatus($data['status']),
            'remark' => '',
            'created_at' => $this->getServerDateTime(), // 集成使用 UTC
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
        // 请依据三方查询订单文档调整以下字段
        $params = [
            'user_name' => $order['merchant_id'],
            'merchant_order_id' => $order['order_no'],
            'time_stamp' => $this->getDateTime(),
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $order['merchant_key']);

        return $params;
    }
}
