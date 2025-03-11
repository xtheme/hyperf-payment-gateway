<?php

declare(strict_types=1);

namespace App\Payment\Drivers\HeiShi;

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
        $orderNo = $callback['out_trade_no'] ?? '';

        $this->logger->info(sprintf('%s 回調參數', $orderNo), $callback);

        // 查询订单
        $order = $this->getOrder($orderNo);

        // 验证签名
        if (false === $this->verifySignature($callback, $order['merchant_key'])) {
            return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 二次校驗
        $this->doubleCheck($callback, $orderNo, $order);

        // 更新订单
        $update = [
            'status' => $this->transformStatus($callback['pay_status']),
            // 'real_amount' => $callback['pay_amount'],
            'real_amount' => $this->revertAmount($callback['pay_amount']),
            'trade_no' => $callback['trade_no'],
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

        // 验签成功, 调用第三方查询接口判断该订单在第三方系统里是否支付成功
        $response = $this->queryOrderInfo($order['query_url'], $order);

        // 三方返回数据示例
        // {
        //     "code": 200,
        //     "msg": "success",
        //     "data": {
        //         "thoroughfare": "1000",
        //         "account_name": "MX8888",
        //         "pay_time": 0,
        //         "pay_status": 3,
        //         "status": "success",
        //         "amount": "10.00",
        //         "pay_amount": "10.00",
        //         "out_trade_no": "Test_1002-1",
        //         "trade_no": "4515120231002975457",
        //         "timestamp": 1696214194,
        //         "fees": "0.1100"
        //     }
        // }

        if (!isset($response['data']['out_trade_no']) || $orderNo != $response['data']['out_trade_no']) {
            throw new ApiException('查單失敗 ' . $orderNo);
        }

        // 三方訂單狀態與回調訂單狀態不一致
        if ($response['data']['pay_status'] != $callback['pay_status']) {
            throw new ApiException('訂單狀態確認失敗 ' . $orderNo);
        }

        // 二次校驗簽名
        // if ($response['sign'] !== $this->getSignature($response['data'], $order['merchant_key'])) {
        //     throw new ApiException('二次验证签名失败 ' . $orderNo);
        // }
    }
}
