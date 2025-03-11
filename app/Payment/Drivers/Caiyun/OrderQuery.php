<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Caiyun;

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
        $endpoint_url = $request->input('endpoint_url');

        $response = $this->queryOrderInfo($endpoint_url, $order);

        // 三方返回数据示例
        // {
        //     "amount": "10000",
        //     "mchOrderNo": "caiyun_order17923981",
        //     "productId": "8012",
        //     "payOrderId": "YT202303271653408159412",
        //     "status": "1",
        //     "mchId": "10094",
        //     "paySuccTime": "",
        //     "retCode": "SUCCESS",
        //     "sign": "A308DC50B743F975B5FD5948F6084F74"
        // }

        // 签名校验
        if (false === $this->verifySignature($response, $order['merchant_key'])) {
            return Response::error('TP Error!', ErrorCode::ERROR, $response);
        }

        // 三方返回码：0=成功，其他失败
        if ('SUCCESS' != $response['retCode']) {
            return Response::error('TP Error!', ErrorCode::ERROR, $response);
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
            'payment_channel' => $data['mchId'],
            'status' => $this->transformStatus($data['status']),
            'remark' => '',
            'created_at' => $this->getServerDateTime(), // 集成使用 UTC
            'raw' => $data, // 三方返回的原始资料
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
            'mchId' => $order['merchant_id'],
            'mchOrderNo' => $order['order_no'], // 商戶訂單號
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $order['merchant_key']);

        return $params;
    }
}
