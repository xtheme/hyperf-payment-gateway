<?php

declare(strict_types=1);

namespace App\Payment\Drivers\GsPay;

use App\Common\Response;
use App\Constants\ErrorCode;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function Hyperf\Config\config;

class OrderNotify extends OrderQuery
{
    /**
     * 三方回調通知, 更新訂單
     */
    public function request(RequestInterface $request): ResponseInterface
    {
        $callback = $request->all();

        // 回調請求中的商戶訂單號
        $orderNo = $callback['MemberOrderNo'] ?? '';

        $this->logger->info(sprintf('%s 回調參數', $orderNo), $callback);

        // 查询订单
        $order = $this->getOrder($orderNo);

        // 三方验证签名
        if (false === $this->verifySignature($callback, $order['merchant_key'])) {
            return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        $redirect = !isset($callback['Status']);

        // 轉跳收銀台
        if ($redirect) {
            // 更新收銀台資訊
            $update = [
                'status' => $this->transformStatus('已建立訂單'),
                'real_amount' => $this->revertAmount($callback['Amount']), // 三方返回的金額須轉換為 "分" 返回集成网关
                'trade_no' => $callback['OrderNo'],
                'payee_bank_name' => $callback['BankName'] ?? '',
                'payee_bank_account' => $callback['PaymentInfo'] ?? '',
            ];
            $this->updateOrder($orderNo, $update);

            $this->logger->info(sprintf('%s 轉跳收銀台', $orderNo), $callback);

            $cashierUrl = config('app_host') . $this->getCashierUrl($orderNo);

            return response()->redirect($cashierUrl);
        }
        // 付款成功, 更新订单
        $update = [
            'status' => $this->transformStatus($callback['Status']),
            // 'real_amount' => $this->revertAmount($callback['Amount']), // 三方返回的金額須轉換為 "分" 返回集成网关
            // 'trade_no' => $callback['OrderNo'],
            // 'payee_bank_name' => $callback['Bank'] ?? '',
            // 'payee_bank_account' => $callback['Account'] ?? '',
        ];
        $this->updateOrder($orderNo, $update);

        $this->logger->info(sprintf('%s 繳費通知', $orderNo), $callback);

        // 回调集成网址
        $callbackUrl = $order['callback_url'];

        // 返回数据给集成网关更新
        $params = $this->transferOrderInfo($callback, $order);

        // 回调集成网关
        $response = $this->sendCallback($callbackUrl, $orderNo, $params);

        return $this->responsePlatform($response['code'] ?? '01');
    }
}
