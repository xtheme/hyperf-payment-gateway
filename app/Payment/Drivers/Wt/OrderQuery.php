<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Wt;

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
        $endpointUrl = $input['endpoint_url'];

        // Custom header params
        $this->appendHeaders($order['header_params']);

        $response = $this->queryOrderInfo($endpointUrl, $order);

        // 三方返回数据示例, data 為陣列
        // {
        //     "error_code": "0000",
        //     "data": [
        //         {
        //             "amount": 10000,
        //             "real_amount": 0,
        //             "fee": 0,
        //             "payment_id": "WUTONGPM00315302",
        //             "payment_cl_id": "wt_order98636755",
        //             "platform_id": "PF0252",
        //             "platform_channel_id": "PFC00000778",
        //             "status": 0,
        //             "memo": "",
        //             "create_time": 1679473246,
        //             "update_time": 1679473247,
        //             "sign": "1a71484abf72bacf2b4f7771f6914af3"
        //         }
        //     ],
        //     "total": 1
        // }

        // 网关返回码：0000=成功，其他失败
        if ('0000' != $response['error_code']) {
            return Response::error('TP Error!', ErrorCode::ERROR, $response);
        }

        // 网关返回码：0000=成功，其他失败
        if (0 == $response['total']) {
            return Response::error('TP 查無訂單號 ' . $orderNo . '!', ErrorCode::ERROR, $response);
        }

        // 校驗簽名
        if (false === $this->verifySignature($response['data'][0], $order['merchant_key'])) {
            return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

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
            'real_amount' => $this->revertAmount($data['real_amount']),
            'order_no' => $data['payment_cl_id'],
            'trade_no' => $data['payment_id'],
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $order['payment_channel'],
            'status' => $this->transformStatus($data['status']),
            'remark' => '',
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
        // WT 不用签名
        return [
            'payment_cl_id' => $order['order_no'],
            'limit' => 1,
        ];
    }
}
