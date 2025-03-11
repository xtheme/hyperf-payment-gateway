<?php

declare(strict_types=1);

namespace App\Payment\Drivers\MaShang;

use App\Common\Response;
use App\Constants\ErrorCode;
use App\Exception\ApiException;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class OrderNotify extends OrderQuery
{
    /**
     * 三方回調通知, 更新訂單
     */
    public function request(RequestInterface $request): ResponseInterface
    {
        // {"amount":"100000","real_amount":"100000","order_no":"mashang_46731090","trade_no":"D17159274350170464","payment_platform":"mashang","payment_channel":"ALIPAY","status":"2","remark":"mashang_46731090","created_at":1715927982,"raw":"{\"gamerOrderId\":\"D17159274350170464\",\"merchantOrderId\":\"mashang_46731090\",\"sign\":\"23aee554265377b7b12f5e55661c72be\",\"currencyCode\":\"CNY\",\"paymentTypeCode\":\"ALIPAY\",\"amount\":\"1000.00\",\"remark\":\"mashang_46731090\",\"status\":\"Success\",\"mp\":null}"}
        $callback = $request->all();

        $orderNo = $callback['merchantOrderId'] ?? '';

        $this->logger->info(sprintf('%s 回調參數', $orderNo), $callback);

        // 查询订单
        $order = $this->getOrder($orderNo);

        // 验证签名
        if (false === $this->verifySignature($callback, $order['merchant_key'])) {
            return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 二次校驗
        $this->doubleCheck($callback, $orderNo, $order);

        $update = [
            'status' => $this->transformStatus($callback['status']),
            'real_amount' => $callback['amount'],
            'trade_no' => $callback['gamerOrderId'],
        ];
        $this->updateOrder($orderNo, $update);

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

        if (true !== $response['result']) {
            throw new ApiException('查單失敗 ' . $orderNo);
        }

        if ($response['data']['status'] != $callback['status']) {
            throw new ApiException('訂單狀態確認失敗 ' . $orderNo);
        }

        if (false === $this->verifySignature($response['data'], $order['merchant_key'])) {
            throw new ApiException('二次验证签名失败 ' . $orderNo);
        }
    }
}
