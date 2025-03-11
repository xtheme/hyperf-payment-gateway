<?php

declare(strict_types=1);

namespace App\Payment\Drivers\TopPay;

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
        // {"bankCode":"008","fee":"5570","description":"toppay_withdraw_20753600","orderNum":"toppay_withdraw_20753600","feeType":"1","number":"0060011643339","platOrderNum":"W06202407231625189096419","money":"10000","statusMsg":"SUCCESS","name":"Siswanto","platSign":"jwS4Sfq4UxnmaeERLWjJzmrZcySxi9McAAIht73cCKwtt8m6cKa9tgNWUCKWXSWIdU+5uX0Mr8Ez14y04kBU1gMfaXfVJs1OfmPwSTPVR3hNkFzyMtk1rMHXPLnkxIWylCFDbqGxoAd8Kf1CrOBh7/L8VFv8qZm7yB+4kX6Uzfg=","status":"2"}
        $callback = $request->all();

        $orderNo = $callback['orderNum'] ?? '';

        $this->logger->info(sprintf('%s 回調參數', $orderNo), $callback);

        // 查询订单
        $order = $this->getOrder($orderNo, 'withdraw');

        // 三方验证签名
        $chkSign = $this->decryptSign($callback['platSign'], $order);
        $chkParams = $callback;
        unset($chkParams['platSign']);
        ksort($chkParams);
        $params_str = '';

        foreach ($chkParams as $key => $val) {
            $params_str = $params_str . $val;
        }

        if ($params_str !== $chkSign) {
            return Response::error('验证签名失败', ErrorCode::ERROR, $callback);
        }

        // 查詢訂單二次校驗訂單狀態 (非必要)
        $this->doubleCheck($callback, $orderNo, $order);

        // 更新订单
        $update = [
            'status' => $this->transformWithdrawStatus($callback['status']),
            'trade_no' => $callback['platOrderNum'],
        ];
        $this->updateOrder($orderNo, $update, 'withdraw');

        // 回调集成网址
        $callbackUrl = $order['callback_url'];

        // 返回数据给集成网关更新
        $callback['amount'] = $callback['money'];
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

        if (1000 !== $response['code']) {
            throw new ApiException('查詢訂單失敗 ' . $orderNo);
        }

        if ($response['data']['status'] != $callback['status']) {
            throw new ApiException('訂單狀態確認失敗 ' . $orderNo);
        }
    }
}
