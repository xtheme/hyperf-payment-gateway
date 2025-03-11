<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Xusheng;

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
        $orderNo = $callback['outTradeNo'] ?? '';

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
            'status' => $this->transformStatus($callback['state']),
            'real_amount' => $callback['amount'],
            'trade_no' => $callback['tradeNo'],
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
        //     "code": 0,
        //     "message": "ok",
        //     "data": {
        //         "mchId": "M1681128855",
        //         "wayCode": 2,
        //         "tradeNo": "P1645636935016587264",
        //         "outTradeNo": "xusheng_order22448142",
        //         "originTradeNo": "1",
        //         "amount": "80000",
        //         "subject": "stt",
        //         "body": null,
        //         "extParam": null,
        //         "notifyUrl": "http://127.0.0.1:9501/api/v1/payment/notify/xusheng",
        //         "payUrl": "http://pay.hgnewcloud.com/c/api/pay?osn=2023041111563439460879193",
        //         "expiredTime": "1681185694",
        //         "successTime": "1681185395",
        //         "createTime": "1681185395",
        //         "state": 2,
        //         "notifyState": 0
        //     },
        //     "sign": "9363cb3af6087b210659e34cdef1e99e"
        // }

        if (!isset($response['data']['outTradeNo']) || $orderNo != $response['data']['outTradeNo']) {
            throw new ApiException('查單失敗 ' . $orderNo);
        }

        // 三方訂單狀態與回調訂單狀態不一致
        if ($response['data']['state'] != $callback['state']) {
            throw new ApiException('訂單狀態確認失敗 ' . $orderNo);
        }

        // 二次校驗簽名
        if ($response['sign'] !== $this->getSignature($response['data'], $order['merchant_key'])) {
            throw new ApiException('二次验证签名失败 ' . $orderNo);
        }
    }
}
