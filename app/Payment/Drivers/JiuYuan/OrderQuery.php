<?php

declare(strict_types=1);

namespace App\Payment\Drivers\JiuYuan;

use App\Common\Response;
use App\Constants\ErrorCode;
use App\Exception\ApiException;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class OrderQuery extends Driver
{
    protected string $signField = 'sign';

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

        if ('SUCCESS' != $response['result']) {
            return Response::error('TP Error!', ErrorCode::ERROR, $response);
        }

        // 三方返回数据示例
        //        {
        //            "fxid": "104",
        //            "status": "0",
        //            "result": "SUCCESS",
        //            "amount": "50.00",
        //            "error": "",
        //            "fxorder": "jiuyuan_order23150352",
        //            "sign": "c32a71534958422791bb9dfc56f9fe4d"
        //        }

        // 檢查簽名規則
        $check_params = [
            'status' => $response['status'],
            'fxid' => $order['merchant_id'],
            'fxorder' => $response['fxorder'],
            'amount' => sprintf('%.2f', $response['amount']),                   // 必須小數兩位
        ];
        $check_sign = $this->getSignature($check_params, $order['merchant_key']);

        // 签名校验
        if ($response['sign'] != $check_sign) {
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
        // 三方返回数据示例
        // {
        //     "error_msg": "",
        //     "status": 3,
        //     "mownecum_order_num": "2019072523580001310482",
        //     "company_order_num": "GD1UTN2VBKS4bkEuzv",
        //     "amount": 0.00,
        //     "exact_transaction_charge": 5.00, // 实际服务费
        //     "transaction_type": 2, // 交易类型：1=充值订单, 2=提现订单
        //     "key": "aa424cc92512fb23dca21aef51827e10"
        // }

        // 请依据三方逻辑转换字段, $order 是缓存资料, 建议用三方返回的 $data 满足以下条件
        return [
            'amount' => $this->revertAmount($data['amount']),
            'real_amount' => $this->revertAmount($data['amount']),
            'order_no' => $data['fxddh'],
            'trade_no' => $data['fxorder'] ?? '',
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
        // 請勿調整字段順序造成簽名錯誤
        $params = [
            'fxid' => $order['merchant_id'], // 商户号
            'fxorder' => $order['order_no'],
            'fxtype' => 1, // 指定充值訂單
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $order['merchant_key']);

        return $params;
    }

    protected function getSignature(array $data, string $signatureKey): string
    {
        if (isset($data[$this->signField])) {
            unset($data[$this->signField]);
        }

        // 1. 依據組合傳參拼接字串
        $tempStr = urldecode(http_build_query($data));

        // 2. $tempStr 拼接 key
        $tempStr = $tempStr . '&' . $signatureKey;

        // 3. md5
        return md5($tempStr);
    }
}
