<?php

declare(strict_types=1);

namespace App\Payment\Drivers\ManHe;

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
        // {"payout_id":"STARDREAMPOT00065748","payout_cl_id":"manhe_withdraw_30029531","platform_id":"PF0111","amount":100000,"fee":0,"status":3,"create_time":1714974051,"update_time":1714974201,"sign":"3a1df0daede2491568e9fb6730130ed3"}
        $callback = $request->all();

        $orderNo = $callback['payout_cl_id'] ?? '';

        $this->logger->info(sprintf('%s 回調參數', $orderNo), $callback);

        // 查询订单
        $order = $this->getOrder($orderNo, 'withdraw');

        if (false === $this->verifySignature($callback, $order['merchant_key'])) {
            return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 二次校驗
        $this->doubleCheck($callback, $orderNo, $order);

        // 更新订单
        $update = [
            'status' => $this->transformWithdrawStatus($callback['status']),
            'trade_no' => $callback['payout_id'],
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

        if ('0000' !== $response['error_code']) {
            throw new ApiException('TP Error!', ErrorCode::ERROR, $response);
        }

        // 查無訂單數據
        if (0 == count($response['data'])) {
            throw new ApiException('TP 查無訂單號 ' . $orderNo . '!', ErrorCode::ERROR, $response);
        }

        if ($response['data'][0]['status'] != $callback['status']) {
            throw new ApiException('訂單狀態確認失敗 ' . $orderNo);
        }

        if (false === $this->verifySignature($response['data'][0], $order['merchant_key'])) {
            throw new ApiException('二次验证签名失败 ' . $orderNo);
        }
    }
}
