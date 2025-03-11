<?php

declare(strict_types=1);

namespace App\Payment\Drivers\BaiYun;

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
        $endpointUrl = $request->input('endpoint_url');

        // Custom header params
        $this->appendHeaders($order['header_params']);

        $response = $this->queryOrderInfo($endpointUrl, $order);

        // 网关返回码：0000=成功，其他失败
        if (1 != $response['fxstatus']) {
            return Response::error('TP Error!', ErrorCode::ERROR, $response);
        }

        // 統一狀態後返回集成网关
        $body = json_decode($response['fxbody'], true);
        $data = array_merge($response, $body[0]);
        $data = $this->transferOrderInfo($data, $order);

        return Response::success($data);
    }

    /**
     * 返回整理过的订单资讯给集成网关
     */
    public function transferOrderInfo(array $data, array $order): array
    {
        // 请依据三方逻辑转换字段, $order 是缓存资料, 建议用三方返回的 $data 满足以下条件
        return [
            'amount' => $this->revertAmount($data['fxfee']),
            'fee' => 0,
            'order_no' => str_replace('baiyun', 'baiyun_', $data['fxddh']),
            'trade_no' => $data['fxddh'],
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $order['payment_channel'],
            'status' => $this->transformWithdrawStatus($data['fxstatus']),
            'remark' => $data['fxmsg'] ?? '',
            'created_at' => $this->getServerDateTime(), // 返回集成使用 UTC
            'raw' => json_encode($data, JSON_UNESCAPED_SLASHES), // 三方返回的原始资料
        ];
    }

    /**
     * 查詢額度
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
        $order_no = str_replace('_', '', $order['order_no']);
        $body = [
            [
                'fxddh' => $order_no,
            ],
        ];
        $params = [
            'fxid' => $order['merchant_id'],
            'fxaction' => 'repayquery',
            'fxbody' => json_encode($body),
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature([
            $params, [
                'fxid',
                'fxaction',
                'fxbody',
            ],
        ], $order['merchant_key']);

        return $params;
    }
}
