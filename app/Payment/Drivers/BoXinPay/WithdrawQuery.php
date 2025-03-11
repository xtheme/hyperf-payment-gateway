<?php

declare(strict_types=1);

namespace App\Payment\Drivers\BoXinPay;

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

        $response = $this->queryOrderInfo($endpointUrl, $order);

        // {"ErrorCode":"0","Message":"Success","Data":{"Date":"2024-06-13 16:20:49","WithdrawalTime":"","TradingNumber":"240613693173","MerchantNumber":"boxin_withdraw_37061329","BankName":"中國信託","BankBranch":"","BranchCode":"","BankAccount":"000000000000","AccountName":"測試","MinusPoints":"1035","Fee":"35","ActualPoints":"1000","Status":"未撥款","StatusCode":"0","Remark":""}}
        if ('0' !== $response['ErrorCode']) {
            return Response::error('TP Error!', ErrorCode::ERROR, $response);
        }

        // 統一狀態後返回集成网关
        $data = $this->transferOrderInfo($response['Data'], $order);

        return Response::success($data);
    }

    /**
     * 返回整理过的订单资讯给集成网关
     */
    public function transferOrderInfo(array $data, array $order): array
    {
        return [
            'amount' => $this->revertAmount($data['ActualPoints']), // 三方返回的金額須轉換為 "分" 返回集成网关
            'fee' => $this->revertAmount($data['Fee'] ?? 0), // 三方返回的金額須轉換為 "分" 返回集成网关
            'order_no' => $order['order_no'],
            'trade_no' => $data['TradingNumber'], // 交易單編號
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $order['payment_channel'],
            'status' => $this->transformWithdrawStatus($data['StatusCode']),
            'remark' => $data['Remark'] ?? '',
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
            'HashKey' => $order['merchant_id'],
            'HashIV' => $order['merchant_key'],
            'MerchantNumber' => $order['order_no'],
        ];
    }
}
