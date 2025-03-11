<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Sulifu;

use App\Common\Response;
use App\Constants\ErrorCode;
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

        // 回調請求中的商戶訂單號
        $orderNo = $callback['tradeNo'] ?? '';

        $this->logger->info(sprintf('%s 回調參數', $orderNo), $callback);

        // 查询订单
        $order = $this->getOrder($orderNo);

        // 參與簽名字段, 有順序性
        $signParams = [
            'tradeNo' => $callback['tradeNo'],
            'topupAmount' => $callback['topupAmount'],
        ];

        // 验证签名
        if ($callback['sign'] !== $this->getSignature($signParams, $order['merchant_key'])) {
            return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 更新订单
        $update = [
            'status' => $this->transformStatus($callback['tradeStatus']),
            'real_amount' => $this->revertAmount($callback['topupAmount']),
            'trade_no' => $callback['tradeNo'],
        ];
        $this->updateOrder($orderNo, $update);

        // 回调集成网址
        $callbackUrl = $order['callback_url'];

        // 返回数据给集成网关更新
        $params = $this->transferOrderInfo($callback, $order);

        // 回调集成网关
        $response = $this->sendCallback($callbackUrl, $orderNo, $params);

        // 通知三方回调结果
        return $this->responsePlatform($response['code'] ?? '01');
    }
}
