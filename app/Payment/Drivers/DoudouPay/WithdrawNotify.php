<?php

declare(strict_types=1);

namespace App\Payment\Drivers\DoudouPay;

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
        $callback = $request->all();

        // 回調請求中的商戶訂單號
        $orderNo = $callback['orderId'] ?? '';

        $this->logger->info(sprintf('%s 回調參數', $orderNo), $callback);

        // 查询订单
        $order = $this->getOrder($orderNo, 'withdraw');

        // 验证签名
        // if (false === $this->verifySignature($callback, $order['merchant_key'])) {
        //     return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        // }

        // 二次校驗

        // 更新订单
        // $update = [
        //     'status' => $this->transformWithdrawStatus($callback['status']),
        //     'trade_no' => $callback['payout_id'],
        // ];
        // $this->updateOrder($orderNo, $update, 'withdraw');

        // 回调集成网址
        $callbackUrl = $order['callback_url'];

        // 返回数据给集成网关更新
        $params = $this->doubleCheck($callback, $orderNo, $order);
        // $params = $this->transferOrderInfo($callback, $order);

        // 回调集成网关
        $response = $this->sendCallback($callbackUrl, $orderNo, $params);

        // 通知三方回调结果
        return $this->responsePlatform($response['code'] ?? '01');
    }

    /**
     * 若创建订单时带有三方查询网址 query_url 参数则可发起二次校验
     */
    private function doubleCheck(array $callback, string $orderNo, array $order)
    {
        if (empty($order['query_url'])) {
            return;
        }

        // Custom header params
        // $this->appendHeaders($order['header_params']);

        // 验签成功, 调用第三方查询接口判断该订单在第三方系统里是否支付成功
        $response = $this->queryOrderInfo($order['query_url'], $order);

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

        if (0 != $response['retcode']) {
            $this->logger->error('TP Error: ' . json_encode($response, JSON_UNESCAPED_SLASHES));

            throw new ApiException('查單失敗 ' . $orderNo);
        }

        return [
            'amount' => $this->revertAmount($callback['payAmount']),
            'fee' => 0,
            'order_no' => $response['orderId'],
            'trade_no' => $response['id'],
            'payment_platform' => $order['payment_platform'],
            'payment_channel' => $order['payment_channel'],
            'status' => $this->transformWithdrawStatus($response['status']),
            'remark' => $response['memo'] ?? '',
            'created_at' => $this->getServerDateTime(),
            // 返回集成使用 UTC
            'raw' => json_encode($response, JSON_UNESCAPED_SLASHES),
            // 三方返回的原始资料
        ];

        // 三方訂單狀態與回調訂單狀態不一致
        // if ($response['data'][0]['status'] != $callback['status']) {
        //     if ('dev' != config('app_env')) {
        //         throw new ApiException('訂單狀態確認失敗 ' . $orderNo);
        //     }
        //     $this->logger->warning('訂單狀態確認失敗 ' . $orderNo);
        // }

        // 二次校驗簽名
        // if (false === $this->verifySignature($response['data'][0], $order['merchant_key'])) {
        //     throw new ApiException('二次验证签名失败 ' . $orderNo);
        // }
    }
}
