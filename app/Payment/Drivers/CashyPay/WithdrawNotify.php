<?php

declare(strict_types=1);

namespace App\Payment\Drivers\CashyPay;

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
        // {"amount":10000.0,"completionTime":"2024-07-31 12:54:26","fee":300.0,"mchOrderNo":"cashypay_withdraw_42206949","merchantId":"3006075","nonceStr":"1722405268136","orderNo":"PAYOUT8506865414587559936","orderStatus":"SUCCESS","payType":"11"}
        $callback = $request->all();

        $orderNo = $callback['mchOrderNo'] ?? '';

        $this->logger->info(sprintf('%s 回調參數', $orderNo), $callback);

        // 查询订单
        $order = $this->getOrder($orderNo, 'withdraw');

        // 验签
        $requestSign = $request->getHeaderLine($this->signField);
        $checkSign = md5($request->getBody() . $order['merchant_key']);

        if ($checkSign !== $requestSign) {
            return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 查詢訂單二次校驗訂單狀態 (非必要)
        $this->doubleCheck($callback, $orderNo, $order);

        // 更新订单
        $update = [
            'status' => $this->transformWithdrawStatus($callback['orderStatus']),
            'trade_no' => $callback['orderNo'],
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

        // 验签成功, 调用第三方查询接口判断该订单在第三方系统里是否支付成功
        $response = $this->queryOrderInfo($order['query_url'], $order);

        if (200 !== $response['code']) {
            throw new ApiException('訂單查詢失敗 ' . $orderNo);
        }

        // 三方訂單狀態與回調訂單狀態不一致
        if ($response['data']['orderStatus'] != $callback['orderStatus']) {
            throw new ApiException('訂單狀態確認失敗 ' . $orderNo);
        }
    }
}
