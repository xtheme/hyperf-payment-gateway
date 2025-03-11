<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Huiying;

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
        //     "appId": "e5d721c6d7f54bbea4efb310e186a08d",
        //     "mchOrderNo": "huiying_order61675320",
        //     "channelAttach": "",
        //     "productId": "8028",
        //     "currency": "cny",
        //     "status": "-2",
        //     "mchId": "20000028",
        //     "channelUser": "huiyingzfbqr",
        //     "paySuccTime": "",
        //     "retCode": "SUCCESS",
        //     "sign": "20656A5224CCA0794176E699E5F6C4D5",
        //     "channelOrderNo": "",
        //     "amount": "100",
        //     "payOrderId": "P01202304101332520512"
        // }

        // 三方返回码
        if ('SUCCESS' != $response['retCode']) {
            return Response::error('TP Error!', ErrorCode::ERROR, $response);
        }

        // 签名校验
        if (false === $this->verifySignature($response, $order['merchant_key'])) {
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
        // 请依据三方逻辑转换字段, $order 是缓存资料, 建议用三方返回的 $data 满足以下条件
        return [
            'amount' => $this->revertAmount($data['amount']),
            'real_amount' => $this->revertAmount($data['amount']),
            'order_no' => $data['mchOrderNo'],
            'trade_no' => $data['payOrderId'],
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
        // Custom body params
        $body_params = json_decode($order['body_params'], true);

        // 请依据三方查询订单文档调整以下字段
        $params = [
            'mchId' => $order['merchant_id'],       // 商户ID
            'appId' => $body_params['appId'] ?? '', // 应用ID
            'mchOrderNo' => $order['order_no'],          // 商户订单号, 擇一
            // 'payOrderId'    => $order['trade_no'], // 支付订单号, 擇一
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $order['merchant_key']);

        return $params;
    }
}
