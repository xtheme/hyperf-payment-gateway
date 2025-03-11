<?php

declare(strict_types=1);

namespace App\Payment\Drivers\DoudouPay;

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
        //     "retcode": 0,
        //     "retdesc": "success",
        //     "id": "20231108151658004837",
        //     "orderId": "Test_1108-1",
        //     "amount": 100,
        //     "paymode": "HELP_BUY",
        //     "completeTime": null,
        //     "status": "WAITING",
        //     "sign": null
        // }

        // 网关返回码：0=成功，其他失败
        if (0 != $response['retcode']) {
            return Response::error('TP Error!', ErrorCode::ERROR, $response);
        }

        // 校驗簽名
        // if (false === $this->verifySignature($response['data'][0], $order['merchant_key'])) {
        //     return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        // }

        // 統一狀態後返回集成网关
        $data = $response;
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
            'fee' => 0,
            // $this->revertAmount($data['fee']),
            'order_no' => $data['orderId'],
            'trade_no' => $data['id'],
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $order['payment_channel'],
            'status' => $this->transformWithdrawStatus($data['status']),
            'remark' => $data['memo'] ?? '',
            'created_at' => $this->getServerDateTime(),
            // 返回集成使用 UTC
            'raw' => json_encode($data, JSON_UNESCAPED_SLASHES),
            // 三方返回的原始资料
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
        // 依据三方创建代付接口的参数调整以下字段
        $params = [
            'mchCode' => $order['merchant_id'],
            'orderId' => $order['order_no'],
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $order['merchant_key']);

        return $params;
    }
}
