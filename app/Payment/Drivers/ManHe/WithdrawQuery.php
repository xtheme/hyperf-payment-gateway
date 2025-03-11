<?php

declare(strict_types=1);

namespace App\Payment\Drivers\ManHe;

use App\Common\Response;
use App\Constants\ErrorCode;
use App\Exception\ApiException;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class WithdrawQuery extends Driver
{
    /**
     * 查詢訂單
     */
    public function request(RequestInterface $request): ResponseInterface
    {
        $input = $request->all();

        // 商户订单号
        $orderNo = $input['order_id'];

        // 查询订单
        $order = $this->getOrder($orderNo, 'withdraw');

        // 三方接口地址
        $endpointUrl = $request->input('endpoint_url');

        // Custom header params
        $this->appendHeaders($order['header_params']);

        $response = $this->queryOrderInfo($endpointUrl, $order);

        //  {"error_code":"0000","data":[{"payout_id":"STARDREAMPOT00065724","payout_cl_id":"manhe_withdraw_23305857","platform_id":"PF0111","platform_channel_id":"PFC00000262","amount":100000,"fee":0,"status":1,"memo":"","create_time":1714963194,"update_time":0,"sign":"f9de666f210159f8f24a656e68bf55b3"}],"total":1}
        if ('0000' !== $response['error_code']) {
            return Response::error('TP Error!', ErrorCode::ERROR, $response);
        }

        // 查無訂單數據
        if (0 == $response['total']) {
            return Response::error('TP 查無訂單號 ' . $orderNo . '!', ErrorCode::ERROR, $response);
        }

        // 校驗簽名
        if (false === $this->verifySignature($response['data'][0], $order['merchant_key'])) {
            return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

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
            'fee' => $this->revertAmount($data['fee']),
            'order_no' => $data['payout_cl_id'],
            'trade_no' => $data['payout_id'],
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $order['payment_channel'],
            'status' => $this->transformWithdrawStatus($data['status']),
            'remark' => $data['memo'] ?? '',
            'created_at' => $this->getTimestamp(), // 返回集成使用 UTC
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
        return [
            'payout_cl_id' => $order['order_no'],
        ];
    }
}
