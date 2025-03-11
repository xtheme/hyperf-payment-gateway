<?php

declare(strict_types=1);

namespace App\Payment\Drivers\BeBePay;

use App\Common\Response;
use App\Constants\ErrorCode;
use App\Exception\ApiException;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class WithdrawNotify extends WithdrawQuery
{
    /**
     * 三方回調通知, 更新訂單
     */
    public function request(RequestInterface $request): ResponseInterface
    {
        // {"type":"1","amount":"1000.0","real_amount":"1000.0","appKey":"rtay9g6nwbg6XngzPSEJYW5qYxqiyUHG","payType":"4","orderID":"bebepay_withdraw_70630423","status":"4","sign":"0dda4e409f4d9ff76b624018bb19ab16"}
        $callback = $request->all();

        $orderNo = $callback['orderID'] ?? '';

        $this->logger->info(sprintf('%s 回調參數', $orderNo), $callback);

        if ('1' != $callback['type']) {
            return Response::error('回調type錯誤', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 查询订单
        $order = $this->getOrder($orderNo, 'withdraw');

        // 验证签名
        $signParams = [
            'type' => $callback['type'],
            'amount' => $callback['amount'],
            'real_amount' => $callback['real_amount'],
            'appKey' => $callback['appKey'],
            'payType' => $callback['payType'],
            'orderID' => $callback['orderID'],
            'status' => $callback['status'],
            $this->signField => $callback[$this->signField],
        ];

        if (false === $this->verifySignature($signParams, $order['merchant_key'])) {
            return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 二次校驗
        $this->doubleCheck($callback, $orderNo, $order);

        // 更新订单
        $callback['status'] = intval($callback['status']);
        $update = [
            'status' => $this->transformWithdrawStatus($callback['status']),
            'trade_no' => $callback['payout_id'] ?? '',
        ];
        $this->updateOrder($orderNo, $update, 'withdraw');

        // 回调集成网址
        $callbackUrl = $order['callback_url'];

        // 返回数据给集成网关更新
        $params = $this->transferOrderInfo($callback, $order);

        // 回调集成网关
        $response = $this->sendCallback($callbackUrl, $orderNo, $params);

        // 通知三方回调结果
        return $this->responsePlatform($response['code'] ?? '01');
    }

    /**
     * 若创建订单时带有三方查询网址 query_url 参数则可发起二次校验
     */
    private function doubleCheck(array $callback, string $orderNo, array $order): void
    {
        if (empty($order['query_url'])) {
            return;
        }

        // Custom header params
        $this->appendHeaders($order['header_params']);

        // 验签成功, 调用第三方查询接口判断该订单在第三方系统里是否支付成功
        $response = $this->queryOrderInfo($order['query_url'], $order);

        if (!$response['success']) {
            throw new ApiException('查單失敗 ' . $orderNo);
        }

        // 二次校驗簽名
        $signParams = [
            'appKey' => $order['merchant_id'],
            'orderID' => $orderNo,
            'status' => $response['result']['status'],
            'amount' => number_format($response['result']['amount'], 1, '.', ''),
            'real_amount' => number_format($response['result']['real_amount'], 1, '.', ''),
            $this->signField => $response['result'][$this->signField],
        ];

        if (false === $this->verifySignature($signParams, $order['merchant_key'])) {
            throw new ApiException('二次验证签名失败 ' . $orderNo);
        }

        // 三方訂單狀態與回調訂單狀態不一致
        if ($response['result']['status'] != $callback['status']) {
            throw new ApiException('訂單狀態確認失敗 ' . $orderNo);
        }
    }
}
