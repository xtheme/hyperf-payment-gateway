<?php

declare(strict_types=1);

namespace App\Payment\Drivers\ShunSin;

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
        // {"merchantId":"YG8888","merchantOrderId":"shunsin_withdraw_65687773","status":"3","orderType":"1","orderAmount":"1000.00","systemOrderId":"7d1e9faa67a8447aaae0bd91d069b98d","remark":"","submitIp":"34.92.218.106","sign":"05fe74388fe84fd59958d6dc2a6ff8fe"}
        $callback = $request->all();

        $orderNo = $callback['merchantOrderId'] ?? '';

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
            'trade_no' => $callback['systemOrderId'],
        ];
        $this->updateOrder($orderNo, $update, 'withdraw');

        // 回调集成网址
        $callbackUrl = $order['callback_url'];

        // 返回数据给集成网关更新
        $callback['MerchantOrderId'] = $callback['merchantOrderId'];
        $callback['OrderAmount'] = $callback['orderAmount'];
        $callback['Status'] = $callback['status'];
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

        if ('' !== $response['ErrorCode']) {
            throw new ApiException('查單失敗 ' . $orderNo);
        }

        if ($response['Status'] != $callback['status']) {
            throw new ApiException('訂單狀態確認失敗 ' . $orderNo);
        }
    }
}
