<?php

declare(strict_types=1);

namespace App\Payment\Drivers\PT;

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
        // {"merchantID":"052D278F152C","orderNo":"pt_withdraw_66134699","tradeAmt":"2000.00","notifyTime":"2024-05-09 17:54:32","status":true,"sign":"ano8jgzjn9r2ugqek7mafg=="}
        $callback = $request->all();

        $orderNo = $callback['orderNo'] ?? '';

        $this->logger->info(sprintf('%s 回調參數', $orderNo), $callback);

        // 查询订单
        $order = $this->getOrder($orderNo, 'withdraw');

        $body = json_decode($order['body_params'], true);

        if (false === $this->verifySignature($callback, $body['secret'])) {
            return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 二次校驗
        $chk = $this->doubleCheck($callback, $orderNo, $order);

        // 更新订单
        $update = [
            'status' => $this->transformWithdrawStatus($chk['status']),
            'trade_no' => $chk['sysOrderNo'] ?? '',
        ];
        $this->updateOrder($orderNo, $update, 'withdraw');

        // 回调集成网址
        $callbackUrl = $order['callback_url'];

        // 返回数据给集成网关更新
        $callback['sysOrderNo'] = $chk['sysOrderNo'];
        $callback['status'] = $chk['status'];
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

        // Custom header params
        $this->appendHeaders($order['header_params']);

        // 验签成功, 调用第三方查询接口判断该订单在第三方系统里是否支付成功
        $response = $this->queryOrderInfo($order['query_url'], $order);

        if (100 !== $response['code']) {
            throw new ApiException('TP Error!', ErrorCode::ERROR, $response);
        }

        $chk = $this->transformWithdrawStatus($response['data']['status']);

        if ($callback['status']) {
            if ('3' !== $chk) {
                throw new ApiException('訂單狀態確認失敗 ' . $orderNo);
            }
        } else {
            if ('5' !== $chk) {
                throw new ApiException('訂單狀態確認失敗 ' . $orderNo);
            }
        }

        return $response['data'];
    }
}
