<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Laifu;

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

        // Custom header params
        $this->appendHeaders($order['header_params']);

        $response = $this->queryOrderInfo($endpointUrl, $order);

        // 三方返回码：0=成功，其他失败
        if (0 != $response['code']) {
            return Response::error('TP Error!', ErrorCode::ERROR, $response);
        }

        // 查詢訂單時沒有簽名需要校驗

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
        // 依据三方逻辑转换字段, $order 是缓存资料, 建议用三方返回的 $data 满足以下条件

        // {
        //     "code": 0,
        //     "msg": "请求成功",
        //     "data": {
        //         "trade_no": "67616818942437766413628",
        //         "out_trade_no": "laifu_order39217484",
        //         "amount": "100.000",
        //         "status": "SUCCESS"
        //     }
        // }

        return [
            'amount' => $this->revertAmount($data['amount']),
            'real_amount' => $this->revertAmount($data['amount']),
            'order_no' => $data['out_trade_no'],
            'trade_no' => $data['trade_no'],
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
            $response = $this->sendRequest($endpointUrl, $params, $this->config['query_order']);
        } catch (\Exception $e) {
            throw new ApiException($e->getMessage());
        }

        // {
        //     "code": 0,
        //     "msg": "请求成功",
        //     "data": {
        //         "trade_no": "67616818909456033477271",
        //         "out_trade_no": "laifu_order23834829",
        //         "amount": "100.000",
        //         "status": "WAIT"
        //     }
        // }

        // 更新订单
        $update = [
            'status' => $this->transformStatus($response['data']['status']),
            'real_amount' => $this->revertAmount($response['data']['amount']),
            'trade_no' => $response['data']['trade_no'],
        ];
        $this->updateOrder($order['order_no'], $update);

        return $response;
    }

    /**
     * 转换三方查询订单字段
     */
    protected function prepareQueryOrder($order): array
    {
        // 请依据三方查询订单文档调整以下字段
        $params = [
            'mchid' => $order['merchant_id'],
            'out_trade_no' => $order['order_no'],
            'channel' => $order['payment_channel'],
            // 'mchOrderNo'   => $order['order_no'],
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $order['merchant_key']);

        return $params;
    }
}
