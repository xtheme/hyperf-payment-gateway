<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Apollo;

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
        // {"tradeNo":"apollo_withdraw_76198538","orderAmount":"100000","tradeStatus":"1","message":"","sign":"a61789bbbb9c20feae8d1cfbfaad8346"}
        $callback = $request->all();

        $orderNo = $callback['tradeNo'] ?? '';

        $this->logger->info(sprintf('%s 回調參數', $orderNo), $callback);

        // 查询订单
        $order = $this->getOrder($orderNo, 'withdraw');

        $signParams = [
            'tradeNo' => $callback['tradeNo'],
            'orderAmount' => $callback['orderAmount'],
            $this->signField => $callback[$this->signField],
        ];

        if (false === $this->verifySignature($signParams, $order['merchant_key'])) {
            return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 查詢訂單二次校驗訂單狀態
        $queryRes = $this->doubleCheck($callback, $orderNo, $order);

        // 更新订单
        $update = [
            'status' => $this->transformWithdrawStatus($callback['tradeStatus']),
            'trade_no' => $queryRes['oid'],
        ];
        $this->updateOrder($orderNo, $update, 'withdraw');

        // 回调集成网址
        $callbackUrl = $order['callback_url'];

        // 返回数据给集成网关更新
        $callback['oid'] = $queryRes['oid'];
        $callback['status'] = $callback['tradeStatus'];
        $params = $this->transferOrderInfo($callback, $order);

        // 回调集成网关
        $response = $this->sendCallback($callbackUrl, $orderNo, $params);

        // 通知三方回调结果
        return $this->responsePlatform($response['code'] ?? '01');
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

        if (1 !== $response['Success']) {
            throw new ApiException('查單失敗 ' . $orderNo);
        }

        if ($response['status'] != $callback['tradeStatus']) {
            throw new ApiException('訂單狀態確認失敗 ' . $orderNo);
        }

        return $response;
    }
}
