<?php

declare(strict_types=1);

namespace App\Payment\Drivers\JinPay;

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
        // {"amount":10000.0,"tradesno":"202408021603480228","appid":"IDS88","sign":"5fb0a65b1af2b96ece769bd7c77fa7fd","apporderid":"jinpay_withdraw_23749649","status":2}
        $callback = $request->all();

        $orderNo = $callback['apporderid'] ?? '';

        $this->logger->info(sprintf('%s 回調參數', $orderNo), $callback);

        // 查询订单
        $order = $this->getOrder($orderNo, 'withdraw');

        $signParams = $callback;
        ksort($signParams);
        unset($signParams[$this->signField]);
        $tempStr = urldecode(http_build_query($signParams));
        $tempStr .= $order['merchant_key'];
        $ar = explode('&', $tempStr);
        $tempStr2 = '';

        foreach ($ar as $key => $value) {
            $tempStr2 .= $value;

            if ('amount' == substr($value, 0, 6)) {
                $tempStr2 .= '.00';
            }
            $tempStr2 .= '&';
        }
        $tempStr2 = substr($tempStr2, 0, strlen($tempStr2) - 1);
        $chk = strtolower(md5($tempStr2));

        // if (false === $this->verifySignature($callback, $order['merchant_key'])) {
        if ($chk !== $callback[$this->signField]) {
            return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 查詢訂單二次校驗訂單狀態
        $this->doubleCheck($callback, $orderNo, $order);

        // 更新订单
        $update = [
            'status' => $this->transformWithdrawStatus($callback['status']),
            'trade_no' => $callback['tradesno'],
        ];
        $this->updateOrder($orderNo, $update, 'withdraw');

        // 回调集成网址
        $callbackUrl = $order['callback_url'];

        // 返回数据给集成网关更新
        $callback['orderId'] = $callback['tradesno'];
        $callback['orderStatus'] = $callback['status'];
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

        if (0 !== $response['code']) {
            throw new ApiException('訂單查詢失敗 ' . $orderNo);
        }

        if ($response['data']['orderStatus'] != $callback['status']) {
            throw new ApiException('訂單狀態確認失敗 ' . $orderNo);
        }

        if (false === $this->verifySignature($response['data'], $order['merchant_key'], 'withdraw')) {
            throw new ApiException('二次验证签名失败 ' . $orderNo);
        }
    }
}
