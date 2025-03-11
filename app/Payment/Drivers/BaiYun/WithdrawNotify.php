<?php

declare(strict_types=1);

namespace App\Payment\Drivers\BaiYun;

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
        $callback = $request->all();

        // 回調請求中的商戶訂單號
        $orderNo = str_replace('baiyun', 'baiyun_', $callback['fxddh']);

        $this->logger->info(sprintf('%s 回調參數', $orderNo), $callback);

        // 查询订单
        $order = $this->getOrder($orderNo, 'withdraw');

        // 验证签名
        if (false === $this->verifySignature([$callback, ['fxstatus', 'fxid', 'fxddh', 'fxfee'], 'fxsign' => $callback['fxsign']], $order['merchant_key'])) {
            return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 發起三方查單，正確的訂單狀態必須查單才有
        $callback = $this->doubleCheck($callback, $orderNo, $order);

        // 回调集成网址
        $callbackUrl = $order['callback_url'];

        // 返回数据给集成网关更新
        $params = $this->transferOrderInfo($callback, $order);

        // 更新订单
        $update = [
            'status' => $this->transformWithdrawStatus($callback['fxstatus']),
            'trade_no' => $callback['fxddh'],
        ];
        $this->updateOrder($orderNo, $update, 'withdraw');

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

        if (1 != $response['fxstatus']) {
            $this->logger->error('TP Error: ' . json_encode($response, JSON_UNESCAPED_SLASHES));

            throw new ApiException('查單失敗 ' . $orderNo);
        }

        $body = json_decode($response['fxbody'], true);

        return array_merge($callback, $body[0]);
    }
}
