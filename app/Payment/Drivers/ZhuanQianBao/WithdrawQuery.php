<?php

declare(strict_types=1);

namespace App\Payment\Drivers\ZhuanQianBao;

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
        // "data": [
        //     {
        //         "id": "1206",
        //         "mem_bank_code": "",
        //         "mem_bank_name": "",
        //         "mem_name": "",
        //         "mem_acc": "",
        //         "office_name": "",
        //         "office_bank_code": "",
        //         "office_bank_name": "",
        //         "office_acc": "",
        //         "member_id": "0",
        //         "recharge_card_id": "0",
        //         "member_user_name": "",
        //         "type": 3,
        //         "coin": "10",
        //         "coin_rmb": "10",
        //         "rmb_usdt_rage": "0",
        //         "after_coin": "0",
        //         "status": 1,
        //         "remark": "",
        //         "created_at": "2023-10-06T15:42:23+08:00",
        //         "charged_at": null,
        //         "finished_at": null,
        //         "get_order_at": null,
        //         "match_making_id": "6",
        //         "match_making_name": "瓦力遊戲(金)",
        //         "match_making_order_id": "zhuanqianbao_order78735919",
        //         "receipt": [
        //             "",
        //             "",
        //             ""
        //         ],
        //         "screenshot": [
        //             "",
        //             "",
        //             ""
        //         ],
        //         "rebate_coin": "0",
        //         "rebate_rate": "1",
        //         "activity_at": null,
        //         "video_cover_path": "",
        //         "video_path": ""
        //     }
        // ],
        // "page_result": {
        //     "total": 1,
        //     "current": 1,
        //     "pageSize": 10,
        //     "base_offset": 0
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
            'fee' => '0', // $this->revertAmount($data['fee']),
            'order_no' => $data['match_making_order_id'],
            'trade_no' => $data['id'],
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $order['payment_channel'],
            'status' => $this->transformWithdrawStatus($data['status']),
            'remark' => $data['remark'] ?? '',
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
        // 请依据三方查询订单文档调整以下字段
        $params = [
            'user_name' => $order['merchant_id'],
            'match_making_order_id' => $order['order_no'],
            'time_stamp' => $this->getDateTime(),
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $order['merchant_key']);

        return $params;
    }
}
