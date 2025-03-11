<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Stpay;

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
        // {
        //     "memberid": "230264547",
        //     "orderid": "stpay_order89652970",
        //     "amount": "100.0000",
        //     "time_end": "",
        //     "transaction_id": "202303291637011005459419",
        //     "returncode": "01",
        //     "trade_state": "NOTPAY",
        //     "sign": "44DD01A6160C243FCD6BCE7C17E5B65B"
        // }

        // 三方返回码：00=成功，其他失败
        if (!isset($response['returncode']) || '00' != $response['returncode']) {
            return Response::error('TP Error!', ErrorCode::ERROR, $response);
        }

        // 签名校验
        if ($response['sign'] != $this->getSignature($response, $order['merchant_key'])) {
            return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
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
        return [
            'amount' => $this->revertAmount($data['amount']),
            'real_amount' => $this->revertAmount($data['amount']),
            'order_no' => $data['orderid'],
            'trade_no' => $data['transaction_id'],
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $order['payment_channel'],
            'status' => $this->transformStatus($data['returncode']),
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

        // 更新订单
        $update = [
            'status' => $this->transformStatus($response['trade_state']),
            'real_amount' => $this->revertAmount($response['amount']),
            'trade_no' => $response['transaction_id'],
        ];
        $this->updateOrder($order['order_no'], $update);

        return $response;
    }

    /**
     * 转换三方查询订单字段
     */
    protected function prepareQueryOrder($order): array
    {
        $params = [
            'pay_memberid' => $order['merchant_id'],
            'pay_orderid' => $order['order_no'],
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $order['merchant_key']);

        return $params;
    }
}
