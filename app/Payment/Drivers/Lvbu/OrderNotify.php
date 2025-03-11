<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Lvbu;

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
        $orderNo = $callback['mchOrderNo'] ?? '';

        $this->logger->info(sprintf('%s 回調參數', $orderNo), $callback);

        // 查询订单
        $order = $this->getOrder($orderNo);

        // 验证签名
        if (false === $this->verifySignature($callback, $order['merchant_key'])) {
            return Response::error('验证签名失败', ErrorCode::ERROR, ['orderNo' => $orderNo]);
        }

        // 二次校驗
        $this->doubleCheck($callback, $orderNo, $order);

        // 更新订单
        $update = [
            'status' => $this->transformStatus($callback['status']),
            'real_amount' => $callback['amount'],
            'trade_no' => $callback['payOrderId'],
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
        //     "amount": "10000",
        //     "mchOrderNo": "lvbu_order41340392",
        //     "productId": "8004",
        //     "payOrderId": "LB202305081614177525410",
        //     "status": "1",
        //     "mchId": "10013",
        //     "paySuccTime": "",
        //     "retCode": "SUCCESS",
        //     "sign": "7B4A75811BB0D4D2F7F903618D62C918"
        // }

        if (!isset($response['mchOrderNo']) || $response['mchOrderNo'] != $orderNo) {
            throw new ApiException('查單失敗 ' . $orderNo);
        }

        // 三方訂單狀態與回調訂單狀態不一致
        if ($response['status'] != $callback['status']) {
            throw new ApiException('訂單狀態確認失敗 ' . $orderNo);
        }

        // 二次校驗簽名
        if (false === $this->verifySignature($response, $order['merchant_key'])) {
            throw new ApiException('二次验证签名失败 ' . $orderNo);
        }

        // 異步回調支付結果
        // $redis_queue_key = 'orders:callback';
        // redis()->lRem($redis_queue_key, $order_no, 0);
        // co(function () use ($callback, $cache_key, $order, $order_no) {
        //     if ($response['code'] != '00') {
        //         // 回調集成失敗, 放入隊列
        //         redis()->rPush($redis_queue_key, $order_no);
        //         // return response()->raw('fail');
        //     }
        // });
    }
}
