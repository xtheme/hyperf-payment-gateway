<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Perfect;

use App\Common\Response;
use App\Constants\ErrorCode;
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

        // {
        //     'agent': 'T_aisle168',
        //     'system_sn': 'Wti3roaisfp18sanxobfituqf4b2mh',
        //     'order_sn': 'Perfect_392256652221',
        //     'amount': '500',
        //     'total_commission': '30',
        //     'status': '01',
        //     'type': '1',
        //     'created_at': '2024-05-16 13:35:51',
        //     'completed_at': '2024-05-16 13:37:21',
        //     'overtime_at': '2024-05-17 13:35:51',
        //     'sign': 'aa9971d70b3d75efd327cce1453d6cd9'
        // }

        // 回調請求中的商戶訂單號
        $orderNo = $callback['order_sn'] ?? '';

        $this->logger->info(sprintf('%s 回調參數', $orderNo), $callback);

        // 查询订单
        $order = $this->getOrder($orderNo, 'withdraw');

        // 验证签名
        if (false === $this->verifySignature($callback, $order['merchant_key'])) {
            return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 更新订单
        $update = [
            'status' => $this->transformWithdrawStatus($callback['status']),
            'trade_no' => $callback['system_sn'],
            'commission' => $callback['total_commission'],
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
}
