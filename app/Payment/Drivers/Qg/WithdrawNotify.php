<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Qg;

use App\Common\Response;
use App\Constants\ErrorCode;
use App\Exception\ApiException;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function Hyperf\Config\config;

class WithdrawNotify extends WithdrawQuery
{
    /**
     * 三方回調通知, 更新訂單
     */
    public function request(RequestInterface $request): ResponseInterface
    {
        $callback = $request->all();

        // 回調請求中的商戶訂單號
        $orderNo = $callback['order_id'] ?? '';

        $this->logger->info(sprintf('%s 回調參數', $orderNo), $callback);

        // 查询订单
        $order = $this->getOrder($orderNo, 'withdraw');

        // 拿取 merchant_key、signKey
        $Keys = $order['merchant_key'] . '|' . json_decode($order['body_params'], true)['signKey'];

        // 验证签名
        if (false === $this->verifySignature($callback, $Keys)) {
            return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 二次校驗
        $data = $this->doubleCheck($callback, $orderNo, $order);

        // 取得查單資訊
        $data = $data[0];

        // 更新订单
        $update = [
            'status' => $this->transformWithdrawStatus($callback['status']),
            'trade_no' => $callback['order_sid'],
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
    private function doubleCheck(array $callback, string $orderNo, array $order): array
    {
        if (empty($order['query_url'])) {
            return [];
        }

        // Custom header params
        $this->appendHeaders($order['header_params']);

        // 验签成功, 调用第三方查询接口判断该订单在第三方系统里是否支付成功
        $response = $this->queryOrderInfo($order['query_url'], $order);

        // 三方返回数据示例, data 為陣列
        // {
        //     "rows": 1,
        //     "start": 0,
        //     "data": [
        //     {
        //     "order_sid": 202302242300001,
        //     "order_id": "a10016",
        //     "payer": "玩家",
        //     "type": 2,
        //     "amount": "100.00",
        //     "init_time": "2023-02-24 23:04:55",
        //     "status": 0,
        //     "is_finish": false,
        //     "finish_time": null,
        //     "message": "test order",
        //     "callback": "https://test.callback.url"
        //     },
        //
        //     ],
        //     "code": 200
        // }

        if (200 != $response['code'] || 0 == $response['rows']) {
            $this->logger->error('TP Error: ' . json_encode($response, JSON_UNESCAPED_SLASHES));

            throw new ApiException('查單失敗 ' . $orderNo);
        }

        // 三方訂單狀態與回調訂單狀態不一致
        if ($response['data'][0]['status'] != $callback['status']) {
            if ('dev' != config('app_env')) {
                throw new ApiException('訂單狀態確認失敗 ' . $orderNo);
            }
            $this->logger->warning('訂單狀態確認失敗 ' . $orderNo);
        }

        // 二次校驗簽名
        // if (false === $this->verifySignature($response['data'][0], $order['merchant_key'])) {
        //     throw new ApiException('二次验证签名失败 ' . $orderNo);
        // }
        return $response['data'];
    }
}
