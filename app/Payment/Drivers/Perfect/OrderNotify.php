<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Perfect;

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

        // {
        //     'agent': 'T_aisle168',
        //     'system_sn': 'Dflhnxerysessdqnb8evvwhgagv9fj',
        //     'order_sn': 'Perfect_2405161139h6yi91',
        //     'amount': '500',
        //     'total_commission': '43',
        //     'status': '01',
        //     'type': '0',
        //     'created_at': '2024-05-16 11:39:07',
        //     'completed_at': '2024-05-16 12:17:11',
        //     'overtime_at': '2024-05-17 11:39:07',
        //     'sign': 'd0f95e5a0c5fc16520e1aa40c9a45972'
        // }

        // 回調請求中的商戶訂單號
        $orderNo = $callback['order_sn'] ?? '';

        $this->logger->info(sprintf('%s 回調參數', $orderNo), $callback);

        // 查询订单
        $order = $this->getOrder($orderNo);

        // 验证签名
        if (false === $this->verifySignature($callback, $order['merchant_key'])) {
            return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 更新订单
        $update = [
            'status' => $this->transformStatus($callback['status']),
            'real_amount' => $callback['amount'],
            'trade_no' => $callback['system_sn'],
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
