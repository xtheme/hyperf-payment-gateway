<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Lelipay;

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
        $callback = $request->all();

        // 回調請求中的商戶訂單號
        $orderNo = $callback['orderId'] ?? '';

        $this->logger->info(sprintf('%s 回調參數', $orderNo), $callback);

        // 查询订单
        $order = $this->getOrder($orderNo);

        // 验证签名
        if ($callback[$this->signField] !== $this->getSignature($callback, $order['merchant_key'])) {
            return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 二次教驗
        $data = $this->doubleCheck($callback, $orderNo, $order);

        // 更新订单
        $update = [
            'status' => $this->transformStatus($data['txnStatus']),
            'real_amount' => $callback['txnAmt'],
            'trade_no' => $callback['txnId'],
        ];

        $this->updateOrder($orderNo, $update);

        // 回调集成网址
        $callbackUrl = $order['callback_url'];

        // 返回数据给集成网关更新
        $params = $this->transferOrderInfo($data, $order);

        // 回调集成网关
        $response = $this->sendCallback($callbackUrl, $orderNo, $params);

        // 通知三方回调结果
        return $this->responsePlatform($response['code'] ?? '01');
    }

    // 二次教驗流程
    private function doubleCheck(array $callback, string $orderNo, array $order): array
    {
        if (empty($order['query_url'])) {
            return [];
        }

        // 验签成功, 调用第三方查询接口判断该订单在第三方系统里是否支付成功
        $response = $this->queryOrderInfo($order['query_url'], $order);

        // {
        //     "Code": "0",
        //     "Message": "OK",
        //     "Amount": "100.00",
        //     "RealAmount": "99.98",
        //     "CommissionAmount": "1.88",
        //     "PayOrderStatus": "0",
        //     "PayOrderId": "D1234567890123456789",
        //     "MerchantUniqueOrderId": "guid123456789",
        //     "Remark":""
        // }

        if ('0000' !== $response['respCode']) {
            $this->logger->error('TP Error: ' . json_encode($response, JSON_UNESCAPED_SLASHES));

            throw new ApiException('查單失敗 ' . $orderNo);
        }

        // 三方訂單狀態與回調訂單狀態不一致
        if ($this->transformStatus($response['txnStatus']) != $this->transformStatus($callback['txnStatus'])) {
            throw new ApiException('訂單狀態確認失敗 ' . $orderNo);
            // $this->logger->warning('訂單狀態確認失敗 ' . $orderNo);
        }

        return $response;
    }
}
