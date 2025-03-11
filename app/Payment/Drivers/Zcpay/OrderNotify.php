<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Zcpay;

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
        $orderNo = $callback['order_no'] ?? '';

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
            'status' => $this->transformStatus($callback['status']),
            'amount' => $callback['money'],
            'real_amount' => $callback['rec_money'],
            'trade_no' => $callback['plat_no'],
        ];

        $this->updateOrder($orderNo, $update);

        // 查询订单
        $order = $this->getOrder($orderNo);

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

        // Custom header params
        // $this->appendHeaders($order['header_params']);

        // 验签成功, 调用第三方查询接口判断该订单在第三方系统里是否支付成功
        $response = $this->queryOrderInfo($order['query_url'], $order);

        // 三方返回数据示例, data 為陣列
        // {
        //     "appid": "XXX12345",
        //     "order_no": "P20241710742695152",
        //     "money": "2000.00",
        //     "rec_money": "1800.00",
        //     "channel_id": 1,
        //     "plat_no": "9ab9d758363a4499ae4412248802b3e4",
        //     "status": 1,
        //     "sign": "64115ef8461e20eeddaebb11d91972ce"
        //     }

        if (200 != $response['code']) {
            $this->logger->error('TP Error: ' . json_encode($response, JSON_UNESCAPED_SLASHES));

            throw new ApiException('查單失敗 ' . $orderNo);
        }

        // 三方訂單狀態與回調訂單狀態不一致
        if (($response['data']['status']) != $callback['status']) {
            if ('dev' != config('app_env')) {
                throw new ApiException('訂單狀態確認失敗 ' . $orderNo);
            }
            $this->logger->warning('訂單狀態確認失敗 ' . $orderNo);
        }

        // // 二次校驗簽名  查單返回無簽名
        // if (false === $this->verifySignature($response['data'], $order['merchant_key'])) {
        //     throw new ApiException('二次验证签名失败 ' . $orderNo);
        // }
        return $response;
    }
}
