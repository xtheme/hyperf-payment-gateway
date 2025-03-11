<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Zhangcheng;

use App\Common\Response;
use App\Constants\ErrorCode;
use App\Exception\ApiException;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function Hyperf\Config\config;

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

        // 連接 sign_key
        $sign_key = $order['merchant_key'] . json_decode($order['body_params'], true)['customer_service_key'];

        // 验证签名
        if ($callback[$this->signField] !== $this->getSignature($callback, $sign_key)) {
            return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 二次教驗
        $data = $this->doubleCheck($callback, $orderNo, $order);

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

        // 三方返回数据示例, data 為陣列
        // {
        //  "customer_id": 50003,
        //  "order_id": "99523425405591",
        //  "transaction_id": "T2020120819255110016375591",
        //  "order_amount": 500,
        //  "real_amount": 500,
        //  "sign": "1fc494c688dbe76693e9193d900000fd",
        //  "status": "30000",
        //  "message": "⽀付成功",
        //  "extra": {
        //  "user_name": "玩家姓名",
        //  "pay_product_name": null
        // }

        if ('SUCCESS' != $response['retCode']) {
            $this->logger->error('TP Error: ' . json_encode($response, JSON_UNESCAPED_SLASHES));

            throw new ApiException('查單失敗 ' . $orderNo);
        }

        // 三方訂單狀態與回調訂單狀態不一致

        if (($response['status']) != $callback['status']) {
            if ('dev' != config('app_env')) {
                throw new ApiException('訂單狀態確認失敗 ' . $orderNo);
            }
            $this->logger->warning('訂單狀態確認失敗 ' . $orderNo);
        }

        // 二次校驗簽名  查單返回無簽名
        // if (false === $this->verifySignature($response['data'][0], $order['merchant_key'])) {
        //     throw new ApiException('二次验证签名失败 ' . $orderNo);
        // }
        return $response;
    }
}
