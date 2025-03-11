<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Jbp;

use App\Common\Response;
use App\Constants\ErrorCode;
use App\Exception\ApiException;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class OrderNotify extends OrderQuery
{
    /**
     * 三方回調通知, 更新訂單
     */
    public function request(RequestInterface $request): ResponseInterface
    {
        $callback = $request->all();

        // 三方回調 $callback
        // {
        //     "type": "addTransfer",
        //     "pay_time": "20230411171855",
        //     "bank_id": "40",
        //     "amount": "10000.00",
        //     "company_order_num": "jbp_order08375294",
        //     "mownecum_order_num": "STT2023041116060007252767",
        //     "transaction_charge": "40",
        //     "fee": 0,
        //     "deposit_mode": "2",
        //     "operating_time": "20230411171855",
        //     "key": "97213a0050b14e439c5bce367ea99880"
        // }

        // 回調請求中的商戶訂單號
        $orderNo = $callback['company_order_num'] ?? '';

        $this->logger->info(sprintf('%s 回調參數', $orderNo), $callback);

        // 查询订单
        $order = $this->getOrder($orderNo);

        // 檢查簽名規則
        $check_params = [
            'pay_time' => $callback['pay_time'],
            'bank_id' => $callback['bank_id'],
            'amount' => sprintf('%.2f', $callback['amount']), // 必須小數兩位
            'company_order_num' => $callback['company_order_num'],
            'mownecum_order_num' => $callback['mownecum_order_num'],
            'fee' => sprintf('%.2f', $callback['fee']),                // 必須小數兩位
            'transaction_charge' => sprintf('%.2f', $callback['transaction_charge']), // 必須小數兩位
            'deposit_mode' => $callback['deposit_mode'],
        ];
        $check_sign = $this->getSignature($check_params, $order['merchant_key']);

        // 验证签名
        if ($callback['key'] !== $check_sign) {
            return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 二次校驗
        $order_check = $this->doubleCheck($callback, $orderNo, $order);

        if (!$order_check) {
            return Response::error('二次校验查询订单失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 只有查詢訂單有 status
        $callback['status'] = $order_check['status'];

        // 更新订单
        $update = [
            'status' => $this->transformStatus($callback['status']),
            'real_amount' => $callback['amount'],
            'trade_no' => $callback['mownecum_order_num'],
        ];
        $this->updateOrder($orderNo, $update);

        // 回调集成网址
        $callbackUrl = $order['callback_url'];

        // 返回数据给集成网关更新
        $params = $this->transferOrderInfo($callback, $order);

        // 回调集成网关
        $response = $this->sendCallback($callbackUrl, $orderNo, $params);

        // 通知三方回调结果
        return $this->responsePlatform($response['code'] ?? '01', $orderNo, $callback['mownecum_order_num']);
    }

    /**
     * 若创建订单时带有三方查询网址 query_url 参数则可发起二次校验
     */
    private function doubleCheck(array $callback, string $orderNo, array $order): array
    {
        if (empty($order['query_url'])) {
            return [];
        }

        // 验签成功, 调用第三方查询接口判断该订单在第三方系统里是否支付成功
        $response = $this->queryOrderInfo($order['query_url'], $order);

        // 三方回調 $callback
        // {
        //     "type": "addTransfer",
        //     "pay_time": "20230411171855",
        //     "bank_id": "40",
        //     "amount": "10000.00",
        //     "company_order_num": "jbp_order08375294",
        //     "mownecum_order_num": "STT2023041116060007252767",
        //     "transaction_charge": "40",
        //     "fee": 0,
        //     "deposit_mode": "2",
        //     "operating_time": "20230411171855",
        //     "key": "97213a0050b14e439c5bce367ea99880"
        // }

        // 三方查單 $response
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

        if (!isset($callback['type']) || 'addTransfer' != $callback['type']) {
            throw new ApiException('回調 type 異常 ' . $orderNo);
        }

        // 檢查訂單號
        if (!isset($response['company_order_num']) || $callback['company_order_num'] != $response['company_order_num']) {
            throw new ApiException('查單失敗 ' . $orderNo);
        }

        // 沒有訂單狀態可比對

        // 檢查簽名規則
        $check_params = [
            'mownecum_order_num' => $response['mownecum_order_num'],
            'company_order_num' => $response['company_order_num'],
            'status' => $response['status'],
            'amount' => sprintf('%.2f', $response['amount']),                   // 必須小數兩位
            'exact_transaction_charge' => sprintf('%.2f', $response['exact_transaction_charge']), // 必須小數兩位
            'transaction_type' => $response['transaction_type'],
        ];
        $check_sign = $this->getSignature($check_params, $order['merchant_key']);

        // 二次校驗簽名
        if ($response['key'] != $check_sign) {
            throw new ApiException('二次验证签名失败 ' . $orderNo);
        }

        return $response;
    }
}
