<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Fservice;

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
        //     "fxid": "2023100",
        //     "fxstatus": "0",
        //     "fxddh": "fservice_order64652762",
        //     "fxorder": "",
        //     "fxdesc": "stt",
        //     "fxfee": "100.0000",
        //     "fxattch": "order64652762",
        //     "fxtime": "0",
        //     "fxsign": "21e24e69b53f7dcf24ca413ad93d59f2"
        // }

        // 三方返回码：1=正常支付, 0=支付异常
        if ('1' !== $response['fxstatus']) {
            return Response::error('TP Error!', ErrorCode::ERROR, $response);
        }

        // 签名校验
        if ($response['fxsign'] != $this->getNotifySignature($response, $order['merchant_key'])) {
            throw new ApiException('验证签名失败 ' . $orderNo);
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
            'amount' => $this->revertAmount($data['fxfee']),
            'real_amount' => $this->revertAmount($data['fxfee']),
            'order_no' => $data['fxddh'],
            'trade_no' => $data['fxorder'],
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $order['payment_channel'],
            'status' => $this->transformStatus($data['fxstatus']),
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
        $params = [
            'fxid' => $order['merchant_id'], // 商户号
            'fxddh' => $order['order_no'],    // 商户订单号
            'fxaction' => 'orderquery',
        ];

        // 加上签名
        $params[$this->signField] = $this->genQuerySignature($params, $order['merchant_key']);

        return $params;
    }
}
